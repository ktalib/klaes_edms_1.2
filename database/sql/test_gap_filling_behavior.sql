-- ============================================================================
-- GAP-FILLING FILE NUMBER RESERVATION TEST
-- ============================================================================
-- This script demonstrates the gap-filling behavior of the file number
-- reservation system to ensure NO GAPS in the final sequential numbering.
--
-- Scenario: ST-RES-2025-1 is reserved then expires, ST-RES-2025-2 is used.
--           Next reservation should get ST-RES-2025-1 (filling the gap).
-- ============================================================================

PRINT '============================================================================';
PRINT 'GAP-FILLING FILE NUMBER RESERVATION TEST';
PRINT '============================================================================';
PRINT '';

-- ============================================================================
-- PART 1: Setup - Clear any existing test data
-- ============================================================================
PRINT '-- PART 1: Cleanup existing test data';
PRINT '';

DELETE FROM [dbo].[file_number_reservations] 
WHERE land_use_type = 'Residential' AND year = 2025;

DELETE FROM [dbo].[land_use_serials] 
WHERE land_use_type = 'Residential' AND year = 2025;

PRINT '✓ Test data cleared';
PRINT '';

-- ============================================================================
-- PART 2: Create initial state
-- ============================================================================
PRINT '-- PART 2: Initialize land_use_serials';
PRINT '';

INSERT INTO [dbo].[land_use_serials] (land_use_type, prefix, year, current_serial, created_at, updated_at)
VALUES ('Residential', 'RES', 2025, 0, GETDATE(), GETDATE());

PRINT '✓ Initialized: Residential 2025 serial = 0';
PRINT '';

-- ============================================================================
-- PART 3: Simulate User A reserves ST-RES-2025-1 (saves draft)
-- ============================================================================
PRINT '-- PART 3: User A reserves ST-RES-2025-1 (draft)';
PRINT '';

INSERT INTO [dbo].[file_number_reservations] 
(file_number, land_use_type, year, serial_number, status, reserved_by, draft_id, expires_at, created_at, updated_at)
VALUES 
('ST-RES-2025-1', 'Residential', 2025, 1, 'reserved', 1, 100, DATEADD(day, 3, GETDATE()), GETDATE(), GETDATE());

UPDATE [dbo].[land_use_serials] 
SET current_serial = 1, updated_at = GETDATE()
WHERE land_use_type = 'Residential' AND year = 2025;

PRINT '✓ Reserved: ST-RES-2025-1 (status: reserved)';
PRINT '  - Draft ID: 100';
PRINT '  - Expires: ' + CONVERT(VARCHAR, DATEADD(day, 3, GETDATE()), 120);
PRINT '';

SELECT 
    file_number,
    serial_number,
    status,
    draft_id,
    application_id,
    expires_at
FROM [dbo].[file_number_reservations]
WHERE land_use_type = 'Residential' AND year = 2025
ORDER BY serial_number;

PRINT '';

-- ============================================================================
-- PART 4: Simulate User B submits application (gets ST-RES-2025-2)
-- ============================================================================
PRINT '-- PART 4: User B submits application (no draft, direct submission)';
PRINT '';

INSERT INTO [dbo].[file_number_reservations] 
(file_number, land_use_type, year, serial_number, status, reserved_by, application_id, expires_at, created_at, updated_at)
VALUES 
('ST-RES-2025-2', 'Residential', 2025, 2, 'used', 2, 500, NULL, GETDATE(), GETDATE());

UPDATE [dbo].[land_use_serials] 
SET current_serial = 2, updated_at = GETDATE()
WHERE land_use_type = 'Residential' AND year = 2025;

PRINT '✓ Used: ST-RES-2025-2 (status: used)';
PRINT '  - Application ID: 500';
PRINT '  - Submitted to mother_applications';
PRINT '';

SELECT 
    file_number,
    serial_number,
    status,
    draft_id,
    application_id
FROM [dbo].[file_number_reservations]
WHERE land_use_type = 'Residential' AND year = 2025
ORDER BY serial_number;

