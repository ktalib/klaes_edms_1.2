-- ============================================================================
-- create_related_file_number_table.sql                              (PRODUCTION)
-- ----------------------------------------------------------------------------
-- DROPS [related_file_number] if it exists and rebuilds it from five sources.
-- Safe to re-run — every execution starts from an empty table.
--
-- Five sources contributing rows:
--   source_table          related_fileno col   prop_id     file_title     party_2        location                       new_kangis (for comment)
--   file_indexings        related_fileno       prop_id     file_title     current_holder location                       new_kangis_file_no
--   deprecated_records    related_fileno       prop_id     file_title     current_holder location                       (none)
--   dciv_file_no          (intentionally excluded — see Step 5)
--   pra                   related_file_number  prop_id     NULL           party_2        COALESCE(prop_description,loc) NewKANGISFileno
--   pic                   related_file_number  prop_id     NULL           party_2        COALESCE(prop_description,loc) NewKANGISFileno
--   fileNumber            (intentionally excluded — see Step 4)
--
-- Each source row's related_fileno is split into one output row per value:
--   * JSON arrays  e.g. ["A","B","C"]       -> 3 rows
--   * Comma lists  e.g. "A, B, C"           -> 3 rows
--   * Plain value  e.g. "KN 36"             -> 1 row
-- Splitting is done via STRING_SPLIT after stripping JSON brackets/quotes,
-- which works uniformly for both stored formats.
--
-- Separator normalization: each split value has '_' and '/' replaced with '-'
-- so "RES_RC_1983_1147" / "CON/COM/95/12" come out canonical (RES-RC-1983-1147
-- / CON-COM-95-12).
--
-- Comment rules (evaluated top-down, first match wins):
--   1. Parent row has a non-empty New KANGIS FileNo
--      -> "KANGIS RECERTIFICATION <NewKANGIS>"
--   2. Parent file_number contains "-RC-" AND the split related_fileno is an
--      Old MLS KN file ("KN 36", "KN-218" etc. — KN followed by a digit)
--      -> "MINISTRY OF LAND AND PHYSICAL PLANNING RECERTIFICATION <file_number>"
--   3. Parent file_number contains "-RC-" (any other related_fileno)
--      -> "KANGIS RECERTIFICATION <parent file_number>"
--   4. The split related_fileno starts with KANGIS legacy prefix (KNML, MLKN, KNGP)
--      regardless of parent file_number format
--      -> "KANGIS RECERTIFICATION <parent file_number>"
--   5. Else NULL
--
-- Old MLS (KN) and KANGIS legacy prefixes
-- (from resources/views/components/global-fileno-modal.blade.php):
--   Old MLS:      KN <digit>...    (e.g. KN 36, KN-218)        -> MLPP
--   KANGIS legacy: KNML, MLKN, KNGP (e.g. KNML 7444, MLKN 1928) -> KANGIS
-- KANGIS is the default comment for -RC- parents — MLPP is reserved for the
-- specific case of Old MLS KN files under an RC parent.
--
-- Concurrency: WITH (NOLOCK) on every source scan + READ UNCOMMITTED isolation
-- to avoid deadlocking against active writers.
--
-- Target: SQL Server 2016+ (STRING_SPLIT, ISJSON).
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

-- ---------------------------------------------------------------------------
-- 1) Drop existing table (if any) and create a fresh empty one
-- ---------------------------------------------------------------------------
IF OBJECT_ID('dbo.related_file_number', 'U') IS NOT NULL
BEGIN
    DROP TABLE [dbo].[related_file_number];
    PRINT 'Existing [related_file_number] dropped.';
END

