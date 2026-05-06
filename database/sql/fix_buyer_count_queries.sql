-- CORRECTED BUYER COUNT QUERIES FOR KLAES GIS EDMS
-- Run this script to verify and fix buyer count discrepancies

-- =====================================================
-- 1. VERIFY BUYER COUNT DISCREPANCY ISSUE
-- =====================================================

-- Check total records in both tables
PRINT 'Total records in buyer_list table:'
SELECT COUNT(*) AS total_buyer_records FROM buyer_list

PRINT 'Total records in st_unit_measurements table:'
SELECT COUNT(*) AS total_measurement_records FROM st_unit_measurements

-- Check for specific application (replace 1054 with your test application ID)
DECLARE @TestAppId INT = 1054

PRINT 'Buyer count for application ' + CAST(@TestAppId AS VARCHAR(10)) + ':'

-- OLD INCORRECT QUERY (shows the problem)
SELECT 
    'INCORRECT - Simple count from buyer_list' AS query_type,
    COUNT(*) AS count
FROM buyer_list 
WHERE application_id = @TestAppId

UNION ALL

-- OLD INCORRECT QUERY (shows the problem)
SELECT 
    'INCORRECT - Simple count from st_unit_measurements' AS query_type,
    COUNT(*) AS count
FROM st_unit_measurements 
WHERE application_id = @TestAppId

UNION ALL

-- CORRECTED QUERY - Use DISTINCT count with proper JOIN
SELECT 
    'CORRECT - Distinct buyer count with JOIN' AS query_type,
    COUNT(DISTINCT bl.id) AS count
FROM buyer_list bl
LEFT JOIN st_unit_measurements sum ON bl.id = sum.buyer_id 
    AND bl.application_id = sum.application_id
WHERE bl.application_id = @TestAppId

-- =====================================================
-- 2. DETAILED ANALYSIS OF BUYER RECORDS
-- =====================================================

PRINT 'Detailed analysis for application ' + CAST(@TestAppId AS VARCHAR(10)) + ':'

SELECT 
    bl.id,
    bl.buyer_title,
    bl.buyer_name,
    bl.unit_no,
    bl.land_use,
    bl.application_id,
    sum.id as measurement_id,
    sum.measurement,
    CASE 
        WHEN sum.id IS NOT NULL THEN 'Has Measurement'
        ELSE 'No Measurement'
    END as measurement_status
FROM buyer_list bl
LEFT JOIN st_unit_measurements sum ON bl.id = sum.buyer_id 
    AND bl.application_id = sum.application_id
WHERE bl.application_id = @TestAppId
ORDER BY bl.id

-- =====================================================
-- 3. CHECK FOR DUPLICATE BUYER RECORDS
-- =====================================================

PRINT 'Checking for duplicate buyer records:'

SELECT 
    application_id,
    unit_no,
    buyer_name,
    COUNT(*) as duplicate_count
FROM buyer_list
WHERE application_id = @TestAppId
GROUP BY application_id, unit_no, buyer_name
HAVING COUNT(*) > 1

-- =====================================================
-- 4. CHECK FOR ORPHANED MEASUREMENT RECORDS
-- =====================================================

PRINT 'Checking for orphaned measurement records:'

SELECT 
    sum.id,
    sum.application_id,
    sum.unit_no,
    sum.buyer_id,
    sum.measurement,
    'ORPHANED - No matching buyer' as status
FROM st_unit_measurements sum
LEFT JOIN buyer_list bl ON sum.buyer_id = bl.id 
    AND sum.application_id = bl.application_id
WHERE sum.application_id = @TestAppId
    AND bl.id IS NULL

-- =====================================================
-- 5. CORRECTED QUERIES FOR DASHBOARD USE
-- =====================================================

-- For viewrecorddetail.blade.php
-- CORRECT: Count distinct buyers for an application
SELECT COUNT(DISTINCT bl.id) as allocated_units
FROM buyer_list bl
LEFT JOIN st_unit_measurements sum ON bl.id = sum.buyer_id 
    AND bl.application_id = sum.application_id
WHERE bl.application_id = @TestAppId

-- For dashboard statistics
-- CORRECT: Count buyers by land use
SELECT 
    bl.land_use,
    COUNT(DISTINCT bl.id) as buyer_count
FROM buyer_list bl
LEFT JOIN st_unit_measurements sum ON bl.id = sum.buyer_id 
    AND bl.application_id = sum.application_id
WHERE bl.application_id = @TestAppId
GROUP BY bl.land_use

-- For sub application available buyers
-- CORRECT: Count available buyers not in subapplications
SELECT COUNT(DISTINCT bl.id) as available_buyers
FROM buyer_list bl
LEFT JOIN st_unit_measurements sum ON bl.id = sum.buyer_id 
    AND bl.application_id = sum.application_id
LEFT JOIN subapplications sub ON bl.unit_no = sub.unit_number 
    AND bl.application_id = sub.main_application_id
WHERE bl.application_id = @TestAppId
    AND sub.id IS NULL  -- Not used in any subapplication

PRINT 'Buyer count analysis complete!'
PRINT 'Use the CORRECT queries in your Laravel views and controllers.'