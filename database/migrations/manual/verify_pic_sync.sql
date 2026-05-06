-- Verification Script for PIC Table Schema Sync
-- Run this to confirm all columns were added successfully

USE klaes;
GO

PRINT '===================================================';
PRINT 'PIC Table Schema Verification';
PRINT '===================================================';
PRINT '';

-- Get total column count
DECLARE @total_columns INT;
SELECT @total_columns = COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic';
PRINT 'Total PIC columns: ' + CAST(@total_columns AS VARCHAR);
PRINT '';

-- Check for key new columns
PRINT 'Verifying key new columns:';
PRINT '---------------------------------------------------';

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'party_1')
    PRINT '✓ party_1 exists'
ELSE
    PRINT '✗ party_1 MISSING';

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'deeds_date')
    PRINT '✓ deeds_date exists'
ELSE
    PRINT '✗ deeds_date MISSING';

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'title_type')
    PRINT '✓ title_type exists'
ELSE
    PRINT '✗ title_type MISSING';

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'Vendor')
    PRINT '✓ Vendor exists'
ELSE
    PRINT '✗ Vendor MISSING';

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'is_caveated')
    PRINT '✓ is_caveated exists'
ELSE
    PRINT '✗ is_caveated MISSING';

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'updated_by')
    PRINT '✓ updated_by exists'
ELSE
    PRINT '✗ updated_by MISSING';

PRINT '';
PRINT '===================================================';
PRINT 'Verification Complete!';
PRINT '===================================================';
GO

-- Show all columns for manual review
PRINT '';
PRINT 'All PIC Table Columns:';
PRINT '---------------------------------------------------';
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    CHARACTER_MAXIMUM_LENGTH,
    IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'pic' 
ORDER BY ORDINAL_POSITION;
GO