CREATE TABLE [dbo].[related_file_number] (
    [id]             BIGINT          IDENTITY(1,1) NOT NULL,
    [related_fileno] NVARCHAR(500)   NOT NULL,
    [prop_id]        NVARCHAR(100)   NULL,
    [source_table]   NVARCHAR(50)    NOT NULL,
    [source_id]      BIGINT          NOT NULL,
    [file_number]    NVARCHAR(255)   NULL,
    [file_title]     NVARCHAR(500)   NULL,
    [party_2]        NVARCHAR(500)   NULL,
    [location]       NVARCHAR(500)   NULL,
    [comment]        NVARCHAR(500)   NULL,
    [transaction_type] NVARCHAR(60)  NULL,
    [created_at]     DATETIME2(0)    NOT NULL,
    [updated_at]     DATETIME2(0)    NOT NULL,
    CONSTRAINT [PK_related_file_number] PRIMARY KEY CLUSTERED ([id] ASC)
);

CREATE NONCLUSTERED INDEX [IX_related_file_number_related_fileno]
    ON [dbo].[related_file_number] ([related_fileno]);

CREATE NONCLUSTERED INDEX [IX_related_file_number_prop_id]
    ON [dbo].[related_file_number] ([prop_id]);

CREATE NONCLUSTERED INDEX [IX_related_file_number_source]
    ON [dbo].[related_file_number] ([source_table], [source_id]);

PRINT 'Created fresh [related_file_number].';
GO

-- ---------------------------------------------------------------------------
-- 2) file_indexings
-- ---------------------------------------------------------------------------
INSERT INTO [dbo].[related_file_number]
    (related_fileno, prop_id, source_table, source_id, file_number, file_title, party_2, location, comment, created_at, updated_at)
SELECT
    LTRIM(RTRIM(REPLACE(REPLACE(j.[value], '_', '-'), '/', '-'))),
    CAST(s.prop_id AS NVARCHAR(100)),
    'file_indexings',
    s.id,
    s.file_number,
    s.file_title,
    s.current_holder,
    s.location,
    CASE
        WHEN s.new_kangis_file_no IS NOT NULL AND LTRIM(RTRIM(s.new_kangis_file_no)) <> ''
            THEN CONCAT('KANGIS RECERTIFICATION ', s.new_kangis_file_no)
        WHEN s.file_number LIKE '%-RC-%' AND
             (   UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KN [0-9]%'
              OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KN-[0-9]%')
            THEN CONCAT('MINISTRY OF LAND AND PHYSICAL PLANNING RECERTIFICATION ', s.file_number)
        WHEN s.file_number LIKE '%-RC-%'
            THEN CONCAT('KANGIS RECERTIFICATION ', s.file_number)
        WHEN UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNML%'
          OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'MLKN%'
          OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNGP%'
            THEN CONCAT('KANGIS RECERTIFICATION ', s.file_number)
        WHEN UPPER(s.file_number) LIKE 'KNML%'
          OR UPPER(s.file_number) LIKE 'MLKN%'
          OR UPPER(s.file_number) LIKE 'KNGP%'
            THEN CONCAT('KANGIS RECERTIFICATION ', s.file_number)
        ELSE NULL
    END,
    SYSUTCDATETIME(), SYSUTCDATETIME()
FROM [dbo].[file_indexings] s WITH (NOLOCK)
CROSS APPLY STRING_SPLIT(
    CASE WHEN ISJSON(s.related_fileno) = 1
         THEN REPLACE(REPLACE(REPLACE(CAST(s.related_fileno AS NVARCHAR(MAX)), '[', ''), ']', ''), '"', '')
         ELSE CAST(s.related_fileno AS NVARCHAR(MAX))
    END, ',') j
WHERE s.related_fileno IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500))))) > 0
  AND LTRIM(RTRIM(j.[value])) <> '';
PRINT CONCAT('file_indexings: inserted ', @@ROWCOUNT);
GO

-- ---------------------------------------------------------------------------
-- 3) deprecated_records
-- ---------------------------------------------------------------------------
INSERT INTO [dbo].[related_file_number]
    (related_fileno, prop_id, source_table, source_id, file_number, file_title, party_2, location, comment, created_at, updated_at)
