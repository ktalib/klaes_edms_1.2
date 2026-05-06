-- ============================================================================
-- SIMPLE FIX - Run this query directly to fix the database
-- ============================================================================

-- Step 1: Check if is_deleted columns have 'NULL' string values
-- Run this first to see the problem:

SELECT 'subapplications' as [table], COUNT(*) as bad_records
FROM dbo.subapplications 
WHERE TRY_CAST(is_deleted AS int) IS NULL AND is_deleted IS NOT NULL;

SELECT 'mother_applications' as [table], COUNT(*) as bad_records
FROM dbo.mother_applications 
WHERE TRY_CAST(is_deleted AS int) IS NULL AND is_deleted IS NOT NULL;

-- Step 2: Fix the data by converting string 'NULL' to actual NULL
-- This is safe to run multiple times:

BEGIN TRANSACTION;

-- Fix subapplications
UPDATE dbo.subapplications
SET is_deleted = NULL
WHERE TRY_CAST(is_deleted AS int) IS NULL AND is_deleted IS NOT NULL;

-- Fix mother_applications  
UPDATE dbo.mother_applications
SET is_deleted = NULL
WHERE TRY_CAST(is_deleted AS int) IS NULL AND is_deleted IS NOT NULL;

-- Fix subapplications.main_application_id (if it has 'NULL' strings)
UPDATE dbo.subapplications
SET main_application_id = NULL
WHERE main_application_id = 'NULL';

-- Check how many were fixed
SELECT @@ROWCOUNT as rows_updated;

COMMIT TRANSACTION;

-- Step 3: Verify the fix worked
SELECT COUNT(*) as should_be_zero
FROM dbo.subapplications 
WHERE TRY_CAST(is_deleted AS int) IS NULL AND is_deleted IS NOT NULL;

SELECT COUNT(*) as should_be_zero
FROM dbo.mother_applications 
WHERE TRY_CAST(is_deleted AS int) IS NULL AND is_deleted IS NOT NULL;
