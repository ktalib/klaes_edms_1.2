/*
Fix for SQLSTATE[22001] truncation error in file_history_staging.Releasor column
Error: String or binary data would be truncated when inserting long bank names
*/

USE [klaes]
GO

-- Check current column definition
SELECT 
    c.COLUMN_NAME,
    c.DATA_TYPE,
    c.CHARACTER_MAXIMUM_LENGTH,
    c.IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS c
WHERE c.TABLE_NAME = 'file_history_staging' 
    AND c.COLUMN_NAME = 'Releasor';

-- Alter Releasor column to accommodate longer bank names
-- Increasing from current size to NVARCHAR(500) to handle long institutional names
ALTER TABLE dbo.file_history_staging 
ALTER COLUMN Releasor NVARCHAR(500) NULL;

-- Also check and fix Releasee column in case it has similar issues
SELECT 
    c.COLUMN_NAME,
    c.DATA_TYPE,
    c.CHARACTER_MAXIMUM_LENGTH,
    c.IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS c
WHERE c.TABLE_NAME = 'file_history_staging' 
    AND c.COLUMN_NAME = 'Releasee';

-- Alter Releasee column as well for consistency
ALTER TABLE dbo.file_history_staging 
ALTER COLUMN Releasee NVARCHAR(500) NULL;

-- Verify the changes
SELECT 
    c.COLUMN_NAME,
    c.DATA_TYPE,
    c.CHARACTER_MAXIMUM_LENGTH,
    c.IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS c
WHERE c.TABLE_NAME = 'file_history_staging' 
    AND c.COLUMN_NAME IN ('Releasor', 'Releasee')
ORDER BY c.COLUMN_NAME;

PRINT 'Column sizes updated successfully for file_history_staging.Releasor and Releasee';