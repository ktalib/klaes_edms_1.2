-- APPLICATION TYPE VERIFICATION QUERIES
-- Run these queries after testing each tab to verify data integrity

-- ============================================
-- 1. CHECK PRIMARY FILE NUMBERS
-- ============================================
-- Expected: All records should have application_type filled
SELECT TOP 10
    id,
    np_fileno AS 'NP File Number',
    fileno AS 'Final File Number',
    file_no_type AS 'Type',
    application_type AS 'Application Type',
    land_use AS 'Land Use',
    applicant_type AS 'Applicant Type',
    status AS 'Status',
    created_at AS 'Created',
    created_by AS 'Created By'
FROM st_file_numbers
WHERE file_no_type = 'PRIMARY'
ORDER BY created_at DESC;

-- Check for missing application_type
SELECT COUNT(*) AS 'PRIMARY Files Missing Application Type'
FROM st_file_numbers
WHERE file_no_type = 'PRIMARY' 
AND (application_type IS NULL OR application_type = '');

-- ============================================
-- 2. CHECK SUA FILE NUMBERS
-- ============================================
SELECT TOP 10
    id,
    np_fileno AS 'NP File Number',
    fileno AS 'Final File Number',
    file_no_type AS 'Type',
    application_type AS 'Application Type',
    land_use AS 'Land Use',
    applicant_type AS 'Applicant Type',
    status AS 'Status',
    created_at AS 'Created'
FROM st_file_numbers
WHERE file_no_type = 'SUA'
ORDER BY created_at DESC;

-- Check for missing application_type
SELECT COUNT(*) AS 'SUA Files Missing Application Type'
FROM st_file_numbers
WHERE file_no_type = 'SUA' 
AND (application_type IS NULL OR application_type = '');

-- ============================================
-- 3. CHECK PUA INHERITANCE (CRITICAL TEST)
-- ============================================
-- This verifies PuA units inherit application_type from parent
SELECT TOP 10
    pua.fileno AS 'Unit File Number',
    pua.application_type AS 'Unit App Type',
    pua.file_no_type AS 'Unit Type',
    pua.land_use AS 'Unit Land Use',
    pua.status AS 'Unit Status',
    parent.np_fileno AS 'Parent File Number',
    parent.application_type AS 'Parent App Type',
    parent.file_no_type AS 'Parent Type',
    CASE 
        WHEN pua.application_type = parent.application_type THEN '✓ MATCH'
        WHEN pua.application_type IS NULL THEN '✗ NULL'
        ELSE '✗ MISMATCH'
    END AS 'Inheritance Status',
    pua.created_at AS 'Created'
FROM st_file_numbers pua
LEFT JOIN st_file_numbers parent ON pua.parent_id = parent.id
WHERE pua.file_no_type = 'PUA'
ORDER BY pua.created_at DESC;

-- Count inheritance mismatches (should be 0)
SELECT COUNT(*) AS 'PUA Inheritance Failures'
FROM st_file_numbers pua
LEFT JOIN st_file_numbers parent ON pua.parent_id = parent.id
WHERE pua.file_no_type = 'PUA'
AND (pua.application_type != parent.application_type OR pua.application_type IS NULL);

-- ============================================
-- 4. APPLICATION TYPE DISTRIBUTION
-- ============================================
-- See which application types are most common
SELECT 
    file_no_type AS 'File Type',
    application_type AS 'Application Type',
    COUNT(*) AS 'Count',
    CAST(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER (PARTITION BY file_no_type) AS DECIMAL(5,2)) AS 'Percentage'
FROM st_file_numbers
WHERE application_type IS NOT NULL
GROUP BY file_no_type, application_type
ORDER BY file_no_type, COUNT(*) DESC;

-- ============================================
-- 5. RECENT TEST RECORDS (TODAY)
-- ============================================
-- Show all file numbers created today for testing
SELECT 
    np_fileno AS 'NP File Number',
    fileno AS 'Final File Number',
    file_no_type AS 'Type',
    application_type AS 'Application Type',
    land_use AS 'Land Use',
    applicant_type AS 'Applicant Type',
    status AS 'Status',
    FORMAT(created_at, 'yyyy-MM-dd HH:mm:ss') AS 'Created At',
    created_by AS 'Created By'
FROM st_file_numbers
WHERE CAST(created_at AS DATE) = CAST(GETDATE() AS DATE)
ORDER BY created_at DESC;

-- ============================================
-- 6. VALIDATION CHECK: ALLOWED VALUES
-- ============================================
-- Verify only allowed application types exist
SELECT 
    application_type AS 'Application Type',
    COUNT(*) AS 'Count',
    CASE 
        WHEN application_type IN ('Direct Allocation', 'Conversion') THEN '✓ VALID'
        WHEN application_type IS NULL THEN '⚠ NULL'
        ELSE '✗ INVALID'
    END AS 'Validation Status'
FROM st_file_numbers
GROUP BY application_type
ORDER BY COUNT(*) DESC;

-- Find invalid application types (should return 0 rows)
SELECT 
    id,
    np_fileno,
    application_type,
    file_no_type,
    created_at
FROM st_file_numbers
WHERE application_type NOT IN ('Direct Allocation', 'Conversion')
AND application_type IS NOT NULL
ORDER BY created_at DESC;

