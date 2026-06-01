-- ============================================================================
-- diagnose_related_file_number.sql
-- ----------------------------------------------------------------------------
-- Read-only diagnostic queries. Compares row counts in the staging table
-- against actual related_fileno populations in the source tables, so we can
-- explain why prod ended up with fewer rows than the month-old local backup.
-- ============================================================================

SET NOCOUNT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

-- ---------------------------------------------------------------------------
-- 1) What's actually in related_file_number now, broken down by source
-- ---------------------------------------------------------------------------
PRINT '=== A) related_file_number rows per source ===';
SELECT
    source_table,
    COUNT(*) AS rows_copied,
    COUNT(prop_id) AS with_prop_id,
    COUNT(*) - COUNT(prop_id) AS without_prop_id
FROM [dbo].[related_file_number]
GROUP BY source_table
ORDER BY rows_copied DESC;

SELECT 'TOTAL' AS source_table, COUNT(*) AS rows_copied
FROM [dbo].[related_file_number];

-- ---------------------------------------------------------------------------
-- 2) Source table populations: how many rows actually have a non-empty
--    related_fileno right now?
-- ---------------------------------------------------------------------------
PRINT '=== B) Source tables - rows with non-empty related_fileno ===';
SELECT 'file_indexings' AS tbl, COUNT(*) AS rows_with_related_fileno
FROM [dbo].[file_indexings] WITH (NOLOCK)
WHERE related_fileno IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(related_fileno AS NVARCHAR(500))))) > 0
UNION ALL
SELECT 'deprecated_records', COUNT(*)
FROM [dbo].[deprecated_records] WITH (NOLOCK)
WHERE related_fileno IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(related_fileno AS NVARCHAR(500))))) > 0
UNION ALL
SELECT 'fileNumber', COUNT(*)
FROM [dbo].[fileNumber] WITH (NOLOCK)
WHERE related_fileno IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(related_fileno AS NVARCHAR(500))))) > 0
UNION ALL
SELECT 'dciv_file_no', COUNT(*)
FROM [dbo].[dciv_file_no] WITH (NOLOCK)
WHERE related_fileno IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(related_fileno AS NVARCHAR(500))))) > 0;

-- ---------------------------------------------------------------------------
-- 3) Gap analysis — rows that should have been copied but weren't
-- ---------------------------------------------------------------------------
PRINT '=== C) Rows with related_fileno NOT in staging (per source) ===';

SELECT 'file_indexings' AS tbl, COUNT(*) AS missing_from_staging
FROM [dbo].[file_indexings] s WITH (NOLOCK)
WHERE s.related_fileno IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500))))) > 0
  AND NOT EXISTS (
      SELECT 1 FROM [dbo].[related_file_number] t
      WHERE t.source_table = 'file_indexings' AND t.source_id = s.id
  )
UNION ALL
SELECT 'deprecated_records', COUNT(*)
FROM [dbo].[deprecated_records] s WITH (NOLOCK)
WHERE s.related_fileno IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500))))) > 0
  AND NOT EXISTS (
      SELECT 1 FROM [dbo].[related_file_number] t
      WHERE t.source_table = 'deprecated_records' AND t.source_id = s.id
  )
UNION ALL
SELECT 'fileNumber', COUNT(*)
FROM [dbo].[fileNumber] s WITH (NOLOCK)
WHERE s.related_fileno IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500))))) > 0
  AND NOT EXISTS (
      SELECT 1 FROM [dbo].[related_file_number] t
      WHERE t.source_table = 'fileNumber' AND t.source_id = s.id
  )
UNION ALL
SELECT 'dciv_file_no', COUNT(*)
FROM [dbo].[dciv_file_no] s WITH (NOLOCK)
WHERE s.related_fileno IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500))))) > 0
  AND NOT EXISTS (
      SELECT 1 FROM [dbo].[related_file_number] t
      WHERE t.source_table = 'dciv_file_no' AND t.source_id = s.id
  );

