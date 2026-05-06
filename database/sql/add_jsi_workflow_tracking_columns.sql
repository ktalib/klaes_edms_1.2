-- Add workflow tracking columns to joint_site_inspection_reports table
USE [klas]
GO

-- Add is_generated column (tracks if JSI report has been generated for viewing)
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[joint_site_inspection_reports]') AND name = 'is_generated')
BEGIN
    ALTER TABLE [dbo].[joint_site_inspection_reports] 
    ADD [is_generated] BIT NOT NULL DEFAULT 0
    PRINT 'Added is_generated column to joint_site_inspection_reports table'
END
ELSE
BEGIN
    PRINT 'is_generated column already exists in joint_site_inspection_reports table'
END

-- Add is_submitted column (tracks if JSI report has been submitted for final approval)
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[joint_site_inspection_reports]') AND name = 'is_submitted')
BEGIN
    ALTER TABLE [dbo].[joint_site_inspection_reports] 
    ADD [is_submitted] BIT NOT NULL DEFAULT 0
    PRINT 'Added is_submitted column to joint_site_inspection_reports table'
END
ELSE
BEGIN
    PRINT 'is_submitted column already exists in joint_site_inspection_reports table'
END

-- Verify the new columns
SELECT TOP 5 
    id, 
    application_id,
    sub_application_id,
    is_generated,
    is_submitted,
    created_at,
    updated_at 
FROM [dbo].[joint_site_inspection_reports] 
ORDER BY id DESC

PRINT 'JSI workflow columns added successfully'