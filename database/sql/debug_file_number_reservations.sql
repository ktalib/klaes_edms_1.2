-- ============================================================================
-- FILE NUMBER RESERVATION DIAGNOSTIC SCRIPT
-- ============================================================================
-- This script helps diagnose why file_number_reservations is empty
-- and why users are getting duplicate file numbers
-- ============================================================================

PRINT '============================================================================';
PRINT 'FILE NUMBER RESERVATION SYSTEM DIAGNOSTIC';
PRINT '============================================================================';
PRINT '';

-- ============================================================================
-- PART 1: Check if file_number_reservations table exists and structure
-- ============================================================================
PRINT '-- PART 1: Table Structure Check';
PRINT '';

IF OBJECT_ID('dbo.file_number_reservations', 'U') IS NOT NULL
BEGIN
    PRINT '✓ Table file_number_reservations EXISTS';
    
    PRINT '';
    PRINT 'Table columns:';
    SELECT 
        COLUMN_NAME,
        DATA_TYPE,
        IS_NULLABLE,
        COLUMN_DEFAULT
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'file_number_reservations'
    ORDER BY ORDINAL_POSITION;
    
    PRINT '';
    PRINT 'Row count:';
    SELECT COUNT(*) as total_rows FROM [dbo].[file_number_reservations];
    
    PRINT '';
    PRINT 'Recent records (if any):';
    SELECT TOP 10 
        file_number,
        land_use_type,
        year,
        serial_number,
        status,
        reserved_by,
        draft_id,
        application_id,
        created_at
    FROM [dbo].[file_number_reservations]
    ORDER BY created_at DESC;
END
ELSE
BEGIN
    PRINT '✗ ERROR: Table file_number_reservations DOES NOT EXIST!';
    PRINT '  → This explains why no records are being inserted!';
    PRINT '  → You need to run the migration or SQL script first!';
END

PRINT '';

-- ============================================================================
-- PART 2: Check land_use_serials table
-- ============================================================================
PRINT '-- PART 2: Land Use Serials Check';
PRINT '';

IF OBJECT_ID('dbo.land_use_serials', 'U') IS NOT NULL
BEGIN
    PRINT '✓ Table land_use_serials EXISTS';
    
    PRINT '';
    PRINT 'Current serials:';
    SELECT 
        land_use_type,
        prefix,
        year,
        current_serial,
        created_at,
        updated_at
    FROM [dbo].[land_use_serials]
    ORDER BY land_use_type, year DESC;
END
ELSE
BEGIN
    PRINT '✗ ERROR: Table land_use_serials DOES NOT EXIST!';
    PRINT '  → This could cause file number generation issues!';
END

PRINT '';

-- ============================================================================
-- PART 3: Check mother_application_draft for duplicate file numbers
-- ============================================================================
PRINT '-- PART 3: Draft Duplicate Check';
PRINT '';

IF OBJECT_ID('dbo.mother_application_draft', 'U') IS NOT NULL
BEGIN
    PRINT '✓ Table mother_application_draft EXISTS';
    
    PRINT '';
    PRINT 'Recent drafts with file numbers:';
    SELECT TOP 10
        draft_id,
        np_file_no,
        created_at,
        last_saved_at,
        last_saved_by
    FROM [dbo].[mother_application_draft]
    WHERE np_file_no IS NOT NULL
    ORDER BY last_saved_at DESC;
    
    PRINT '';
    PRINT 'Checking for DUPLICATE file numbers in drafts:';
    SELECT 
        np_file_no,
        COUNT(*) as duplicate_count,
        STRING_AGG(CAST(draft_id AS VARCHAR), ', ') as draft_ids
    FROM [dbo].[mother_application_draft]
    WHERE np_file_no IS NOT NULL
    GROUP BY np_file_no
    HAVING COUNT(*) > 1
    ORDER BY COUNT(*) DESC;
    
    IF @@ROWCOUNT = 0
        PRINT '✓ No duplicates found in drafts';
    ELSE
        PRINT '✗ DUPLICATES DETECTED! Multiple drafts have same file number!';
END
ELSE
BEGIN
    PRINT '✗ Table mother_application_draft does not exist';
END

PRINT '';

-- ============================================================================
-- PART 4: Check mother_applications for duplicate file numbers
-- ============================================================================
PRINT '-- PART 4: Applications Duplicate Check';
PRINT '';