-- ============================================
-- 7. FULL LINEAGE CHECK (PRIMARY → PUA)
-- ============================================
-- Trace application_type through parent-child relationships
WITH FileHierarchy AS (
    SELECT 
        f.id,
        f.np_fileno,
        f.fileno,
        f.file_no_type,
        f.application_type,
        f.parent_id,
        CAST(NULL AS VARCHAR(100)) AS parent_np_fileno,
        CAST(NULL AS VARCHAR(100)) AS parent_application_type,
        1 AS level
    FROM st_file_numbers f
    WHERE f.file_no_type = 'PRIMARY'
    
    UNION ALL
    
    SELECT 
        child.id,
        child.np_fileno,
        child.fileno,
        child.file_no_type,
        child.application_type,
        child.parent_id,
        parent.np_fileno,
        parent.application_type,
        h.level + 1
    FROM st_file_numbers child
    INNER JOIN FileHierarchy h ON child.parent_id = h.id
    CROSS APPLY (
        SELECT np_fileno, application_type 
        FROM st_file_numbers 
        WHERE id = child.parent_id
    ) parent
)
SELECT TOP 20
    REPLICATE('  ', level - 1) + np_fileno AS 'File Hierarchy',
    file_no_type AS 'Type',
    application_type AS 'App Type',
    parent_np_fileno AS 'Parent File',
    parent_application_type AS 'Parent App Type',
    CASE 
        WHEN level = 1 THEN 'ROOT'
        WHEN application_type = parent_application_type THEN '✓ INHERITED'
        ELSE '✗ MISMATCH'
    END AS 'Status',
    level AS 'Level'
FROM FileHierarchy
ORDER BY level, np_fileno;

-- ============================================
-- 8. QUICK STATUS CHECK
-- ============================================
-- One-line summary of system status
SELECT 
    (SELECT COUNT(*) FROM st_file_numbers WHERE file_no_type = 'PRIMARY' AND application_type IS NOT NULL) AS 'PRIMARY (OK)',
    (SELECT COUNT(*) FROM st_file_numbers WHERE file_no_type = 'PRIMARY' AND application_type IS NULL) AS 'PRIMARY (Missing)',
    (SELECT COUNT(*) FROM st_file_numbers WHERE file_no_type = 'SUA' AND application_type IS NOT NULL) AS 'SUA (OK)',
    (SELECT COUNT(*) FROM st_file_numbers WHERE file_no_type = 'SUA' AND application_type IS NULL) AS 'SUA (Missing)',
    (SELECT COUNT(*) FROM st_file_numbers pua 
     LEFT JOIN st_file_numbers parent ON pua.parent_id = parent.id
     WHERE pua.file_no_type = 'PUA' AND pua.application_type = parent.application_type) AS 'PUA (Inherited)',
    (SELECT COUNT(*) FROM st_file_numbers pua 
     LEFT JOIN st_file_numbers parent ON pua.parent_id = parent.id
     WHERE pua.file_no_type = 'PUA' AND (pua.application_type != parent.application_type OR pua.application_type IS NULL)) AS 'PUA (Failed)';

-- ============================================
-- 9. SEARCH SPECIFIC FILE NUMBER
-- ============================================
-- Replace 'ST-COM-2025-1' with your test file number
DECLARE @SearchFileNo VARCHAR(100) = 'ST-COM-2025-1';

SELECT 
    np_fileno AS 'NP File Number',
    fileno AS 'Final File Number',
    file_no_type AS 'Type',
    application_type AS 'Application Type',
    land_use AS 'Land Use',
    applicant_type AS 'Applicant Type',
    applicant_title AS 'Title',
    applicant_first_name AS 'First Name',
    applicant_surname AS 'Surname',
    status AS 'Status',
    parent_id AS 'Parent ID',
    created_at AS 'Created',
    created_by AS 'Created By'
FROM st_file_numbers
WHERE np_fileno LIKE '%' + @SearchFileNo + '%'
OR fileno LIKE '%' + @SearchFileNo + '%'
ORDER BY created_at DESC;

-- ============================================
-- 10. MOTHER APPLICATIONS INTEGRATION CHECK
-- ============================================
-- Verify application_type syncs with mother_application_draft
SELECT TOP 5
    ma.id AS 'Draft ID',
    ma.npFileno AS 'Draft NP FileNo',
    ma.application_type AS 'Draft App Type',
    st.np_fileno AS 'ST NP FileNo',
    st.application_type AS 'ST App Type',
    CASE 
        WHEN ma.application_type = st.application_type THEN '✓ SYNCED'
        WHEN ma.application_type IS NULL OR st.application_type IS NULL THEN '⚠ NULL'
        ELSE '✗ MISMATCH'
    END AS 'Sync Status',
    ma.created_at AS 'Created'
FROM mother_application_draft ma
LEFT JOIN st_file_numbers st ON ma.npFileno = st.np_fileno
WHERE ma.application_type IS NOT NULL
ORDER BY ma.created_at DESC;

-- ============================================
-- EXPECTED RESULTS SUMMARY
-- ============================================
/*
✅ All PRIMARY records should have application_type = 'Direct Allocation' or 'Conversion'
✅ All SUA records should have application_type = 'Direct Allocation' or 'Conversion'
✅ All PUA records should have application_type matching their parent
✅ No NULL values for application_type in recent records
✅ No invalid values (only 'Direct Allocation' or 'Conversion' allowed)
✅ Inheritance check should show 0 failures
✅ Mother application drafts should sync with st_file_numbers

❌ If any query shows mismatches or missing values:
   1. Check JavaScript files were loaded (clear browser cache)
   2. Check backend validation rules in CommissionNewSTController.php
   3. Check Blade templates have application_type fields
   4. Review browser console for JavaScript errors
*/
