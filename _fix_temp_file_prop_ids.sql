-- ============================================================================
-- KLAES Temporary File Number Property ID Fix
-- Fix mismatches where temporary file (T) prop_ids don't match main file prop_ids
-- ============================================================================

-- Step 1: Review what will be fixed (Run this first to verify)
WITH FilePropIds AS (
    SELECT UPPER(LTRIM(RTRIM(file_number))) AS fileno, prop_id, 'file_indexings' AS source_table, id AS record_id
    FROM file_indexings WHERE file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo))) AS fileno, prop_id, 'file_history_staging' AS source_table, id AS record_id
    FROM file_history_staging WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo))) AS fileno, prop_id, 'pra' AS source_table, id AS record_id
    FROM pra WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(fileno))) AS fileno, prop_id, 'deed_registrations' AS source_table, id AS record_id
    FROM deed_registrations WHERE fileno IS NOT NULL AND LTRIM(RTRIM(fileno)) <> ''
),
TempFiles AS (
    SELECT fileno AS temp_fileno, RTRIM(REPLACE(fileno, '(T)', '')) AS base_fileno,
    prop_id AS temp_prop_id, source_table AS temp_source, record_id AS temp_id
    FROM FilePropIds WHERE fileno LIKE '%(T)'
),
MainFiles AS (
    SELECT fileno AS main_fileno, prop_id AS main_prop_id, source_table AS main_source
    FROM FilePropIds WHERE fileno NOT LIKE '%(T)'
)
SELECT DISTINCT
    t.temp_fileno,
    t.temp_prop_id,
    t.temp_source,
    m.main_fileno,
    m.main_prop_id,
    m.main_source,
    'WILL UPDATE TO: ' + CAST(m.main_prop_id AS VARCHAR(20)) AS action
FROM TempFiles t
JOIN MainFiles m ON t.base_fileno = m.main_fileno
WHERE (t.temp_prop_id <> m.main_prop_id 
       OR (t.temp_prop_id IS NULL AND m.main_prop_id IS NOT NULL)
       OR (t.temp_prop_id IS NOT NULL AND m.main_prop_id IS NULL))
  AND (t.temp_source = 'deed_registrations' OR m.main_source = 'deed_registrations')
ORDER BY t.temp_fileno;
GO

GO

-- ============================================================================
-- Step 2: ACTUAL FIX - Run after verifying Step 1
-- Update deed_registrations temporary records to match main file prop_ids
-- ============================================================================
WITH FilePropIds AS (
    SELECT UPPER(LTRIM(RTRIM(file_number))) AS fileno, prop_id, 'file_indexings' AS source_table, id AS record_id
    FROM file_indexings WHERE file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo))) AS fileno, prop_id, 'file_history_staging' AS source_table, id AS record_id
    FROM file_history_staging WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo))) AS fileno, prop_id, 'pra' AS source_table, id AS record_id
    FROM pra WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(fileno))) AS fileno, prop_id, 'deed_registrations' AS source_table, id AS record_id
    FROM deed_registrations WHERE fileno IS NOT NULL AND LTRIM(RTRIM(fileno)) <> ''
),
TempFiles AS (
    SELECT fileno AS temp_fileno, RTRIM(REPLACE(fileno, '(T)', '')) AS base_fileno,
    prop_id AS temp_prop_id, source_table AS temp_source, record_id AS temp_id
    FROM FilePropIds WHERE fileno LIKE '%(T)'
),
MainFiles AS (
    SELECT fileno AS main_fileno, prop_id AS main_prop_id, source_table AS main_source
    FROM FilePropIds WHERE fileno NOT LIKE '%(T)'
),
ToFix AS (
    SELECT t.temp_id, t.temp_fileno, m.main_prop_id
    FROM TempFiles t
    JOIN MainFiles m ON t.base_fileno = m.main_fileno
    WHERE (t.temp_prop_id <> m.main_prop_id 
           OR (t.temp_prop_id IS NULL AND m.main_prop_id IS NOT NULL)
           OR (t.temp_prop_id IS NOT NULL AND m.main_prop_id IS NULL))
      AND t.temp_source = 'deed_registrations'
)
UPDATE dr
SET dr.prop_id = tf.main_prop_id
FROM deed_registrations dr
INNER JOIN ToFix tf ON dr.id = tf.temp_id
WHERE dr.fileno LIKE '%(T)';

-- Log the transaction
PRINT 'Temporary file number prop_id fix complete. ' + 
      CAST(@@ROWCOUNT AS VARCHAR) + ' records updated.';
GO

GO

-- ============================================================================
-- Step 3: Verification - Run after fix to confirm
-- ============================================================================
WITH FilePropIds AS (
    SELECT UPPER(LTRIM(RTRIM(file_number))) AS fileno, prop_id, 'file_indexings' AS source_table, id AS record_id
    FROM file_indexings WHERE file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo))) AS fileno, prop_id, 'file_history_staging' AS source_table, id AS record_id
    FROM file_history_staging WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo))) AS fileno, prop_id, 'pra' AS source_table, id AS record_id
    FROM pra WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(fileno))) AS fileno, prop_id, 'deed_registrations' AS source_table, id AS record_id
    FROM deed_registrations WHERE fileno IS NOT NULL AND LTRIM(RTRIM(fileno)) <> ''
),
TempFiles AS (
    SELECT fileno AS temp_fileno, RTRIM(REPLACE(fileno, '(T)', '')) AS base_fileno,
    prop_id AS temp_prop_id, source_table AS temp_source, record_id AS temp_id
    FROM FilePropIds WHERE fileno LIKE '%(T)'
),
MainFiles AS (
    SELECT fileno AS main_fileno, prop_id AS main_prop_id, source_table AS main_source
    FROM FilePropIds WHERE fileno NOT LIKE '%(T)'
)
SELECT 
    'AFTER FIX VERIFICATION' AS verification_stage,
    t.temp_fileno,
    t.temp_prop_id AS [Temp Prop ID (Should Match Main)],
    m.main_fileno,
    m.main_prop_id AS [Main Prop ID],
    CASE 
        WHEN t.temp_prop_id = m.main_prop_id THEN '✓ FIXED'
        ELSE '✗ STILL MISMATCHED'
    END AS status
FROM TempFiles t
JOIN MainFiles m ON t.base_fileno = m.main_fileno
WHERE (t.temp_source = 'deed_registrations' OR m.main_source = 'deed_registrations')
ORDER BY t.temp_fileno;
GO
