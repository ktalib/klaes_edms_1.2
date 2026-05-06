-- Analysis Script: Focus on numeric IDs that need to be converted to names
-- Most records (~19k) already have string names, only ~30 have numeric IDs

-- 1. First, let's see the count breakdown
SELECT 
    CASE 
        WHEN ISNUMERIC(created_by) = 1 THEN 'Numeric (ID)'
        WHEN created_by IS NULL THEN 'NULL'
        ELSE 'Text (Name)'
    END as data_type,
    COUNT(*) as record_count
FROM file_indexings 
GROUP BY 
    CASE 
        WHEN ISNUMERIC(created_by) = 1 THEN 'Numeric (ID)'
        WHEN created_by IS NULL THEN 'NULL'
        ELSE 'Text (Name)'
    END
ORDER BY record_count DESC;

-- 2. Show only the numeric IDs that need conversion (should be ~30 records)
SELECT DISTINCT 
    fi.created_by as user_id,
    COUNT(*) as record_count
FROM file_indexings fi
WHERE ISNUMERIC(fi.created_by) = 1
GROUP BY fi.created_by
ORDER BY fi.created_by;

-- 3. Map these numeric IDs to actual user names
SELECT 
    fi.created_by as user_id,
    COUNT(*) as record_count,
    u.id as actual_user_id,
    u.first_name,
    u.last_name,
    u.email,
    COALESCE(u.first_name + ' ' + u.last_name, u.first_name, u.name, 'Unknown User') as full_name
FROM file_indexings fi
LEFT JOIN users u ON fi.created_by = CAST(u.id AS NVARCHAR(50))
WHERE ISNUMERIC(fi.created_by) = 1
GROUP BY fi.created_by, u.id, u.first_name, u.last_name, u.email, u.name
ORDER BY CAST(fi.created_by AS INT);