SELECT
    LTRIM(RTRIM(REPLACE(REPLACE(j.[value], '_', '-'), '/', '-'))),
    CAST(s.prop_id AS NVARCHAR(100)),
    'deprecated_records',
    s.id,
    s.file_number,
    s.file_title,
    s.current_holder,
    s.location,
    CASE
        WHEN s.file_number LIKE '%-RC-%' AND
             (   UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KN [0-9]%'
              OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KN-[0-9]%')
            THEN CONCAT('MINISTRY OF LAND AND PHYSICAL PLANNING RECERTIFICATION ', s.file_number)
        WHEN s.file_number LIKE '%-RC-%'
            THEN CONCAT('KANGIS RECERTIFICATION ', s.file_number)
        WHEN UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNML%'
          OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'MLKN%'
          OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNGP%'
            THEN CONCAT('KANGIS RECERTIFICATION ', s.file_number)
        WHEN UPPER(s.file_number) LIKE 'KNML%'
          OR UPPER(s.file_number) LIKE 'MLKN%'
          OR UPPER(s.file_number) LIKE 'KNGP%'
            THEN CONCAT('KANGIS RECERTIFICATION ', s.file_number)
        ELSE NULL
    END,
    SYSUTCDATETIME(), SYSUTCDATETIME()
FROM [dbo].[deprecated_records] s WITH (NOLOCK)
CROSS APPLY STRING_SPLIT(
    CASE WHEN ISJSON(s.related_fileno) = 1
         THEN REPLACE(REPLACE(REPLACE(CAST(s.related_fileno AS NVARCHAR(MAX)), '[', ''), ']', ''), '"', '')
         ELSE CAST(s.related_fileno AS NVARCHAR(MAX))
    END, ',') j
WHERE s.related_fileno IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500))))) > 0
  AND LTRIM(RTRIM(j.[value])) <> '';
PRINT CONCAT('deprecated_records: inserted ', @@ROWCOUNT);
GO

-- ---------------------------------------------------------------------------
-- 4) fileNumber  -- INTENTIONALLY EXCLUDED
--    The fileNumber rows contributed ~2.4k entries with low signal
--    (0 prop_id, 22/2440 comments) and a high false-positive rate on the
--    KANGIS branch because parent identifiers are inconsistent. Leaving it
--    out for now; re-add if/when fileNumber data is cleaner.
-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------------------
-- 5) dciv_file_no — INTENTIONALLY EXCLUDED
--    DCIV file numbers (DCIV-YYYY-N, LPCC-YYYY-N) live in a separate domain
--    from the registry/MLS flow. Their related_filenos rarely resolve to
--    PropID_Master and they pollute the LS recertification view.
-- ---------------------------------------------------------------------------


-- ---------------------------------------------------------------------------
-- 6) pra  (note column: related_file_number, NewKANGISFileno lowercase 'no')
-- ---------------------------------------------------------------------------
INSERT INTO [dbo].[related_file_number]
    (related_fileno, prop_id, source_table, source_id, file_number, file_title, party_2, location, comment, created_at, updated_at)
