-- ============================================================================
-- extract_district_candidates.sql
-- ----------------------------------------------------------------------------
-- Extract candidate district names from pra.property_description (comma-
-- separated free-form text) into a staging table for review.
--
-- Cleanup rules applied BEFORE classification:
--   * "PLOT 1216 - CHALAWA"  ->  "CHALAWA"   (extract district after " - ")
--   * "PLOT C-113"           ->  empty      ->  INVALID_PLOT
--   * "SANI YARO STREET"     ->  INVALID_STREET (no extraction; rejected)
--   * "DORAYI BABBA ROAD"    ->  INVALID_STREET
--   * "AIRPORT BYPASS"       ->  INVALID_STREET
--   * EXT / EXTENSION suffixes are kept (YARGAYA EXT stays a candidate)
--
-- LGA aliases and state tokens are inlined via VALUES (no temp tables) so
-- the entire extraction runs as a single atomic batch.
--
-- Target: SQL Server 2016+ (uses STRING_SPLIT)
-- Idempotent: drops + recreates the staging table on each run.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

-- Use READ UNCOMMITTED for the extract: prevents deadlocks against other
-- concurrent readers/writers on the (very busy) pra table. This is a
-- one-shot staging build; dirty reads are acceptable.
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

-- ---------------------------------------------------------------------------
-- 0) (Re)create staging table
-- ---------------------------------------------------------------------------
IF OBJECT_ID('dbo.district_extraction_staging', 'U') IS NOT NULL
    DROP TABLE [dbo].[district_extraction_staging];

CREATE TABLE [dbo].[district_extraction_staging] (
    [id]              BIGINT IDENTITY(1,1) NOT NULL,
    [raw_value]       NVARCHAR(255) NOT NULL,
    [normalized]      NVARCHAR(255) NOT NULL,
    [classification]  NVARCHAR(20)  NOT NULL,    -- STATE | LGA_MATCH | DISTRICT_HIT | CANDIDATE | INVALID_PLOT | INVALID_STREET | INVALID_GENERIC | NUMERIC | TOO_SHORT
    [occurrence]      INT           NOT NULL,
    [first_pra_id]    INT           NULL,
    [sample_desc]     NVARCHAR(500) NULL,
    [review_status]   NVARCHAR(20)  NOT NULL DEFAULT 'pending',
    [created_at]      DATETIME2(0)  NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT PK_district_extraction_staging PRIMARY KEY CLUSTERED ([id])
);

CREATE NONCLUSTERED INDEX IX_des_classification ON [dbo].[district_extraction_staging]([classification]);
CREATE NONCLUSTERED INDEX IX_des_normalized     ON [dbo].[district_extraction_staging]([normalized]);
CREATE NONCLUSTERED INDEX IX_des_occurrence     ON [dbo].[district_extraction_staging]([occurrence] DESC);
GO

-- ---------------------------------------------------------------------------
-- 1) Build a single indexed dimension lookup combining LGAs / LGA aliases /
--    state tokens / existing districts. This replaces 4 non-sargable EXISTS
--    subqueries with one hash-friendly equi-join on a clustered index.
-- ---------------------------------------------------------------------------
IF OBJECT_ID('tempdb..#Dim', 'U') IS NOT NULL DROP TABLE #Dim;
CREATE TABLE #Dim (
    norm           NVARCHAR(255) NOT NULL,
    classification NVARCHAR(20)  NOT NULL,
    rank_order     INT           NOT NULL,  -- lower = wins on collision
    CONSTRAINT PK_Dim PRIMARY KEY CLUSTERED (norm)
);

-- State tokens win first (rank 1)
INSERT INTO #Dim (norm, classification, rank_order) VALUES
    ('KANO', 'STATE', 1),
    ('KANO STATE', 'STATE', 1),
    ('NIGERIA', 'STATE', 1),
    ('FCT', 'STATE', 1),
    ('FEDERAL CAPITAL TERRITORY', 'STATE', 1);

