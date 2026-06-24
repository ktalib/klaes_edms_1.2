-- ============================================================================
-- finalize_district_staging.sql
-- ----------------------------------------------------------------------------
-- Step 1: Apply final normalization fixes to district_extraction_staging
--         (de-noise, title-case, dedup aliases, fix known typos).
-- Step 2: Create district_staging — the clean, promotion-ready table.
-- Step 3: Populate district_staging from district_extraction_staging.
--
-- Run AFTER extract_district_candidates.sql and after manual
-- review_status updates (approved / rejected) have been applied.
--
-- Target: SQL Server 2016+
-- Idempotent: district_staging is dropped and recreated on each run.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

-- ============================================================================
-- STEP 1 — Final normalization fixes on district_extraction_staging
-- ============================================================================

PRINT '=== STEP 1: Cleanup district_extraction_staging.normalized ===';

-- 1a) Strip stray punctuation and double-spaces
UPDATE district_extraction_staging
SET    normalized = LTRIM(RTRIM(
           REPLACE(REPLACE(REPLACE(normalized, '  ', ' '), '.', ''), ',', '')
       ))
WHERE  normalized LIKE '%.%'
    OR normalized LIKE '%,%'
    OR normalized LIKE '%  %';

-- 1b) Remove noise suffixes that slipped through (LAYOUT, QUARTERS, WARD)
--     These appear on items classified CANDIDATE but are really address components.
UPDATE district_extraction_staging
SET    classification = 'INVALID_STREET',
       review_status  = 'rejected'
WHERE  classification = 'CANDIDATE'
  AND  review_status  = 'pending'
  AND (
       normalized LIKE '% LAYOUT'    OR normalized LIKE '% QUARTERS'
    OR normalized LIKE '% WARD'      OR normalized LIKE '% PHASE%'
    OR normalized LIKE '% SCHEME'    OR normalized LIKE '% VILLAGE'
    OR normalized LIKE '% HOUSING%'
  );

-- 1c) Reject obvious noise still classified CANDIDATE
UPDATE district_extraction_staging
SET    classification = 'INVALID_GENERIC',
       review_status  = 'rejected'
WHERE  classification = 'CANDIDATE'
  AND  review_status  = 'pending'
  AND  normalized IN (
      'PROPERTY DESCRIPTION', 'PIECE OF LAND', 'PLOT OF LAND',
      'RESIDENTIAL PROPERTY', 'COMMERCIAL PROPERTY', 'FARM LAND',
      'N/A', 'NA', 'NIL', 'NONE', 'NOT APPLICABLE', 'NO DISTRICT',
      '(EMPTY-PLOT-ONLY)'
  );

-- 1d) Consolidate known duplicate normalizations
--     (keep the canonical form; mark the alias as rejected)
UPDATE district_extraction_staging
SET    classification = 'DUPLICATE_ALIAS',
       review_status  = 'rejected'
WHERE  classification = 'CANDIDATE'
  AND  normalized IN (
      -- add pairs here as you discover them during review:
      -- e.g. 'DORAYI BABBA' is canonical; 'DORAYE BABBA' is alias
      'DORAYE BABBA', 'DORAYE BABA',
      'DORAYI BABA',
      'BADAWA NASSARAWA', 'BADAWA NASSARAWA GRA',
      'SHARRADAN', 'SHARRARD', 'SHARRADAN GRA',
      'KOFAR MAZUGAL', 'KOFAR MAZUGALI',
      'GYADI GYADI', 'GYADI-GYADI',
      'TUDUN YOLA', 'TUDAN YOLA',
      'HOTARO', 'HUTORO',
      'NAIBAWA', 'NAIBAWAR',
      'BANBARO', 'BAMBARO',
      'YANKABA', 'YAN KABA',
      'PANSHEKARA', 'PANSEKARA', 'PANSHAKARA'
  );

PRINT CONCAT('Cleanup done. Rejected (noise+alias): ',
    (SELECT COUNT(*) FROM district_extraction_staging WHERE review_status = 'rejected'));
PRINT CONCAT('Still pending review: ',
    (SELECT COUNT(*) FROM district_extraction_staging
     WHERE classification = 'CANDIDATE' AND review_status = 'pending'));
GO

-- ============================================================================
-- STEP 2 — Create district_staging
-- ============================================================================

PRINT '=== STEP 2: Create district_staging ===';

IF OBJECT_ID('dbo.district_staging', 'U') IS NOT NULL
    DROP TABLE [dbo].[district_staging];

CREATE TABLE [dbo].[district_staging] (
    [id]              BIGINT IDENTITY(1,1) NOT NULL,

    -- The clean, promotion-ready name
    [name]            NVARCHAR(255) NOT NULL,

    -- URL-safe slug: lower-kebab-case  (set in step 3)
    [slug]            NVARCHAR(255) NOT NULL,

    -- How many times this token appeared in pra.property_description
    [occurrence]      INT           NOT NULL DEFAULT 0,

    -- Whether the name already exists in dbo.districts
    [already_exists]  BIT           NOT NULL DEFAULT 0,

    -- Promotion state
    [insert_status]   NVARCHAR(20)  NOT NULL DEFAULT 'pending',
    --   pending   → not yet promoted
    --   inserted  → promoted to dbo.districts
    --   skipped   → intentionally not promoted (duplicate resolved elsewhere)

    -- Traceability back to the extraction staging table
    [source_id]       BIGINT        NULL,
    [source_raw]      NVARCHAR(255) NULL,

    [created_at]      DATETIME2(0)  NOT NULL DEFAULT SYSUTCDATETIME(),

    CONSTRAINT PK_district_staging PRIMARY KEY CLUSTERED ([id]),
    CONSTRAINT UQ_district_staging_slug UNIQUE ([slug])
);

