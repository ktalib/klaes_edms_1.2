-- Add sub_application_id to st_acknowledgement_tracking if missing (SQL Server)
IF COL_LENGTH('dbo.st_acknowledgement_tracking', 'sub_application_id') IS NULL
BEGIN
    ALTER TABLE dbo.st_acknowledgement_tracking
    ADD sub_application_id INT NULL;
END

-- Add index on sub_application_id for quick lookups
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes i
    WHERE i.[name] = 'IX_st_ackn_sub_application_id'
      AND i.[object_id] = OBJECT_ID('dbo.st_acknowledgement_tracking')
)
BEGIN
    CREATE INDEX IX_st_ackn_sub_application_id
        ON dbo.st_acknowledgement_tracking(sub_application_id);
END
