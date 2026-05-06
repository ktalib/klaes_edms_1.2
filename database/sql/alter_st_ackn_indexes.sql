-- Adjust unique indexes on st_acknowledgement_tracking to support both primary and sub entries
-- Drop old unique index if it exists
IF EXISTS (
    SELECT 1 FROM sys.indexes WHERE name = 'UX_st_ackn_application_id' AND object_id = OBJECT_ID('dbo.st_acknowledgement_tracking')
)
BEGIN
    DROP INDEX UX_st_ackn_application_id ON dbo.st_acknowledgement_tracking;
END

-- Create filtered unique index for application_id (primary acknowledgements)
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes WHERE name = 'UX_st_ackn_application_id_not_null' AND object_id = OBJECT_ID('dbo.st_acknowledgement_tracking')
)
BEGIN
    CREATE UNIQUE INDEX UX_st_ackn_application_id_not_null
    ON dbo.st_acknowledgement_tracking(application_id)
    WHERE application_id IS NOT NULL;
END

-- Create filtered unique index for sub_application_id (sub acknowledgements)
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes WHERE name = 'UX_st_ackn_sub_application_id_not_null' AND object_id = OBJECT_ID('dbo.st_acknowledgement_tracking')
)
BEGIN
    CREATE UNIQUE INDEX UX_st_ackn_sub_application_id_not_null
    ON dbo.st_acknowledgement_tracking(sub_application_id)
    WHERE sub_application_id IS NOT NULL;
END
