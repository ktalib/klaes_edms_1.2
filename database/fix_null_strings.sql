-- ============================================================================
-- SQL QUERIES TO CHECK AND FIX 'NULL' STRING VALUES
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. CHECK QUERIES - See how many records have the problem
-- ----------------------------------------------------------------------------

-- Check subapplications.is_deleted
SELECT 
    COUNT(*) as total_records,
    SUM(CASE WHEN is_deleted = 'NULL' THEN 1 ELSE 0 END) as null_string_count,
    SUM(CASE WHEN is_deleted IS NULL THEN 1 ELSE 0 END) as actual_null_count,
    SUM(CASE WHEN is_deleted = '0' THEN 1 ELSE 0 END) as zero_string_count,
    SUM(CASE WHEN is_deleted = '1' THEN 1 ELSE 0 END) as one_string_count
FROM dbo.subapplications;

-- Check mother_applications.is_deleted
SELECT 
    COUNT(*) as total_records,
    SUM(CASE WHEN is_deleted = 'NULL' THEN 1 ELSE 0 END) as null_string_count,
    SUM(CASE WHEN is_deleted IS NULL THEN 1 ELSE 0 END) as actual_null_count,
    SUM(CASE WHEN is_deleted = '0' THEN 1 ELSE 0 END) as zero_string_count,
    SUM(CASE WHEN is_deleted = '1' THEN 1 ELSE 0 END) as one_string_count
FROM dbo.mother_applications;

-- Check rofo.active (BIT type - can only be NULL, 0, or 1)
SELECT 
    COUNT(*) as total_records,
    SUM(CASE WHEN active IS NULL THEN 1 ELSE 0 END) as actual_null_count,
    SUM(CASE WHEN active = 0 THEN 1 ELSE 0 END) as zero_count,
    SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as one_count
FROM dbo.rofo;

-- View sample rofo records with NULL active
SELECT TOP 10 id, active, rofo_no
FROM dbo.rofo 
WHERE active IS NULL;


-- ----------------------------------------------------------------------------
-- 2. FIX QUERIES - Convert 'NULL' strings to actual NULL values
-- ----------------------------------------------------------------------------

-- IMPORTANT: Run these in a transaction so you can rollback if needed
BEGIN TRANSACTION;

-- Fix subapplications.is_deleted
UPDATE dbo.subapplications
SET is_deleted = NULL
WHERE is_deleted = 'NULL';

-- Fix mother_applications.is_deleted
UPDATE dbo.mother_applications
SET is_deleted = NULL
WHERE is_deleted = 'NULL';

-- Note: rofo.active is a BIT column - it doesn't need fixing
-- BIT columns can only contain NULL, 0, or 1 (not string values)

-- Check the results before committing
SELECT 'subapplications' as table_name, COUNT(*) as records_with_null 
FROM dbo.subapplications WHERE is_deleted IS NULL;

SELECT 'mother_applications' as table_name, COUNT(*) as records_with_null 
FROM dbo.mother_applications WHERE is_deleted IS NULL;

-- If everything looks good, commit the transaction
COMMIT TRANSACTION;

-- If something is wrong, rollback instead:
-- ROLLBACK TRANSACTION;


-- ----------------------------------------------------------------------------
-- 3. OPTIONAL: Convert '0' and '1' strings to integers (if columns should be int)
-- ----------------------------------------------------------------------------

-- ONLY RUN THIS if you want to convert the columns to proper int type
-- This is a more aggressive fix that changes the data type

/*
BEGIN TRANSACTION;

-- For subapplications.is_deleted
-- First, convert string values to integers
UPDATE dbo.subapplications SET is_deleted = '0' WHERE is_deleted = NULL;
UPDATE dbo.subapplications SET is_deleted = CAST(is_deleted AS int) WHERE is_deleted IN ('0', '1');

-- For mother_applications.is_deleted
UPDATE dbo.mother_applications SET is_deleted = '0' WHERE is_deleted = NULL;
UPDATE dbo.mother_applications SET is_deleted = CAST(is_deleted AS int) WHERE is_deleted IN ('0', '1');

-- For rofo.active
UPDATE dbo.rofo SET active = '0' WHERE active = NULL;
UPDATE dbo.rofo SET active = CAST(active AS int) WHERE active IN ('0', '1');

COMMIT TRANSACTION;
*/


-- ----------------------------------------------------------------------------
-- 4. VERIFY THE FIX
-- ----------------------------------------------------------------------------

-- After running the fix, verify no 'NULL' strings remain
SELECT COUNT(*) as remaining_null_strings
FROM dbo.subapplications
WHERE is_deleted = 'NULL';

SELECT COUNT(*) as remaining_null_strings
FROM dbo.mother_applications
WHERE is_deleted = 'NULL';

-- Both should return 0 if the fix was successful
