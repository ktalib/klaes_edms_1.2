-- Add missing columns to mother_applications table

-- Add tracking_id column if it doesn't exist
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_NAME = 'mother_applications' AND COLUMN_NAME = 'tracking_id')
BEGIN
    ALTER TABLE mother_applications
    ADD tracking_id NVARCHAR(255) NULL;
    PRINT 'Added tracking_id column';
END
ELSE
BEGIN
    PRINT 'tracking_id column already exists';
END
GO

-- Add primary_file_id column if it doesn't exist
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_NAME = 'mother_applications' AND COLUMN_NAME = 'primary_file_id')
BEGIN
    ALTER TABLE mother_applications
    ADD primary_file_id NVARCHAR(255) NULL;
    PRINT 'Added primary_file_id column';
END
ELSE
BEGIN
    PRINT 'primary_file_id column already exists';
END
GO

PRINT 'Column additions complete';
