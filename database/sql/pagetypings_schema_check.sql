-- PageTyping Schema Check
-- Verifies pagetypings table and key columns for Booklet + BC+FC + serial suffix

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'pagetypings')
BEGIN
    PRINT 'pagetypings table is missing.';
    RETURN;
END

PRINT 'pagetypings table exists.';

SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'pagetypings'
  AND COLUMN_NAME IN (
    'serial_number',
    'serial_suffix',
    'booklet_id',
    'is_booklet_page',
    'booklet_sequence',
    'is_bcfc_page',
    'bcfc_sequence',
    'bcfc_id'
  )
ORDER BY COLUMN_NAME;

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_pagetypings_serial_number')
BEGIN
    PRINT 'Missing CK_pagetypings_serial_number constraint.';
END
ELSE
BEGIN
    PRINT 'CK_pagetypings_serial_number constraint exists.';
END

SELECT name AS index_name
FROM sys.indexes
WHERE object_id = OBJECT_ID('pagetypings')
  AND name IN (
    'IX_pagetypings_booklet_id',
    'IX_pagetypings_booklet_composite',
    'IX_pagetypings_bcfc_composite'
  );