-- ---------------------------------------------------------------------------
-- 4) Sanity check — sample rows that exist in source but not in staging
-- ---------------------------------------------------------------------------
PRINT '=== D) Sample 5 file_indexings rows missing from staging ===';
SELECT TOP 5 s.id, s.file_number, s.related_fileno, s.prop_id
FROM [dbo].[file_indexings] s WITH (NOLOCK)
WHERE s.related_fileno IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500))))) > 0
  AND NOT EXISTS (
      SELECT 1 FROM [dbo].[related_file_number] t
      WHERE t.source_table = 'file_indexings' AND t.source_id = s.id
  )
ORDER BY s.id;

-- ---------------------------------------------------------------------------
-- 5) Are the source tables even there?
-- ---------------------------------------------------------------------------
PRINT '=== E) Source table existence check ===';
SELECT
    name AS table_name,
    create_date,
    modify_date
FROM sys.tables
WHERE name IN ('related_file_number','file_indexings','deprecated_records','fileNumber','dciv_file_no','pra','pic','instrument_capture')
ORDER BY name;

-- ---------------------------------------------------------------------------
-- 6) PRA: what columns does it actually have that could hold related fileno?
--    We didn't include pra in the original migration because local pra has no
--    related_fileno column. Check if prod has one.
-- ---------------------------------------------------------------------------
PRINT '=== F) PRA columns potentially holding related-fileno data ===';
SELECT
    COLUMN_NAME,
    DATA_TYPE,
    CHARACTER_MAXIMUM_LENGTH,
    IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'pra'
  AND (
        COLUMN_NAME LIKE '%related%'
     OR COLUMN_NAME LIKE '%rel_file%'
     OR COLUMN_NAME LIKE '%rfn%'
     OR COLUMN_NAME LIKE '%linked%'
     OR COLUMN_NAME LIKE '%associated%'
     OR COLUMN_NAME LIKE '%parent_fileno%'
     OR COLUMN_NAME LIKE '%mother_fileno%'
  )
ORDER BY COLUMN_NAME;

-- ---------------------------------------------------------------------------
-- 7) PRA: if related_fileno column exists, count how many rows have it
--    (the IF EXISTS pattern works in T-SQL via OBJECT_ID + COL_LENGTH check)
-- ---------------------------------------------------------------------------
PRINT '=== G) PRA related_fileno populations (if column exists) ===';
IF COL_LENGTH('dbo.pra', 'related_fileno') IS NOT NULL
BEGIN
    EXEC sp_executesql N'
        SELECT
            ''pra.related_fileno'' AS column_name,
            COUNT(*) AS total_pra_rows,
            SUM(CASE WHEN related_fileno IS NOT NULL AND LEN(LTRIM(RTRIM(CAST(related_fileno AS NVARCHAR(500))))) > 0 THEN 1 ELSE 0 END) AS rows_with_related_fileno
        FROM [dbo].[pra] WITH (NOLOCK)
    ';

    PRINT '  -> pra.related_fileno EXISTS. Sample 5 rows below:';
    EXEC sp_executesql N'
        SELECT TOP 5 id, mlsFNo, prop_id, related_fileno
        FROM [dbo].[pra] WITH (NOLOCK)
        WHERE related_fileno IS NOT NULL
          AND LEN(LTRIM(RTRIM(CAST(related_fileno AS NVARCHAR(500))))) > 0
        ORDER BY id
    ';
END
ELSE
BEGIN
    PRINT '  -> pra.related_fileno column does NOT exist on this DB.';
END

-- ---------------------------------------------------------------------------
-- 8) Same probe for pic and instrument_capture (the other PRA-side tables)
-- ---------------------------------------------------------------------------
PRINT '=== H) PIC / instrument_capture related-fileno columns ===';
SELECT
    TABLE_NAME, COLUMN_NAME, DATA_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME IN ('pic', 'instrument_capture')
  AND (
        COLUMN_NAME LIKE '%related%'
     OR COLUMN_NAME LIKE '%rfn%'
     OR COLUMN_NAME LIKE '%linked%'
  )
ORDER BY TABLE_NAME, COLUMN_NAME;