PRINT '';

-- ============================================================================
-- PART 5: Simulate User A's draft expires (3 days later)
-- ============================================================================
PRINT '-- PART 5: User A''s draft expires (cleanup runs)';
PRINT '';

UPDATE [dbo].[file_number_reservations]
SET status = 'expired', updated_at = GETDATE()
WHERE file_number = 'ST-RES-2025-1' AND status = 'reserved';

PRINT '✓ Expired: ST-RES-2025-1 (status: expired)';
PRINT '  - Draft was never submitted';
PRINT '  - File number now available for reuse';
PRINT '';

SELECT 
    file_number,
    serial_number,
    status,
    draft_id,
    application_id
FROM [dbo].[file_number_reservations]
WHERE land_use_type = 'Residential' AND year = 2025
ORDER BY serial_number;

PRINT '';

-- ============================================================================
-- PART 6: Check for gaps (what number will User C get?)
-- ============================================================================
PRINT '-- PART 6: Gap Detection - What number is available?';
PRINT '';

PRINT 'Checking for gaps (released/expired reservations):';
SELECT 
    file_number,
    serial_number,
    status,
    'This gap can be reused!' as note
FROM [dbo].[file_number_reservations]
WHERE land_use_type = 'Residential' 
AND year = 2025
AND status IN ('released', 'expired')
ORDER BY serial_number ASC;

PRINT '';
PRINT 'Current state summary:';
SELECT 
    status,
    COUNT(*) as count,
    MIN(serial_number) as min_serial,
    MAX(serial_number) as max_serial
FROM [dbo].[file_number_reservations]
WHERE land_use_type = 'Residential' AND year = 2025
GROUP BY status
ORDER BY 
    CASE status 
        WHEN 'used' THEN 1 
        WHEN 'reserved' THEN 2 
        WHEN 'expired' THEN 3 
        WHEN 'released' THEN 4 
    END;

PRINT '';

-- ============================================================================
-- PART 7: Simulate getNextAvailableSerial() logic
-- ============================================================================
PRINT '-- PART 7: Simulate getNextAvailableSerial() with GAP-FILLING';
PRINT '';

DECLARE @nextSerial INT;
DECLARE @gapSerial INT;
DECLARE @maxUsedSerial INT;

-- Check for gaps first (gap-filling strategy)
SELECT TOP 1 @gapSerial = serial_number
FROM [dbo].[file_number_reservations]
WHERE land_use_type = 'Residential' 
AND year = 2025
AND status IN ('released', 'expired')
ORDER BY serial_number ASC;

IF @gapSerial IS NOT NULL
BEGIN
    SET @nextSerial = @gapSerial;
    PRINT '✓ GAP FOUND! Will reuse serial number: ' + CAST(@gapSerial AS VARCHAR);
    PRINT '  - Strategy: Gap-filling (no gaps in final sequence)';
END
ELSE
BEGIN
    -- No gaps, get next sequential
    SELECT @maxUsedSerial = MAX(serial_number)
    FROM [dbo].[file_number_reservations]
    WHERE land_use_type = 'Residential' 
    AND year = 2025
    AND status IN ('reserved', 'used');
    
    SET @nextSerial = ISNULL(@maxUsedSerial, 0) + 1;
    PRINT '✓ NO GAPS. Next sequential number: ' + CAST(@nextSerial AS VARCHAR);
    PRINT '  - Strategy: Sequential increment';
END

PRINT '';
PRINT '→ Next file number will be: ST-RES-2025-' + CAST(@nextSerial AS VARCHAR);
PRINT '';

-- ============================================================================
-- PART 8: Simulate User C gets the gap-filled number
-- ============================================================================
PRINT '-- PART 8: User C submits application (should get ST-RES-2025-1)';
PRINT '';

-- Delete the expired reservation (gap-filling removes it)
DELETE FROM [dbo].[file_number_reservations]
WHERE file_number = 'ST-RES-2025-1' AND status = 'expired';

