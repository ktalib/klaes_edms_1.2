-- Keep only source_instrument_capture_id on mls_file_no (SQL Server)
-- Run on the same database used by Laravel sqlsrv connection

IF COL_LENGTH('dbo.mls_file_no', 'source_instrument_capture_id') IS NULL
BEGIN
    ALTER TABLE dbo.mls_file_no ADD source_instrument_capture_id BIGINT NULL;
    PRINT 'Added: mls_file_no.source_instrument_capture_id';
END
GO

-- Remove extra columns previously added by mistake.
IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_mls_file_no_source_prop_id'
      AND object_id = OBJECT_ID('dbo.mls_file_no')
)
BEGIN
    DROP INDEX IX_mls_file_no_source_prop_id ON dbo.mls_file_no;
    PRINT 'Dropped: IX_mls_file_no_source_prop_id';
END
GO

IF COL_LENGTH('dbo.mls_file_no', 'require_op_source') IS NOT NULL
BEGIN
    DECLARE @df_name NVARCHAR(128);
    SELECT @df_name = dc.name
    FROM sys.default_constraints dc
    JOIN sys.columns c ON c.default_object_id = dc.object_id
    WHERE dc.parent_object_id = OBJECT_ID('dbo.mls_file_no')
      AND c.name = 'require_op_source';

    IF @df_name IS NOT NULL
    BEGIN
        EXEC('ALTER TABLE dbo.mls_file_no DROP CONSTRAINT ' + QUOTENAME(@df_name));
        PRINT 'Dropped default constraint for require_op_source';
    END
END
GO

IF COL_LENGTH('dbo.mls_file_no', 'source_prop_id') IS NOT NULL
BEGIN
    ALTER TABLE dbo.mls_file_no DROP COLUMN source_prop_id;
    PRINT 'Dropped: mls_file_no.source_prop_id';
END
GO

IF COL_LENGTH('dbo.mls_file_no', 'require_op_source') IS NOT NULL
BEGIN
    ALTER TABLE dbo.mls_file_no DROP COLUMN require_op_source;
    PRINT 'Dropped: mls_file_no.require_op_source';
END
GO

IF COL_LENGTH('dbo.mls_file_no', 'source_op_serial_number') IS NOT NULL
BEGIN
    ALTER TABLE dbo.mls_file_no DROP COLUMN source_op_serial_number;
    PRINT 'Dropped: mls_file_no.source_op_serial_number';
END
GO

IF COL_LENGTH('dbo.mls_file_no', 'source_registration_number') IS NOT NULL
BEGIN
    ALTER TABLE dbo.mls_file_no DROP COLUMN source_registration_number;
    PRINT 'Dropped: mls_file_no.source_registration_number';
END
GO

IF COL_LENGTH('dbo.mls_file_no', 'source_serial_no') IS NOT NULL
BEGIN
    ALTER TABLE dbo.mls_file_no DROP COLUMN source_serial_no;
    PRINT 'Dropped: mls_file_no.source_serial_no';
END
GO

IF COL_LENGTH('dbo.mls_file_no', 'source_page_no') IS NOT NULL
BEGIN
    ALTER TABLE dbo.mls_file_no DROP COLUMN source_page_no;
    PRINT 'Dropped: mls_file_no.source_page_no';
END
GO

IF COL_LENGTH('dbo.mls_file_no', 'source_volume_no') IS NOT NULL
BEGIN
    ALTER TABLE dbo.mls_file_no DROP COLUMN source_volume_no;
    PRINT 'Dropped: mls_file_no.source_volume_no';
END
GO

IF COL_LENGTH('dbo.mls_file_no', 'source_original_owner') IS NOT NULL
BEGIN
    ALTER TABLE dbo.mls_file_no DROP COLUMN source_original_owner;
    PRINT 'Dropped: mls_file_no.source_original_owner';
END
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_mls_file_no_source_instrument_capture_id'
      AND object_id = OBJECT_ID('dbo.mls_file_no')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_mls_file_no_source_instrument_capture_id
        ON dbo.mls_file_no(source_instrument_capture_id);
    PRINT 'Created: IX_mls_file_no_source_instrument_capture_id';
END
GO

-- Optional FK (uncomment if appropriate and if instrument_capture.id is trusted)
-- IF NOT EXISTS (
--     SELECT 1
--     FROM sys.foreign_keys
--     WHERE name = 'FK_mls_file_no_source_instrument_capture'
-- )
-- BEGIN
--     ALTER TABLE dbo.mls_file_no
--     ADD CONSTRAINT FK_mls_file_no_source_instrument_capture
--         FOREIGN KEY (source_instrument_capture_id)
--         REFERENCES dbo.instrument_capture(id);
-- END
-- GO

PRINT 'Done: mls_file_no now keeps only source_instrument_capture_id linkage.';
