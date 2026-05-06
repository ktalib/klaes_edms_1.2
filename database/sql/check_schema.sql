-- Quick diagnostic to check schema issues

-- Check mother_applications.id data type
SELECT 
    'mother_applications.id' as column_info,
    DATA_TYPE,
    CHARACTER_MAXIMUM_LENGTH,
    NUMERIC_PRECISION
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'mother_applications' AND COLUMN_NAME = 'id';

-- Check users table structure
SELECT 
    'users table columns' as table_info,
    COLUMN_NAME,
    DATA_TYPE
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'users'
ORDER BY ORDINAL_POSITION;
