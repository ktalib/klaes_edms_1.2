-- File Number Reservation System - Testing SQL Script
-- Run these queries to verify the system is working correctly

-- ============================================================================
-- PART 1: INITIAL SETUP VERIFICATION
-- ============================================================================

PRINT 'Part 1: Verifying table structure...';
GO

-- Check if table exists
IF OBJECT_ID('file_number_reservations', 'U') IS NOT NULL
    PRINT '✓ Table file_number_reservations exists'
ELSE
    PRINT '✗ ERROR: Table file_number_reservations NOT found!'
GO

-- Check table structure
SELECT 
    COLUMN_NAME, 
    DATA_TYPE, 
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'file_number_reservations'
ORDER BY ORDINAL_POSITION;
GO

-- Check indexes
SELECT 
    i.name as index_name,
    STRING_AGG(c.name, ', ') as columns,
    i.is_unique
FROM sys.indexes i
INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
INNER JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
WHERE i.object_id = OBJECT_ID('file_number_reservations')
GROUP BY i.name, i.is_unique
ORDER BY i.name;
GO

-- Check foreign keys
SELECT 
    fk.name AS foreign_key_name,
    OBJECT_NAME(fk.parent_object_id) AS table_name,
    COL_NAME(fkc.parent_object_id, fkc.parent_column_id) AS column_name,
    OBJECT_NAME(fk.referenced_object_id) AS referenced_table,
    COL_NAME(fkc.referenced_object_id, fkc.referenced_column_id) AS referenced_column
FROM sys.foreign_keys fk
INNER JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
WHERE fk.parent_object_id = OBJECT_ID('file_number_reservations');
GO

-- ============================================================================
-- PART 2: CREATE TEST DATA
-- ============================================================================

PRINT '';
PRINT 'Part 2: Creating test reservations...';
GO

-- Note: Replace user_id (1) with a valid user ID from your users table
DECLARE @user_id INT = 1;
DECLARE @draft_uuid UNIQUEIDENTIFIER = NEWID();

-- Test 1: Active reservation
INSERT INTO file_number_reservations (
    file_number, land_use_type, serial_number, year, status,
    reserved_at, expires_at, reserved_by, client_ip, user_agent,
    created_at, updated_at
) VALUES (
    'NPFN-2025-TEST-00001', 'RESIDENTIAL', 99901, 2025, 'reserved',
    GETDATE(), DATEADD(day, 3, GETDATE()), @user_id, '127.0.0.1', 'Test Script',
    GETDATE(), GETDATE()
);

-- Test 2: Used reservation
INSERT INTO file_number_reservations (
    file_number, land_use_type, serial_number, year, status,
    reserved_at, expires_at, used_at, reserved_by, client_ip, user_agent,
    created_at, updated_at
) VALUES (
    'NPFN-2025-TEST-00002', 'COMMERCIAL', 99902, 2025, 'used',
    DATEADD(day, -5, GETDATE()), DATEADD(day, -2, GETDATE()), DATEADD(day, -3, GETDATE()),
    @user_id, '127.0.0.1', 'Test Script',
    GETDATE(), GETDATE()
);

-- Test 3: Released reservation
INSERT INTO file_number_reservations (
    file_number, land_use_type, serial_number, year, status,
    reserved_at, expires_at, released_at, reserved_by, client_ip, user_agent,
    created_at, updated_at
) VALUES (
    'NPFN-2025-TEST-00003', 'INDUSTRIAL', 99903, 2025, 'released',
    DATEADD(day, -2, GETDATE()), DATEADD(day, 1, GETDATE()), DATEADD(day, -1, GETDATE()),
    @user_id, '127.0.0.1', 'Test Script',
    GETDATE(), GETDATE()
);

-- Test 4: Expired reservation (for cleanup testing)
INSERT INTO file_number_reservations (
    file_number, land_use_type, serial_number, year, status,
    reserved_at, expires_at, reserved_by, client_ip, user_agent,
    created_at, updated_at
) VALUES (
    'NPFN-2025-TEST-00004', 'MIXED', 99904, 2025, 'reserved',
    DATEADD(day, -5, GETDATE()), DATEADD(day, -2, GETDATE()),
    @user_id, '127.0.0.1', 'Test Script',
    GETDATE(), GETDATE()
);

