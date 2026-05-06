-- SQL Script to add tracking_id column to file_indexings table
-- File: add_tracking_id_to_file_indexings.sql

USE [klas];

-- Check if tracking_id column exists
IF NOT EXISTS (
    SELECT 1 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'file_indexings' 
    AND COLUMN_NAME = 'tracking_id'
)
BEGIN
    -- Add tracking_id column
    ALTER TABLE [dbo].[file_indexings] 
    ADD tracking_id NVARCHAR(255) NULL;
    
    PRINT 'tracking_id column added successfully to file_indexings table';
END
ELSE
BEGIN
    PRINT 'tracking_id column already exists in file_indexings table';
END

-- Show the column info
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    CHARACTER_MAXIMUM_LENGTH,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'file_indexings' 
AND COLUMN_NAME = 'tracking_id';

-- Show first few columns of the table structure for verification
SELECT TOP 10
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    ORDINAL_POSITION
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'file_indexings' 
ORDER BY ORDINAL_POSITION;