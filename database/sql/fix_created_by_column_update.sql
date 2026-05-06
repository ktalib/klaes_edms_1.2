-- Update Script: Standardize created_by column to use names instead of IDs
-- Run this AFTER analyzing the data with the analysis script

-- STEP 1: Backup the current data (optional but recommended)
-- CREATE TABLE file_indexings_backup AS SELECT * FROM file_indexings;

-- STEP 2: Update numeric IDs to corresponding user names
UPDATE file_indexings 
SET created_by = COALESCE(u.first_name, u.name, 'Unknown User')
FROM file_indexings fi
INNER JOIN users u ON fi.created_by = CAST(u.id AS NVARCHAR(50))
WHERE ISNUMERIC(fi.created_by) = 1;

-- STEP 3: Handle common name variations (add more as needed based on your analysis)
-- Update any obvious variations to standardized names
UPDATE file_indexings SET created_by = 'Mansur' WHERE created_by IN ('mansur', 'MANSUR', 'Mansur ');
UPDATE file_indexings SET created_by = 'Admin' WHERE created_by IN ('admin', 'ADMIN', 'administrator');

-- STEP 4: Set NULL or empty values to a default
UPDATE file_indexings 
SET created_by = 'System' 
WHERE created_by IS NULL OR created_by = '' OR created_by = '0';

-- STEP 5: Verify the update
SELECT 
    created_by,
    COUNT(*) as record_count
FROM file_indexings 
GROUP BY created_by 
ORDER BY record_count DESC;

-- STEP 6: Optional - Change column type to NVARCHAR if it's currently INT
-- ALTER TABLE file_indexings ALTER COLUMN created_by NVARCHAR(100);