SELECT
    LTRIM(RTRIM(REPLACE(REPLACE(j.[value], '_', '-'), '/', '-'))),
    CAST(s.prop_id AS NVARCHAR(100)),
    'pra',
    s.id,
    s.mlsFNo,
    NULL,
    s.party_2,
    COALESCE(s.property_description, s.location),
    CASE
        WHEN s.NewKANGISFileno IS NOT NULL AND LTRIM(RTRIM(s.NewKANGISFileno)) <> ''
            THEN CONCAT('KANGIS RECERTIFICATION ', s.NewKANGISFileno)
        WHEN s.mlsFNo LIKE '%-RC-%' AND
             (   UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KN [0-9]%'
              OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KN-[0-9]%')
            THEN CONCAT('MINISTRY OF LAND AND PHYSICAL PLANNING RECERTIFICATION ', s.mlsFNo)
        WHEN s.mlsFNo LIKE '%-RC-%'
            THEN CONCAT('KANGIS RECERTIFICATION ', s.mlsFNo)
        WHEN UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNML%'
          OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'MLKN%'
          OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNGP%'
            THEN CONCAT('KANGIS RECERTIFICATION ', s.mlsFNo)
        WHEN UPPER(s.mlsFNo) LIKE 'KNML%'
          OR UPPER(s.mlsFNo) LIKE 'MLKN%'
          OR UPPER(s.mlsFNo) LIKE 'KNGP%'
            THEN CONCAT('KANGIS RECERTIFICATION ', s.mlsFNo)
        ELSE NULL
    END,
    SYSUTCDATETIME(), SYSUTCDATETIME()
FROM [dbo].[pra] s WITH (NOLOCK)
CROSS APPLY STRING_SPLIT(
    CASE WHEN ISJSON(s.related_file_number) = 1
         THEN REPLACE(REPLACE(REPLACE(CAST(s.related_file_number AS NVARCHAR(MAX)), '[', ''), ']', ''), '"', '')
         ELSE CAST(s.related_file_number AS NVARCHAR(MAX))
    END, ',') j
WHERE s.related_file_number IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_file_number AS NVARCHAR(500))))) > 0
  AND LTRIM(RTRIM(j.[value])) <> '';
PRINT CONCAT('pra: inserted ', @@ROWCOUNT);
GO

-- ---------------------------------------------------------------------------
-- 7) pic
-- ---------------------------------------------------------------------------
INSERT INTO [dbo].[related_file_number]
    (related_fileno, prop_id, source_table, source_id, file_number, file_title, party_2, location, comment, created_at, updated_at)
SELECT
    LTRIM(RTRIM(REPLACE(REPLACE(j.[value], '_', '-'), '/', '-'))),
    CAST(s.prop_id AS NVARCHAR(100)),
    'pic',
    s.id,
    s.mlsFNo,
    NULL,
    s.party_2,
    COALESCE(s.property_description, s.location),
    CASE
        WHEN s.NewKANGISFileno IS NOT NULL AND LTRIM(RTRIM(s.NewKANGISFileno)) <> ''
            THEN CONCAT('KANGIS RECERTIFICATION ', s.NewKANGISFileno)
        WHEN s.mlsFNo LIKE '%-RC-%' AND
             (   UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KN [0-9]%'
              OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KN-[0-9]%')
            THEN CONCAT('MINISTRY OF LAND AND PHYSICAL PLANNING RECERTIFICATION ', s.mlsFNo)
        WHEN s.mlsFNo LIKE '%-RC-%'
            THEN CONCAT('KANGIS RECERTIFICATION ', s.mlsFNo)
        WHEN UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNML%'
          OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'MLKN%'
          OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNGP%'
            THEN CONCAT('KANGIS RECERTIFICATION ', s.mlsFNo)
        WHEN UPPER(s.mlsFNo) LIKE 'KNML%'
          OR UPPER(s.mlsFNo) LIKE 'MLKN%'
          OR UPPER(s.mlsFNo) LIKE 'KNGP%'
            THEN CONCAT('KANGIS RECERTIFICATION ', s.mlsFNo)
        ELSE NULL
    END,
    SYSUTCDATETIME(), SYSUTCDATETIME()
FROM [dbo].[pic] s WITH (NOLOCK)
CROSS APPLY STRING_SPLIT(
    CASE WHEN ISJSON(s.related_file_number) = 1
         THEN REPLACE(REPLACE(REPLACE(CAST(s.related_file_number AS NVARCHAR(MAX)), '[', ''), ']', ''), '"', '')
         ELSE CAST(s.related_file_number AS NVARCHAR(MAX))
    END, ',') j
WHERE s.related_file_number IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_file_number AS NVARCHAR(500))))) > 0
  AND LTRIM(RTRIM(j.[value])) <> '';
PRINT CONCAT('pic: inserted ', @@ROWCOUNT);
GO

