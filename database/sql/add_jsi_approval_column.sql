-- Add approval status column to joint_site_inspection_reports table
-- This column will track JSI approval status

USE [klas]
GO

-- Add is_approved column if it doesn't exist
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[joint_site_inspection_reports]') AND name = 'is_approved')
BEGIN
    ALTER TABLE [dbo].[joint_site_inspection_reports] 
    ADD [is_approved] BIT NOT NULL DEFAULT 0
    PRINT 'Added is_approved column to joint_site_inspection_reports table'
END
ELSE
BEGIN
    PRINT 'is_approved column already exists in joint_site_inspection_reports table'
END

-- Add approved_by column to track who approved the JSI
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[joint_site_inspection_reports]') AND name = 'approved_by')
BEGIN
    ALTER TABLE [dbo].[joint_site_inspection_reports] 
    ADD [approved_by] NVARCHAR(255) NULL
    PRINT 'Added approved_by column to joint_site_inspection_reports table'
END
ELSE
BEGIN
    PRINT 'approved_by column already exists in joint_site_inspection_reports table'
END

-- Add approved_at column to track when JSI was approved
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[joint_site_inspection_reports]') AND name = 'approved_at')
BEGIN
    ALTER TABLE [dbo].[joint_site_inspection_reports] 
    ADD [approved_at] DATETIME2 NULL
    PRINT 'Added approved_at column to joint_site_inspection_reports table'
END
ELSE
BEGIN
    PRINT 'approved_at column already exists in joint_site_inspection_reports table'
END

-- Display current structure
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'joint_site_inspection_reports' 
AND COLUMN_NAME IN ('is_generated', 'is_submitted', 'is_approved', 'approved_by', 'approved_at')
ORDER BY ORDINAL_POSITION