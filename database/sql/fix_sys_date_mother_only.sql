-- ===================================================================
-- FIXED: Add sys_date column - Mother Applications Only (Quick Fix)
-- This resolves the "Invalid column name 'sys_date'" error
-- ===================================================================

USE [klas];
GO

PRINT 'Adding sys_date column to mother_applications table...';

-- Step 1: Add the column as nullable first
ALTER TABLE [dbo].[mother_applications]
ADD [sys_date] DATETIME2(7) NULL;

PRINT '✅ Added sys_date column to mother_applications table';

-- Step 2: Update all existing records with calculated dates based on ID
UPDATE [dbo].[mother_applications] 
SET [sys_date] = DATEADD(SECOND, [id], '2024-01-01 00:00:00');

DECLARE @updatedCount INT = @@ROWCOUNT;
PRINT '✅ Updated ' + CAST(@updatedCount AS VARCHAR(10)) + ' records with calculated sys_date';

-- Step 3: Make the column NOT NULL
ALTER TABLE [dbo].[mother_applications]
ALTER COLUMN [sys_date] DATETIME2(7) NOT NULL;

PRINT '✅ Set sys_date column to NOT NULL';

-- Step 4: Add default constraint for future records
ALTER TABLE [dbo].[mother_applications]
ADD CONSTRAINT DF_mother_applications_sys_date DEFAULT GETDATE() FOR [sys_date];

PRINT '✅ Added GETDATE() default constraint for new records';

-- Verification: Show sample of records ordered by sys_date
PRINT '';
PRINT '📋 Verification - Top 5 records by sys_date (newest first):';
SELECT TOP 5 
    id, 
    fileno, 
    sys_date,
    FORMAT(sys_date, 'yyyy-MM-dd HH:mm:ss') as formatted_sys_date
FROM [dbo].[mother_applications] 
ORDER BY sys_date DESC;

PRINT '';
PRINT '✅ SUCCESS: sys_date column added and configured for mother_applications';
PRINT 'ℹ️ Now run the same process for subapplications table';

GO