PRINT '✓ Test data created (4 reservations)';
GO

-- ============================================================================
-- PART 3: BASIC QUERIES
-- ============================================================================

PRINT '';
PRINT 'Part 3: Running basic queries...';
GO

-- Total count
PRINT 'Total reservations:';
SELECT COUNT(*) as total FROM file_number_reservations;
GO

-- Count by status
PRINT 'Count by status:';
SELECT status, COUNT(*) as count
FROM file_number_reservations
GROUP BY status
ORDER BY status;
GO

-- Recent reservations
PRINT 'Recent reservations (last 10):';
SELECT TOP 10 
    file_number, 
    land_use_type, 
    status, 
    reserved_at,
    expires_at
FROM file_number_reservations
ORDER BY reserved_at DESC;
GO

-- ============================================================================
-- PART 4: VALIDATION QUERIES
-- ============================================================================

PRINT '';
PRINT 'Part 4: Running validation queries...';
GO

-- Check for duplicates (should be 0)
PRINT 'Duplicate file numbers (should be 0):';
SELECT file_number, COUNT(*) as count
FROM file_number_reservations
GROUP BY file_number
HAVING COUNT(*) > 1;
GO

-- Check for expired reservations
PRINT 'Expired reservations needing cleanup:';
SELECT file_number, reserved_at, expires_at, DATEDIFF(day, expires_at, GETDATE()) as days_expired
FROM file_number_reservations
WHERE status = 'reserved' AND expires_at < GETDATE()
ORDER BY expires_at;
GO

-- Check for expiring soon (< 24 hours)
PRINT 'Reservations expiring soon (< 24 hours):';
SELECT file_number, reserved_at, expires_at, DATEDIFF(hour, GETDATE(), expires_at) as hours_remaining
FROM file_number_reservations
WHERE status = 'reserved' AND expires_at < DATEADD(hour, 24, GETDATE()) AND expires_at >= GETDATE()
ORDER BY expires_at;
GO

-- ============================================================================
-- PART 5: SERIAL NUMBER ALIGNMENT
-- ============================================================================

PRINT '';
PRINT 'Part 5: Checking serial number alignment...';
GO

-- Compare land_use_serials with actual reservations
SELECT 
    lus.land_use_type,
    lus.current_serial as table_serial,
    ISNULL(MAX(fnr.serial_number), 0) as max_reserved_serial,
    CASE 
        WHEN lus.current_serial >= ISNULL(MAX(fnr.serial_number), 0) THEN '✓ Aligned'
        ELSE '✗ MISALIGNED'
    END as status
FROM land_use_serials lus
LEFT JOIN file_number_reservations fnr ON 
    fnr.land_use_type = lus.land_use_type AND 
    fnr.year = lus.year
WHERE lus.year = 2025
GROUP BY lus.land_use_type, lus.current_serial
ORDER BY lus.land_use_type;
GO

-- ============================================================================
-- PART 6: ACTIVITY REPORT
-- ============================================================================

PRINT '';
PRINT 'Part 6: Activity report...';
GO

-- Reservations by land use and status
SELECT 
    land_use_type,
    status,
    COUNT(*) as count,
    MIN(reserved_at) as oldest,
    MAX(reserved_at) as newest
FROM file_number_reservations
GROUP BY land_use_type, status
ORDER BY land_use_type, status;
GO

-- Daily activity (last 7 days)
SELECT 
    CAST(reserved_at AS DATE) as date,
    COUNT(*) as reservations_created
FROM file_number_reservations
WHERE reserved_at >= DATEADD(day, -7, GETDATE())
GROUP BY CAST(reserved_at AS DATE)
ORDER BY CAST(reserved_at AS DATE) DESC;
GO

-- ============================================================================
-- PART 7: AUDIT TRAIL QUERY
-- ============================================================================