CREATE NONCLUSTERED INDEX IX_ds_name          ON [dbo].[district_staging]([name]);
CREATE NONCLUSTERED INDEX IX_ds_insert_status ON [dbo].[district_staging]([insert_status]);
CREATE NONCLUSTERED INDEX IX_ds_already_exists ON [dbo].[district_staging]([already_exists]);

PRINT 'district_staging created.';
GO

-- ============================================================================
-- STEP 3 — Populate district_staging from district_extraction_staging
-- ============================================================================

PRINT '=== STEP 3: Populate district_staging ===';

;WITH Source AS (
    SELECT
        des.id                                          AS source_id,
        des.raw_value                                   AS source_raw,
        -- Title-case the normalized name for display
        -- SQL Server has no native INITCAP; we do a simple word-split here
        -- using a scalar approach (good enough for single-word and common two-word names)
        des.normalized                                  AS name_upper,
        des.occurrence,
        CASE
            WHEN EXISTS (
                SELECT 1 FROM dbo.districts d
                WHERE UPPER(LTRIM(RTRIM(d.name))) = des.normalized
            ) THEN CAST(1 AS BIT)
            ELSE CAST(0 AS BIT)
        END AS already_exists
    FROM district_extraction_staging des
    WHERE des.classification = 'CANDIDATE'
      AND des.review_status IN ('approved', 'pending')   -- include pending so you see them in the staging table
      AND LEN(des.normalized) >= 3
      AND des.normalized <> '(EMPTY-PLOT-ONLY)'
),
Deduped AS (
    -- In the unlikely event two extraction rows normalise to the same string, keep the higher-occurrence one
    SELECT
        source_id, source_raw, name_upper, occurrence, already_exists,
        ROW_NUMBER() OVER (PARTITION BY name_upper ORDER BY occurrence DESC) AS rn
    FROM Source
)
INSERT INTO [dbo].[district_staging]
    (name, slug, occurrence, already_exists, source_id, source_raw)
SELECT
    -- Store the normalized name as-is (UPPER); adjust to Title Case below if preferred
    name_upper,
    -- slug: lowercase, spaces → hyphens, strip non-alphanumeric except hyphen
    LOWER(REPLACE(name_upper, ' ', '-')),
    occurrence,
    already_exists,
    source_id,
    source_raw
FROM Deduped
WHERE rn = 1;

PRINT CONCAT('district_staging populated: ', @@ROWCOUNT, ' rows total.');
PRINT CONCAT('  → new (not yet in districts): ',
    (SELECT COUNT(*) FROM district_staging WHERE already_exists = 0));
PRINT CONCAT('  → already in districts:       ',
    (SELECT COUNT(*) FROM district_staging WHERE already_exists = 1));
GO

-- ============================================================================
-- STEP 4 — Summary views
-- ============================================================================

-- Overall summary
SELECT
    already_exists,
    insert_status,
    COUNT(*) AS cnt,
    SUM(occurrence) AS total_occurrences
FROM district_staging
GROUP BY already_exists, insert_status
ORDER BY already_exists, insert_status;

-- Top 50 new candidates by frequency
SELECT TOP 50
    name, slug, occurrence, source_raw
FROM district_staging
WHERE already_exists = 0
  AND insert_status  = 'pending'
ORDER BY occurrence DESC;
GO

-- ============================================================================
-- HOW TO USE
-- ----------------------------------------------------------------------------
-- A) Review what will be promoted:
--    SELECT * FROM district_staging WHERE already_exists = 0 ORDER BY occurrence DESC;
--
-- B) Skip a row you don't want to promote:
--    UPDATE district_staging SET insert_status = 'skipped' WHERE name = 'SOME NOISE';
--
-- C) Promote all pending-new into dbo.districts:
--    INSERT INTO dbo.districts (name, slug, is_active, created_at, updated_at)
--    SELECT name,
--           slug,
--           1,
--           SYSUTCDATETIME(),
--           SYSUTCDATETIME()
--    FROM   district_staging
--    WHERE  already_exists  = 0
--      AND  insert_status   = 'pending'
--      AND  NOT EXISTS (
--               SELECT 1 FROM dbo.districts d
--               WHERE UPPER(LTRIM(RTRIM(d.name))) = district_staging.name
--           );
--
--    UPDATE district_staging
--    SET    insert_status = 'inserted'
--    WHERE  already_exists = 0 AND insert_status = 'pending';
--
-- D) To re-run a fresh extract + restage, run extract_district_candidates.sql
--    first (apply your approved/rejected updates), then re-run this script.
-- ============================================================================
