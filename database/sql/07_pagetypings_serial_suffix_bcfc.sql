-- Add serial suffix + BC+FC fields to pagetypings
-- Execute in SQL Server Management Studio

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

PRINT 'Added serial_suffix and BC+FC fields; updated serial_number constraint';
