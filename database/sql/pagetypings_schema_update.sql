-- PageTyping Schema Update
-- Adds serial suffix + BC+FC columns and updates serial_number constraint

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'serial_suffix')
BEGIN
    ALTER TABLE pagetypings
    ADD serial_suffix NVARCHAR(5) NULL;
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'is_bcfc_page')
BEGIN
    ALTER TABLE pagetypings
    ADD is_bcfc_page BIT NOT NULL CONSTRAINT DF_pagetypings_is_bcfc_page DEFAULT(0);
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'bcfc_sequence')
BEGIN
    ALTER TABLE pagetypings
    ADD bcfc_sequence NVARCHAR(5) NULL;
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'bcfc_id')
BEGIN
    ALTER TABLE pagetypings
    ADD bcfc_id NVARCHAR(50) NULL;
END

-- Update serial_number constraint to allow 0 (BC+FC base)
IF EXISTS (SELECT * FROM sys.check_constraints WHERE name = 'CK_pagetypings_serial_number')
BEGIN
    ALTER TABLE [dbo].[pagetypings] DROP CONSTRAINT CK_pagetypings_serial_number;
END
GO

ALTER TABLE [dbo].[pagetypings]
ADD CONSTRAINT CK_pagetypings_serial_number CHECK ([serial_number] >= 0);
GO

-- Optional composite index for BC+FC
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_pagetypings_bcfc_composite')
BEGIN
    CREATE INDEX IX_pagetypings_bcfc_composite ON pagetypings(is_bcfc_page, bcfc_sequence);
END

PRINT 'pagetypings schema update completed.';