-- ---------------------------------------------------------------------------
-- 8) Transaction type backfill (Recertification | Subdivision | Change of Purpose)
-- ---------------------------------------------------------------------------
UPDATE [dbo].[related_file_number]
SET    transaction_type = 'Ministry of Land & Physical Planning Recertification'
WHERE  comment LIKE 'MINISTRY OF LAND AND PHYSICAL%';

UPDATE [dbo].[related_file_number]
SET    transaction_type = 'KANGIS Recertification'
WHERE  comment LIKE 'KANGIS RECERTIFICATION%';

UPDATE [dbo].[related_file_number]
SET    transaction_type = 'KANGIS Recertification'
WHERE  transaction_type IS NULL
  AND  (UPPER(file_number) LIKE 'KNML%' OR UPPER(file_number) LIKE 'MLKN%' OR UPPER(file_number) LIKE 'KNGP%');

-- Merger: a single parent has > 1 distinct related_fileno (many → one)
;WITH MultiRfnPerParent AS (
    SELECT file_number
    FROM   [dbo].[related_file_number]
    WHERE  related_fileno IS NOT NULL AND LTRIM(RTRIM(related_fileno)) <> ''
       AND file_number    IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
    GROUP BY file_number
    HAVING COUNT(DISTINCT related_fileno) > 1
)
UPDATE r SET r.transaction_type = 'Merger'
FROM [dbo].[related_file_number] r
JOIN MultiRfnPerParent m ON m.file_number = r.file_number
WHERE r.transaction_type IS NULL;

UPDATE [dbo].[related_file_number]
SET    comment = CONCAT('MERGED INTO ', file_number)
WHERE  transaction_type = 'Merger' AND comment IS NULL;

-- Subdivision: a single related_fileno appears under > 1 distinct parent (one → many)
;WITH MultiParent AS (
    SELECT related_fileno
    FROM   [dbo].[related_file_number]
    WHERE  related_fileno IS NOT NULL AND LTRIM(RTRIM(related_fileno)) <> ''
       AND file_number    IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
    GROUP BY related_fileno
    HAVING COUNT(DISTINCT file_number) > 1
)
UPDATE r SET r.transaction_type = 'Subdivision'
FROM [dbo].[related_file_number] r
JOIN MultiParent m ON m.related_fileno = r.related_fileno
WHERE r.transaction_type IS NULL;

UPDATE [dbo].[related_file_number]
SET    comment = CONCAT('SUBDIVISION FROM ', file_number)
WHERE  transaction_type = 'Subdivision' AND comment IS NULL;

-- Change of Purpose — compares LAND USE (not raw prefix) so RES-RC- and
-- CON-RES- both resolve to "Residential" and aren't mis-flagged.
-- Additionally requires the related's title to match the parent's title:
-- a Change of Purpose only makes sense when the SAME owner is changing the
-- use of their property. If the titles differ, it's a sale/unrelated linkage.
UPDATE r
SET r.transaction_type = 'Change of Purpose'
FROM [dbo].[related_file_number] r
INNER JOIN [dbo].[file_indexings] fi_rel WITH (NOLOCK)
        ON fi_rel.file_number = r.related_fileno
       AND fi_rel.deleted_at IS NULL
