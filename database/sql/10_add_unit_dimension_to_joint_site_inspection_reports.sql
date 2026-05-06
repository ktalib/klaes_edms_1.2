IF NOT EXISTS (
    SELECT 1
    FROM sys.columns
    WHERE object_id = OBJECT_ID(N'[dbo].[joint_site_inspection_reports]')
      AND name = 'unit_dimension'
)
BEGIN
    ALTER TABLE [dbo].[joint_site_inspection_reports]
        ADD [unit_dimension] NVARCHAR(255) NULL;
END
ELSE
BEGIN
    PRINT 'Column unit_dimension already exists on joint_site_inspection_reports';
END
