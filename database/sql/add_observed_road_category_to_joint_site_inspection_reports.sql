-- Add observed_road_category column to joint_site_inspection_reports table
IF NOT EXISTS (
    SELECT *
    FROM sys.columns
    WHERE object_id = OBJECT_ID(N'[dbo].[joint_site_inspection_reports]')
      AND name = 'observed_road_category'
)
BEGIN
    ALTER TABLE [dbo].[joint_site_inspection_reports]
    ADD [observed_road_category] NVARCHAR(255) NULL;

    PRINT 'Added observed_road_category column to joint_site_inspection_reports table';
END
ELSE
BEGIN
    PRINT 'observed_road_category column already exists in joint_site_inspection_reports table';
END;