-- LGAs (rank 2)
INSERT INTO #Dim (norm, classification, rank_order)
SELECT UPPER(LTRIM(RTRIM(name))), 'LGA_MATCH', 2
FROM dbo.lgas
WHERE name IS NOT NULL AND LTRIM(RTRIM(name)) <> ''
  AND NOT EXISTS (SELECT 1 FROM #Dim d WHERE d.norm = UPPER(LTRIM(RTRIM(lgas.name))));

-- LGA aliases / misspellings (rank 2)
INSERT INTO #Dim (norm, classification, rank_order)
SELECT alias, 'LGA_MATCH', 2 FROM (VALUES
    ('NASSARAWA'),('UNGOGGO'),('DANBATTA'),('MUNINCIPAL'),('MUNICIPAL'),
    ('KANO MUNICIPAL'),('KANO CITY'),('D/KUDU'),('D/TOFA'),
    ('DAWAKIN KUDU'),('DAWAKIN TOFA'),('GARUN MALLAM'),('GARUN MALAM'),
    ('RIMINGADO'),('RIMIN-GADO'),('TUDUN-WADA'),
    ('KIMBOTSO'),('KUMBOTSOI'),('TSANTAWA')
) x(alias)
WHERE NOT EXISTS (SELECT 1 FROM #Dim d WHERE d.norm = x.alias);

-- Existing districts (rank 3)
INSERT INTO #Dim (norm, classification, rank_order)
SELECT UPPER(LTRIM(RTRIM(name))), 'DISTRICT_HIT', 3
FROM dbo.districts
WHERE name IS NOT NULL AND LTRIM(RTRIM(name)) <> ''
  AND NOT EXISTS (SELECT 1 FROM #Dim d WHERE d.norm = UPPER(LTRIM(RTRIM(districts.name))));

DECLARE @dim_count INT = (SELECT COUNT(*) FROM #Dim);
PRINT CONCAT('#Dim built: ', @dim_count, ' lookup keys.');

-- ---------------------------------------------------------------------------
-- 2) Materialize tokens + cleanup into an indexed temp table so the JOIN
--    against #Dim can use a hash/merge join instead of scanning per-row.
-- ---------------------------------------------------------------------------
IF OBJECT_ID('tempdb..#Tokens', 'U') IS NOT NULL DROP TABLE #Tokens;

;WITH Tokens AS (
    SELECT
        p.id                                              AS pra_id,
        p.property_description,
        LTRIM(RTRIM(s.value))                             AS raw_value,
        UPPER(LTRIM(RTRIM(s.value)))                      AS upper_norm
    FROM [dbo].[pra] p WITH (NOLOCK)
    CROSS APPLY STRING_SPLIT(p.property_description, ',') s
    WHERE p.property_description IS NOT NULL
      AND LTRIM(RTRIM(s.value)) <> ''
)
SELECT
    t.raw_value,
    t.upper_norm,
    t.pra_id,
    t.property_description,
    -- Extract district from "PLOT XXX - DISTRICT" pattern (or '' if pure plot)
    CAST(
        CASE
            WHEN t.upper_norm LIKE 'PLOT %'
                THEN
                    CASE WHEN CHARINDEX(' - ', t.upper_norm) > 0
                         THEN LTRIM(RTRIM(SUBSTRING(t.upper_norm, CHARINDEX(' - ', t.upper_norm) + 3, 500)))
                         ELSE ''
                    END
            ELSE t.upper_norm
        END
    AS NVARCHAR(255)) AS cleaned_norm,
    CASE
        WHEN t.upper_norm LIKE '% STREET'    OR t.upper_norm LIKE '% STREET %'
          OR t.upper_norm LIKE '% ROAD'      OR t.upper_norm LIKE '% ROAD %'
          OR t.upper_norm LIKE '% AVENUE'    OR t.upper_norm LIKE '% AVENUE %'
          OR t.upper_norm LIKE '% CLOSE'     OR t.upper_norm LIKE '% CLOSE %'
          OR t.upper_norm LIKE '% CRESCENT'  OR t.upper_norm LIKE '% CRESCENT %'
          OR t.upper_norm LIKE '% LAYOUT'    OR t.upper_norm LIKE '% LAYOUT %'
          OR t.upper_norm LIKE '% QUARTERS'  OR t.upper_norm LIKE '% QUARTERS %'
          OR t.upper_norm LIKE '% WARD'      OR t.upper_norm LIKE '% WARD %'
          OR t.upper_norm LIKE '% BYPASS'    OR t.upper_norm LIKE '% BY PASS'
          OR t.upper_norm LIKE '% BY-PASS'
          OR t.upper_norm LIKE '% LANE'      OR t.upper_norm LIKE '% LANE %'
          OR t.upper_norm LIKE '% DRIVE'     OR t.upper_norm LIKE '% DRIVE %'
            THEN CAST(1 AS BIT)
        ELSE CAST(0 AS BIT)
    END AS is_street_like,
    CASE WHEN t.upper_norm LIKE 'PLOT %' THEN CAST(1 AS BIT) ELSE CAST(0 AS BIT) END AS is_plot_prefixed
INTO #Tokens
FROM Tokens t;

-- Index for the join to #Dim (hash- or merge-friendly)
CREATE NONCLUSTERED INDEX IX_Tokens_cleaned ON #Tokens(cleaned_norm);

DECLARE @tok_count INT = (SELECT COUNT(*) FROM #Tokens);
PRINT CONCAT('#Tokens materialized: ', @tok_count, ' rows.');

-- ---------------------------------------------------------------------------
-- 3) Classify + aggregate + insert into staging.
--    Single LEFT JOIN to #Dim replaces the 4-EXISTS chain.
-- ---------------------------------------------------------------------------
;WITH Classified AS (
    SELECT
        t.raw_value,
        t.cleaned_norm,
        t.pra_id,
        t.property_description,
        CASE
            WHEN t.is_street_like = 1                                                  THEN 'INVALID_STREET'
            WHEN t.is_plot_prefixed = 1 AND (t.cleaned_norm = '' OR LEN(t.cleaned_norm) < 3) THEN 'INVALID_PLOT'
            WHEN t.cleaned_norm IN ('PLOT PIECE OF LAND','PIECE OF LAND','PROPERTY','LAND') THEN 'INVALID_GENERIC'
            WHEN LEN(t.cleaned_norm) < 3                                                THEN 'TOO_SHORT'
            WHEN t.cleaned_norm NOT LIKE '%[^0-9]%'                                      THEN 'NUMERIC'
            WHEN d.classification IS NOT NULL                                            THEN d.classification
            ELSE 'CANDIDATE'
        END AS classification
    FROM #Tokens t
    LEFT JOIN #Dim d ON d.norm = t.cleaned_norm
)
INSERT INTO [dbo].[district_extraction_staging]
    (raw_value, normalized, classification, occurrence, first_pra_id, sample_desc)
SELECT
    MIN(raw_value),
    CASE WHEN cleaned_norm = '' THEN '(empty-plot-only)' ELSE cleaned_norm END,
    classification,
    COUNT(*),
    MIN(pra_id),
    LEFT(MIN(property_description), 500)
FROM Classified
GROUP BY cleaned_norm, classification;

PRINT CONCAT('district_extraction_staging populated: ', @@ROWCOUNT, ' rows.');

DROP TABLE #Tokens;
DROP TABLE #Dim;
GO

-- ---------------------------------------------------------------------------
-- 2) Summary + top 30 CANDIDATEs
-- ---------------------------------------------------------------------------
SELECT classification, COUNT(*) AS distinct_tokens, SUM(occurrence) AS total_occurrences
FROM [dbo].[district_extraction_staging]
GROUP BY classification
ORDER BY total_occurrences DESC;

PRINT '=== TOP 30 CANDIDATES TO REVIEW ===';
SELECT TOP 30
    raw_value,
    normalized,
    occurrence,
    first_pra_id,
    sample_desc
FROM [dbo].[district_extraction_staging]
WHERE classification = 'CANDIDATE'
ORDER BY occurrence DESC;
GO

-- ============================================================================
-- HOW TO USE
-- ----------------------------------------------------------------------------
-- A) Browse candidates by frequency:
--    SELECT * FROM district_extraction_staging
--    WHERE classification = 'CANDIDATE' ORDER BY occurrence DESC;
--
-- B) Reject items that aren't real districts:
--    UPDATE district_extraction_staging
--    SET    review_status = 'rejected'
--    WHERE  normalized IN ('YARGAYA EXT', 'PROPERTY DESCRIPTION HERE');
--
-- C) Approve items as real districts:
--    UPDATE district_extraction_staging
--    SET    review_status = 'approved'
--    WHERE  classification = 'CANDIDATE'
--      AND  normalized IN ('SHARADA','FANISAU','DORAYI BABBA','BADAWA',
--                          'TUDUN YOLA','HOTORO','NAIBAWA','CHALAWA');
--
-- D) Promote approved candidates into the districts table:
--    INSERT INTO districts (name, slug, is_active, created_at, updated_at)
--    SELECT raw_value, LOWER(REPLACE(normalized,' ','-')), 1,
--           SYSUTCDATETIME(), SYSUTCDATETIME()
--    FROM district_extraction_staging
--    WHERE review_status='approved'
--      AND NOT EXISTS (SELECT 1 FROM districts d
--                      WHERE UPPER(LTRIM(RTRIM(d.name))) = normalized);
--
-- E) Re-run this script to refresh: promoted items move CANDIDATE -> DISTRICT_HIT.
-- ============================================================================
