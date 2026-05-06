-- ============================================================================
-- CHECK COLUMN DATA TYPES
-- Run this first to understand what types we're dealing with
-- ============================================================================

-- Check column data types for the problematic columns
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    DATA_TYPE,
    CHARACTER_MAXIMUM_LENGTH,
    IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE 
    (COLUMN_NAME = 'is_deleted' AND TABLE_NAME IN ('subapplications', 'mother_applications'))
    OR (COLUMN_NAME = 'active' AND TABLE_NAME = 'rofo')
ORDER BY TABLE_NAME, COLUMN_NAME;