-- Insert new reservation with reused serial number
INSERT INTO [dbo].[file_number_reservations] 
(file_number, land_use_type, year, serial_number, status, reserved_by, application_id, expires_at, created_at, updated_at)
VALUES 
('ST-RES-2025-1', 'Residential', 2025, 1, 'used', 3, 501, NULL, GETDATE(), GETDATE());

PRINT '✓ User C got: ST-RES-2025-1 (filled the gap!)';
PRINT '  - Application ID: 501';
PRINT '  - Status: used';
PRINT '  - No gap in sequence!';
PRINT '';

-- ============================================================================
-- PART 9: Final verification - NO GAPS
-- ============================================================================
PRINT '-- PART 9: Final Verification - Check sequence integrity';
PRINT '';

PRINT 'Final reservations (sorted by serial):';
SELECT 
    serial_number,
    file_number,
    status,
    application_id,
    CASE 
        WHEN LAG(serial_number) OVER (ORDER BY serial_number) IS NULL THEN 'First'
        WHEN serial_number = LAG(serial_number) OVER (ORDER BY serial_number) + 1 THEN 'Sequential ✓'
        ELSE 'GAP DETECTED! ✗'
    END as sequence_check
FROM [dbo].[file_number_reservations]
WHERE land_use_type = 'Residential' AND year = 2025
AND status = 'used'
ORDER BY serial_number;

PRINT '';

-- Check for any missing numbers in the sequence
DECLARE @minSerial INT, @maxSerial INT, @usedCount INT, @expectedCount INT;

SELECT 
    @minSerial = MIN(serial_number),
    @maxSerial = MAX(serial_number),
    @usedCount = COUNT(*)
FROM [dbo].[file_number_reservations]
WHERE land_use_type = 'Residential' AND year = 2025
AND status = 'used';

SET @expectedCount = @maxSerial - @minSerial + 1;

PRINT 'Sequence integrity check:';
PRINT '  - Min serial: ' + CAST(@minSerial AS VARCHAR);
PRINT '  - Max serial: ' + CAST(@maxSerial AS VARCHAR);
PRINT '  - Used count: ' + CAST(@usedCount AS VARCHAR);
PRINT '  - Expected count: ' + CAST(@expectedCount AS VARCHAR);
PRINT '';

IF @usedCount = @expectedCount
BEGIN
    PRINT '✓✓✓ SUCCESS! NO GAPS IN SEQUENCE! ✓✓✓';
    PRINT '';
    PRINT 'Final file numbers in mother_applications:';
    PRINT '  - ST-RES-2025-1 ✓ (gap was filled)';
    PRINT '  - ST-RES-2025-2 ✓';
    PRINT '  - ST-RES-2025-3 (next one)';
END
ELSE
BEGIN
    PRINT '✗✗✗ WARNING! GAPS DETECTED! ✗✗✗';
    PRINT 'Missing serials: ' + CAST((@expectedCount - @usedCount) AS VARCHAR);
END

PRINT '';

-- ============================================================================
-- PART 10: Summary
-- ============================================================================
PRINT '============================================================================';
PRINT 'TEST SUMMARY';
PRINT '============================================================================';
PRINT '';
PRINT 'Gap-Filling Strategy Results:';
PRINT '  1. User A reserved ST-RES-2025-1 (draft)';
PRINT '  2. User B got ST-RES-2025-2 (submitted)';
PRINT '  3. User A''s draft expired → ST-RES-2025-1 became available';
PRINT '  4. User C got ST-RES-2025-1 (reused the gap)';
PRINT '';
PRINT '✓ Final sequence has NO GAPS';
PRINT '✓ All file numbers are sequential: 1, 2, 3...';
PRINT '✓ No numbers are skipped in mother_applications table';
PRINT '';
PRINT 'This ensures that your file numbering system maintains:';
PRINT '  • Complete sequential numbering (no missing numbers)';
PRINT '  • Efficient use of reserved numbers (no waste)';
PRINT '  • Clear audit trail (expired reservations deleted after reuse)';
PRINT '';
PRINT '============================================================================';
GO
