-- Add workflow status columns to joint_site_inspection_reports table
USE [your_database_name];
GO

-- Add is_generated column (BIT, DEFAULT 0)
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('joint_site_inspection_reports') AND name = 'is_generated')
BEGIN
    ALTER TABLE joint_site_inspection_reports 
    ADD is_generated BIT NOT NULL DEFAULT 0;
    PRINT 'Added is_generated column to joint_site_inspection_reports table';
END
ELSE
BEGIN
    PRINT 'Column is_generated already exists in joint_site_inspection_reports table';
END

-- Add is_submitted column (BIT, DEFAULT 0)
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('joint_site_inspection_reports') AND name = 'is_submitted')
BEGIN
    ALTER TABLE joint_site_inspection_reports 
    ADD is_submitted BIT NOT NULL DEFAULT 0;
    PRINT 'Added is_submitted column to joint_site_inspection_reports table';
END
ELSE
BEGIN
    PRINT 'Column is_submitted already exists in joint_site_inspection_reports table';
END

-- Verify the columns were added
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'joint_site_inspection_reports' 
    AND COLUMN_NAME IN ('is_generated', 'is_submitted')
ORDER BY COLUMN_NAME;

PRINT 'JSI workflow columns added successfully';
GO