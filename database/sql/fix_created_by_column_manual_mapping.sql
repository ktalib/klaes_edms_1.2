-- Manual Mapping Script: Handle specific cases that need manual review
-- Use this for names that don't have clear user matches

-- STEP 1: Check for partial matches in users table
SELECT 
    fi.created_by as file_indexing_name,
    COUNT(*) as record_count,
    u.first_name,
    u.last_name,
    u.email,
    u.id
FROM file_indexings fi
CROSS JOIN users u
WHERE ISNUMERIC(fi.created_by) = 0 
    AND fi.created_by IS NOT NULL
    AND (
        LOWER(fi.created_by) LIKE '%' + LOWER(u.first_name) + '%' OR
        LOWER(u.first_name) LIKE '%' + LOWER(fi.created_by) + '%' OR
        LOWER(fi.created_by) LIKE '%' + LOWER(u.last_name) + '%' OR
        LOWER(u.last_name) LIKE '%' + LOWER(fi.created_by) + '%'
    )
GROUP BY fi.created_by, u.first_name, u.last_name, u.email, u.id
ORDER BY fi.created_by, record_count DESC;

-- STEP 2: Manual updates based on your findings
-- Example mappings (update based on your actual data):

-- UPDATE file_indexings SET created_by = 'John Smith' WHERE created_by = 'john';
-- UPDATE file_indexings SET created_by = 'Jane Doe' WHERE created_by = 'jane.doe';
-- UPDATE file_indexings SET created_by = 'Mansur Ibrahim' WHERE created_by = 'Mansur';

-- STEP 3: Handle any remaining unmapped names
-- Set them to a standard format or leave as is
UPDATE file_indexings 
SET created_by = 'Legacy User (' + created_by + ')'
WHERE created_by NOT IN (
    SELECT DISTINCT COALESCE(first_name, name) 
    FROM users 
    WHERE COALESCE(first_name, name) IS NOT NULL
) 
AND ISNUMERIC(created_by) = 0 
AND created_by IS NOT NULL
AND created_by NOT LIKE 'Legacy User%';