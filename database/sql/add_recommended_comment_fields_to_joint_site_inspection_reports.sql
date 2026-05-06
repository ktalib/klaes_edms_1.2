-- Add recommended_comment_status and how_many_meters columns to joint_site_inspection_reports table
IF NOT EXISTS (
    SELECT *
    FROM sys.columns
    WHERE object_id = OBJECT_ID(N'[dbo].[joint_site_inspection_reports]')
      AND name = 'recommended_comment_status'
)
BEGIN
    ALTER TABLE [dbo].[joint_site_inspection_reports]
    ADD [recommended_comment_status] NVARCHAR(100) NULL;

    PRINT 'Added recommended_comment_status column to joint_site_inspection_reports table';
END
ELSE
BEGIN
    PRINT 'recommended_comment_status column already exists in joint_site_inspection_reports table';
END;

IF NOT EXISTS (
    SELECT *
    FROM sys.columns
    WHERE object_id = OBJECT_ID(N'[dbo].[joint_site_inspection_reports]')
      AND name = 'how_many_meters'
)
BEGIN
    ALTER TABLE [dbo].[joint_site_inspection_reports]
    ADD [how_many_meters] NVARCHAR(100) NULL;

    PRINT 'Added how_many_meters column to joint_site_inspection_reports table';
END
ELSE
BEGIN
    PRINT 'how_many_meters column already exists in joint_site_inspection_reports table';
END;