CROSS APPLY (
    SELECT
        CASE
            WHEN UPPER(r.related_fileno) LIKE 'CON-RES-RC-%' OR UPPER(r.related_fileno) LIKE 'CON-RES-%' OR UPPER(r.related_fileno) LIKE 'RES-RC-%' OR UPPER(r.related_fileno) LIKE 'RES-%' THEN 'Residential'
            WHEN UPPER(r.related_fileno) LIKE 'CON-COM-RC-%' OR UPPER(r.related_fileno) LIKE 'CON-COM-%' OR UPPER(r.related_fileno) LIKE 'COM-RC-%' OR UPPER(r.related_fileno) LIKE 'COM-%' THEN 'Commercial'
            WHEN UPPER(r.related_fileno) LIKE 'CON-IND-RC-%' OR UPPER(r.related_fileno) LIKE 'CON-IND-%' OR UPPER(r.related_fileno) LIKE 'IND-RC-%' OR UPPER(r.related_fileno) LIKE 'IND-%' THEN 'Industrial'
            WHEN UPPER(r.related_fileno) LIKE 'CON-AG-RC-%'  OR UPPER(r.related_fileno) LIKE 'CON-AG-%'  OR UPPER(r.related_fileno) LIKE 'AG-RC-%'  OR UPPER(r.related_fileno) LIKE 'AG-%'  THEN 'Agriculture'
            ELSE NULL
        END AS related_use,
        CASE
            WHEN UPPER(r.file_number) LIKE 'CON-RES-RC-%' OR UPPER(r.file_number) LIKE 'CON-RES-%' OR UPPER(r.file_number) LIKE 'RES-RC-%' OR UPPER(r.file_number) LIKE 'RES-%' THEN 'Residential'
            WHEN UPPER(r.file_number) LIKE 'CON-COM-RC-%' OR UPPER(r.file_number) LIKE 'CON-COM-%' OR UPPER(r.file_number) LIKE 'COM-RC-%' OR UPPER(r.file_number) LIKE 'COM-%' THEN 'Commercial'
            WHEN UPPER(r.file_number) LIKE 'CON-IND-RC-%' OR UPPER(r.file_number) LIKE 'CON-IND-%' OR UPPER(r.file_number) LIKE 'IND-RC-%' OR UPPER(r.file_number) LIKE 'IND-%' THEN 'Industrial'
            WHEN UPPER(r.file_number) LIKE 'CON-AG-RC-%'  OR UPPER(r.file_number) LIKE 'CON-AG-%'  OR UPPER(r.file_number) LIKE 'AG-RC-%'  OR UPPER(r.file_number) LIKE 'AG-%'  THEN 'Agriculture'
            ELSE NULL
        END AS parent_use
) p
WHERE r.transaction_type IS NULL
  AND p.related_use IS NOT NULL
  AND p.parent_use  IS NOT NULL
  AND p.related_use <> p.parent_use
  -- Only flag Change of Purpose when the related title matches the parent's title.
  -- If titles differ, treat it as an unrelated linkage (e.g. sale/transfer under a different use).
  AND r.file_title IS NOT NULL AND LTRIM(RTRIM(r.file_title)) <> ''
  AND fi_rel.file_title IS NOT NULL AND LTRIM(RTRIM(fi_rel.file_title)) <> ''
  AND UPPER(LTRIM(RTRIM(r.file_title))) = UPPER(LTRIM(RTRIM(fi_rel.file_title)));


-- Change of Purpose comment text
UPDATE [dbo].[related_file_number]
SET    comment = CONCAT('CHANGE OF PURPOSE FROM ', file_number)
WHERE  transaction_type = 'Change of Purpose' AND comment IS NULL;

PRINT 'transaction_type backfill complete.';
GO

-- ---------------------------------------------------------------------------
-- 9) Summary
-- ---------------------------------------------------------------------------
SELECT
    source_table,
    COUNT(*)              AS total_rows,
    COUNT(prop_id)        AS with_prop_id,
    COUNT(comment)        AS with_comment,
    SUM(CASE WHEN comment LIKE 'KANGIS RECERTIFICATION%'        THEN 1 ELSE 0 END) AS kangis_comments,
    SUM(CASE WHEN comment LIKE 'MINISTRY OF LAND AND PHYSICAL%' THEN 1 ELSE 0 END) AS mlpp_comments
FROM [dbo].[related_file_number]
GROUP BY source_table
ORDER BY total_rows DESC;

SELECT COUNT(*) AS grand_total FROM [dbo].[related_file_number];
GO

-- ============================================================================
-- ROLLBACK (manual)
-- IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'related_file_number')
--     DROP TABLE [dbo].[related_file_number];
-- ============================================================================
