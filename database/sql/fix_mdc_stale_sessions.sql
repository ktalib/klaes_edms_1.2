-- =====================================================================
-- Fix MDC Staff Stale Online Sessions
-- =====================================================================
-- Purpose: Close stale online sessions for MDC staff with backwards 
--          login dates and create fresh login records for today
-- Created: 2026-01-09
-- =====================================================================

USE [klaes];
GO

-- =====================================================================
-- STEP 1: PREVIEW - Show all affected records
-- =====================================================================
-- This query shows all MDC staff users currently marked as online
-- whose login_time is NOT from today (2026-01-09)

PRINT '===== PREVIEW: Affected MDC Staff Sessions ======'
PRINT 'Date: ' + CONVERT(VARCHAR(10), GETDATE(), 120)
PRINT 'Looking for: is_online=1 AND login_time date != today AND staff_type_category=MDC'
PRINT ''

SELECT 
    u.id AS user_id,
    u.first_name + ' ' + u.last_name AS employee_name,
    u.staff_type_category,
    u.is_active,
    l.id AS log_id,
    l.login_time,
    l.logout_time,
    l.is_online,
    l.duration_minutes,
    l.status,
    CAST(l.login_time AS DATE) AS login_date,
    CAST(GETDATE() AS DATE) AS today_date,
    DATEDIFF(DAY, CAST(l.login_time AS DATE), CAST(GETDATE() AS DATE)) AS days_since_login,
    CASE 
        WHEN DATEDIFF(DAY, CAST(l.login_time AS DATE), CAST(GETDATE() AS DATE)) > 0 THEN 'STALE - Logout yesterday, Login today'
        ELSE 'SKIP - Already from today'
    END AS action_needed
FROM user_activity_logs l
INNER JOIN users u ON u.id = l.user_id
WHERE u.staff_type_category = 'MDC'
  AND u.is_active = '1' 
  AND l.is_online = 1
  AND CAST(l.login_time AS DATE) < CAST(GETDATE() AS DATE)
ORDER BY l.login_time DESC, u.first_name;

PRINT ''
PRINT 'Row count above = records to be fixed'
PRINT ''
GO

-- =====================================================================
-- STEP 2: UPDATE - Fix the sessions
-- =====================================================================
-- This update will:
-- 1. Set logout_time to the old login_time (end of previous session)
-- 2. Set login_time to TODAY at 09:00:00 (fresh login for today)
-- 3. Set is_online = 1 (mark them online again)
-- 4. Recalculate duration_minutes for closed session
-- 5. Update status to 'Online'

PRINT '===== EXECUTING: Fixing MDC Staff Sessions ======'
PRINT 'This will:'
PRINT '  1. Close old sessions (set logout_time to previous login time)'
PRINT '  2. Create fresh login for today (set login_time to today 09:00:00)'
PRINT '  3. Mark users as online (is_online=1, status=Online)'
PRINT ''

-- Create temp table to track affected records for rollback
CREATE TABLE #affected_records (
    id INT,
    user_id INT,
    old_login_time DATETIME2,
    old_logout_time DATETIME2,
    old_is_online BIT,
    old_status VARCHAR(20),
    old_duration_minutes INT,
    new_login_time DATETIME2,
    new_logout_time DATETIME2,
    new_is_online BIT,
    new_status VARCHAR(20),
    new_duration_minutes INT
);

-- Store the old values before update (for potential rollback)
INSERT INTO #affected_records
SELECT 
    l.id,
    u.id AS user_id,
    l.login_time AS old_login_time,
    l.logout_time AS old_logout_time,
    l.is_online AS old_is_online,
    l.status AS old_status,
    l.duration_minutes AS old_duration_minutes,
    -- New values
    CAST(CONVERT(VARCHAR(10), GETDATE(), 120) + ' 09:00:00' AS DATETIME2) AS new_login_time,
    l.login_time AS new_logout_time,  -- Old login becomes new logout
    CAST(1 AS BIT) AS new_is_online,
    'Online' AS new_status,
    DATEDIFF(MINUTE, l.login_time, GETDATE()) AS new_duration_minutes  -- Duration until today
FROM user_activity_logs l
INNER JOIN users u ON u.id = l.user_id
WHERE u.staff_type_category = 'MDC'
  AND u.is_active = '1' 
  AND l.is_online = 1
  AND CAST(l.login_time AS DATE) < CAST(GETDATE() AS DATE);

PRINT 'Prepared ' + CAST(@@ROWCOUNT AS VARCHAR) + ' records for update'
PRINT ''

-- Now perform the actual update
UPDATE l
SET 
    l.logout_time = ar.new_logout_time,
    l.login_time = ar.new_login_time,
    l.is_online = ar.new_is_online,
    l.status = ar.new_status,
    l.duration_minutes = ar.new_duration_minutes,
    l.updated_at = GETDATE()
FROM user_activity_logs l
INNER JOIN #affected_records ar ON l.id = ar.id;

PRINT 'Updated ' + CAST(@@ROWCOUNT AS VARCHAR) + ' records successfully'
PRINT ''

-- Show the results
PRINT '===== RESULTS: After Fix ======'
SELECT 
    u.id AS user_id,
    u.first_name + ' ' + u.last_name AS employee_name,
    l.id AS log_id,
    l.login_time,
    l.logout_time,
    l.is_online,
    l.status,
    l.duration_minutes,
    'SUCCESS' AS fix_status
FROM user_activity_logs l
INNER JOIN users u ON u.id = l.user_id
INNER JOIN #affected_records ar ON l.id = ar.id
ORDER BY l.login_time DESC, u.first_name;

PRINT ''
PRINT '===== BACKUP DATA (for rollback if needed) ======'
PRINT 'Backup of original values stored in #affected_records:'
SELECT * FROM #affected_records
ORDER BY old_login_time DESC;

PRINT ''
PRINT '===== ROLLBACK SCRIPT (if needed) ======'
PRINT 'If you need to rollback, use this script:'
PRINT ''
PRINT 'USE [klaes];'
PRINT 'GO'
PRINT ''
PRINT 'UPDATE l'
PRINT 'SET'
PRINT '    l.login_time = ar.old_login_time,'
PRINT '    l.logout_time = ar.old_logout_time,'
PRINT '    l.is_online = ar.old_is_online,'
PRINT '    l.status = ar.old_status,'
PRINT '    l.duration_minutes = ar.old_duration_minutes,'
PRINT '    l.updated_at = GETDATE()'
PRINT 'FROM user_activity_logs l'
PRINT 'INNER JOIN #affected_records ar ON l.id = ar.id;'
PRINT ''
PRINT 'GO'
PRINT ''

-- Clean up temp table
DROP TABLE #affected_records;

PRINT '===== FIX COMPLETE ====='
PRINT 'All MDC staff with stale sessions have been:'
PRINT '  ✓ Logged out (old session closed with previous login as logout time)'
PRINT '  ✓ Logged in (fresh session created for today at 09:00:00)'
PRINT '  ✓ Marked online (is_online=1, status=Online)'
GO