PRINT '';
PRINT 'Part 7: Audit trail for test reservations...';
GO

SELECT 
    fnr.file_number,
    fnr.land_use_type,
    fnr.status,
    fnr.reserved_at,
    fnr.expires_at,
    fnr.used_at,
    fnr.released_at,
    ISNULL(u.first_name + ' ' + u.surname, 'Unknown') as reserved_by_name,
    fnr.client_ip,
    CASE 
        WHEN fnr.status = 'reserved' AND fnr.expires_at < GETDATE() THEN 'EXPIRED - Needs Cleanup'
        WHEN fnr.status = 'reserved' AND fnr.expires_at >= GETDATE() THEN 'Active'
        WHEN fnr.status = 'used' THEN 'Used in Application'
        WHEN fnr.status = 'released' THEN 'Released (Draft Deleted)'
        WHEN fnr.status = 'expired' THEN 'Expired and Cleaned'
        ELSE fnr.status
    END as readable_status
FROM file_number_reservations fnr
LEFT JOIN users u ON fnr.reserved_by = u.id
WHERE fnr.file_number LIKE 'NPFN-2025-TEST-%'
ORDER BY fnr.reserved_at DESC;
GO

-- ============================================================================
-- PART 8: PERFORMANCE CHECK
-- ============================================================================

PRINT '';
PRINT 'Part 8: Performance statistics...';
GO

-- Table size
EXEC sp_spaceused 'file_number_reservations';
GO

-- Index usage
SELECT 
    i.name as index_name,
    s.user_seeks,
    s.user_scans,
    s.user_lookups,
    s.user_updates
FROM sys.indexes i
LEFT JOIN sys.dm_db_index_usage_stats s ON 
    i.object_id = s.object_id AND 
    i.index_id = s.index_id AND
    s.database_id = DB_ID()
WHERE i.object_id = OBJECT_ID('file_number_reservations')
ORDER BY i.name;
GO

-- ============================================================================
-- PART 9: CLEANUP TEST DATA (OPTIONAL)
-- ============================================================================

-- Uncomment the following to remove test data after testing:
/*
PRINT '';
PRINT 'Cleaning up test data...';
GO

DELETE FROM file_number_reservations 
WHERE file_number LIKE 'NPFN-2025-TEST-%';
GO

PRINT '✓ Test data cleaned up';
GO
*/

-- ============================================================================
-- PART 10: SUMMARY
-- ============================================================================

PRINT '';
PRINT '============================================================================';
PRINT 'SUMMARY';
PRINT '============================================================================';
GO

SELECT 
    'Total Reservations' as metric,
    CAST(COUNT(*) AS VARCHAR) as value
FROM file_number_reservations
UNION ALL
SELECT 
    'Active Reservations',
    CAST(COUNT(*) AS VARCHAR)
FROM file_number_reservations
WHERE status = 'reserved' AND expires_at >= GETDATE()
UNION ALL
SELECT 
    'Used Reservations',
    CAST(COUNT(*) AS VARCHAR)
FROM file_number_reservations
WHERE status = 'used'
UNION ALL
SELECT 
    'Released Reservations',
    CAST(COUNT(*) AS VARCHAR)
FROM file_number_reservations
WHERE status = 'released'
UNION ALL
SELECT 
    'Expired (Needs Cleanup)',
    CAST(COUNT(*) AS VARCHAR)
FROM file_number_reservations
WHERE status = 'reserved' AND expires_at < GETDATE()
UNION ALL
SELECT 
    'Expired (Cleaned)',
    CAST(COUNT(*) AS VARCHAR)
FROM file_number_reservations
WHERE status = 'expired';
GO

PRINT '';
PRINT 'Testing complete!';
PRINT '';
PRINT 'Next steps:';
PRINT '1. Review the output above for any errors or warnings';
PRINT '2. Run: php artisan filenumbers:cleanup --dry-run';
PRINT '3. Create a test draft in the application';
PRINT '4. Verify the reservation was created';
PRINT '5. Submit or delete the draft and verify status change';
PRINT '';
GO
