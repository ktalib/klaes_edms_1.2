-- =====================================================================
-- Reset Session For Specific User (user_id = 50522)
-- =====================================================================
-- Purpose: Force logout of the current stale session and create a fresh
--          login entry with the current date/time.
-- Date: 2026-01-09
-- =====================================================================

USE [klas];
GO

DECLARE @UserId INT = 50551;
DECLARE @Now DATETIME2(0) = SYSDATETIME();

PRINT '===== PREVIEW: Current Online Session ======';
SELECT TOP 1
    id,
    user_id,
    login_time,
    logout_time,
    is_online,
    status
FROM user_activity_logs
WHERE user_id = @UserId
  AND is_online = 1
ORDER BY login_time DESC;

PRINT '';
PRINT '===== STEP 1: Force Logout Existing Session ======';

UPDATE user_activity_logs
SET
    logout_time = @Now,
    status = 'Offline',
    is_online = 0,
    duration_minutes = DATEDIFF(MINUTE, login_time, @Now),
    updated_at = @Now
WHERE user_id = @UserId
  AND is_online = 1;

PRINT 'Rows updated: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

PRINT '';
PRINT '===== STEP 2: Insert Fresh Login Record ======';

INSERT INTO user_activity_logs (
    user_id,
    ip_address,
    user_agent,
    login_time,
    is_online,
    status,
    session_id,
    device_type,
    browser,
    platform,
    last_seen_at,
    test_control,
    created_at,
    updated_at
)
SELECT TOP 1
    @UserId,
    ip_address,
    user_agent,
    @Now,
    1,
    'Online',
    CONCAT('RESET_', CONVERT(VARCHAR(36), NEWID())),
    device_type,
    browser,
    platform,
    @Now,
    COALESCE(test_control, 'PRO'),
    @Now,
    @Now
FROM user_activity_logs
WHERE user_id = @UserId
ORDER BY id DESC;

PRINT 'Rows inserted: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

PRINT '';
PRINT '===== RESULT: Latest Session For User ======';
SELECT TOP 5
    id,
    login_time,
    logout_time,
    is_online,
    status
FROM user_activity_logs
WHERE user_id = @UserId
ORDER BY id DESC;
GO