IF OBJECT_ID('dbo.mother_applications', 'U') IS NOT NULL
BEGIN
    PRINT '✓ Table mother_applications EXISTS';
    
    PRINT '';
    PRINT 'Recent applications with file numbers:';
    SELECT TOP 10
        id,
        np_fileno,
        created_at
    FROM [dbo].[mother_applications]
    WHERE np_fileno IS NOT NULL
    ORDER BY created_at DESC;
    
    PRINT '';
    PRINT 'Checking for DUPLICATE file numbers in applications:';
    SELECT 
        np_fileno,
        COUNT(*) as duplicate_count,
        STRING_AGG(CAST(id AS VARCHAR), ', ') as application_ids
    FROM [dbo].[mother_applications]
    WHERE np_fileno IS NOT NULL
    GROUP BY np_fileno
    HAVING COUNT(*) > 1
    ORDER BY COUNT(*) DESC;
    
    IF @@ROWCOUNT = 0
        PRINT '✓ No duplicates found in applications';
    ELSE
        PRINT '✗ DUPLICATES DETECTED! Multiple applications have same file number!';
END
ELSE
BEGIN
    PRINT '✗ Table mother_applications does not exist';
END

PRINT '';

-- ============================================================================
-- PART 5: Check Laravel logs for errors
-- ============================================================================
PRINT '-- PART 5: System Status Summary';
PRINT '';

PRINT 'Database Connection Check:';
SELECT 
    'Current database' as info,
    DB_NAME() as database_name,
    @@SERVERNAME as server_name,
    GETDATE() as current_time;

PRINT '';

-- ============================================================================
-- PART 6: Test file number generation logic
-- ============================================================================
PRINT '-- PART 6: File Number Generation Test';
PRINT '';

-- Test if we can manually generate what should be the next file number
DECLARE @testLandUse VARCHAR(50) = 'Residential';
DECLARE @testYear INT = YEAR(GETDATE());
DECLARE @nextSerial INT;

-- Check current serial from land_use_serials
SELECT @nextSerial = current_serial
FROM [dbo].[land_use_serials]
WHERE land_use_type = @testLandUse AND year = @testYear;

IF @nextSerial IS NULL
BEGIN
    PRINT 'No serial record found for ' + @testLandUse + ' ' + CAST(@testYear AS VARCHAR);
    SET @nextSerial = 1;
END
ELSE
BEGIN
    SET @nextSerial = @nextSerial + 1;
END

PRINT 'Expected next file number for ' + @testLandUse + ':';
PRINT '  ST-RES-' + CAST(@testYear AS VARCHAR) + '-' + CAST(@nextSerial AS VARCHAR);

PRINT '';

-- ============================================================================
-- PART 7: Recommendations
-- ============================================================================
PRINT '============================================================================';
PRINT 'DIAGNOSTIC RECOMMENDATIONS';
PRINT '============================================================================';
PRINT '';

IF OBJECT_ID('dbo.file_number_reservations', 'U') IS NULL
BEGIN
    PRINT '❌ CRITICAL: file_number_reservations table missing!';
    PRINT '';
    PRINT '   IMMEDIATE ACTION REQUIRED:';
    PRINT '   1. Run: php artisan migrate --database=sqlsrv';
    PRINT '   2. OR run: create_file_number_reservations.sql';
    PRINT '';
END

-- Check for duplicates across all tables
DECLARE @draftDuplicates INT = 0;
DECLARE @appDuplicates INT = 0;

IF OBJECT_ID('dbo.mother_application_draft', 'U') IS NOT NULL
BEGIN
    SELECT @draftDuplicates = COUNT(*)
    FROM (
        SELECT np_file_no
        FROM [dbo].[mother_application_draft]
        WHERE np_file_no IS NOT NULL
        GROUP BY np_file_no
        HAVING COUNT(*) > 1
    ) duplicates;
END

IF OBJECT_ID('dbo.mother_applications', 'U') IS NOT NULL
BEGIN
    SELECT @appDuplicates = COUNT(*)
    FROM (
        SELECT np_fileno
        FROM [dbo].[mother_applications]
        WHERE np_fileno IS NOT NULL
        GROUP BY np_fileno
        HAVING COUNT(*) > 1
    ) duplicates;
END

IF @draftDuplicates > 0 OR @appDuplicates > 0
BEGIN
    PRINT '❌ CRITICAL: Duplicate file numbers detected!';
    PRINT '   Draft duplicates: ' + CAST(@draftDuplicates AS VARCHAR);
    PRINT '   Application duplicates: ' + CAST(@appDuplicates AS VARCHAR);
    PRINT '';
    PRINT '   This indicates race conditions or missing reservation system!';
    PRINT '';
END

PRINT 'Next steps to investigate:';
PRINT '1. Check Laravel logs: storage/logs/laravel.log';
PRINT '2. Check if FileNumberReservationService is being called';
PRINT '3. Check for database connection errors';
PRINT '4. Verify transaction isolation levels';
PRINT '5. Check for missing database migration';

PRINT '';
PRINT '============================================================================';
GO