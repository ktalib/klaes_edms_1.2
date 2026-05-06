-- ============================================================================
-- Deeds Bill Balance Schema Simplification
-- ============================================================================
-- Purpose: Remove unused columns from deeds_bill_balances_metadata table
--          and adjust constraints to match simplified form requirements.
--
-- Columns to DROP:
--   - status (replaced by billing table Payment_Status)
--   - notes (not used in print template)
--   - prepared_by (not required for certificate generation)
--
-- Columns to make NOT NULL:
--   - applicant_name (required)
--   - location_station (required)
--   - district (required)
--   - amount (required for rent calculations)
--
-- IMPORTANT: Backup your database before running this script!
-- ============================================================================

USE [klaes_db];
GO

-- Check if columns exist before dropping
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.deeds_bill_balances_metadata') AND name = 'status')
BEGIN
    ALTER TABLE dbo.deeds_bill_balances_metadata DROP COLUMN status;
    PRINT 'Dropped column: status';
END
ELSE
BEGIN
    PRINT 'Column status does not exist, skipping.';
END
GO

IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.deeds_bill_balances_metadata') AND name = 'notes')
BEGIN
    ALTER TABLE dbo.deeds_bill_balances_metadata DROP COLUMN notes;
    PRINT 'Dropped column: notes';
END
ELSE
BEGIN
    PRINT 'Column notes does not exist, skipping.';
END
GO

IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.deeds_bill_balances_metadata') AND name = 'prepared_by')
BEGIN
    ALTER TABLE dbo.deeds_bill_balances_metadata DROP COLUMN prepared_by;
    PRINT 'Dropped column: prepared_by';
END
ELSE
BEGIN
    PRINT 'Column prepared_by does not exist, skipping.';
END
GO

-- Update NULL values before making columns NOT NULL
UPDATE dbo.deeds_bill_balances_metadata 
SET applicant_name = 'UNKNOWN' 
WHERE applicant_name IS NULL;
PRINT 'Updated NULL applicant_name values.';
GO

UPDATE dbo.deeds_bill_balances_metadata 
SET location_station = 'UNKNOWN' 
WHERE location_station IS NULL;
PRINT 'Updated NULL location_station values.';
GO

UPDATE dbo.deeds_bill_balances_metadata 
SET district = 'UNKNOWN' 
WHERE district IS NULL;
PRINT 'Updated NULL district values.';
GO

UPDATE dbo.deeds_bill_balances_metadata 
SET amount = 0.00 
WHERE amount IS NULL;
PRINT 'Updated NULL amount values.';
GO

-- Alter columns to NOT NULL
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.deeds_bill_balances_metadata') AND name = 'applicant_name' AND is_nullable = 1)
BEGIN
    ALTER TABLE dbo.deeds_bill_balances_metadata 
    ALTER COLUMN applicant_name NVARCHAR(120) NOT NULL;
    PRINT 'Made applicant_name NOT NULL.';
END
ELSE
BEGIN
    PRINT 'Column applicant_name already NOT NULL or does not exist.';
END
GO

IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.deeds_bill_balances_metadata') AND name = 'location_station' AND is_nullable = 1)
BEGIN
    ALTER TABLE dbo.deeds_bill_balances_metadata 
    ALTER COLUMN location_station NVARCHAR(120) NOT NULL;
    PRINT 'Made location_station NOT NULL.';
END
ELSE
BEGIN
    PRINT 'Column location_station already NOT NULL or does not exist.';
END
GO

IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.deeds_bill_balances_metadata') AND name = 'district' AND is_nullable = 1)
BEGIN
    ALTER TABLE dbo.deeds_bill_balances_metadata 
    ALTER COLUMN district NVARCHAR(120) NOT NULL;
    PRINT 'Made district NOT NULL.';
END
ELSE
BEGIN
    PRINT 'Column district already NOT NULL or does not exist.';
END
GO

IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.deeds_bill_balances_metadata') AND name = 'amount' AND is_nullable = 1)
BEGIN
    ALTER TABLE dbo.deeds_bill_balances_metadata 
    ALTER COLUMN amount DECIMAL(15, 2) NOT NULL;
    PRINT 'Made amount NOT NULL.';
END
ELSE
BEGIN
    PRINT 'Column amount already NOT NULL or does not exist.';
END
GO

PRINT '============================================================================';
PRINT 'Schema simplification completed successfully.';
PRINT 'Final schema: reference, file_number, applicant_name*, applicant_address,';
PRINT '              location_station*, district*, amount*, prepared_at, billing_id';
PRINT '(* = NOT NULL)';
PRINT '============================================================================';
GO
