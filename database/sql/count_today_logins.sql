-- =====================================================================
-- Simple: Count Users Who Logged In Today vs Currently Online
-- =====================================================================
-- Purpose: Show number of MDC staff users who logged in today
--          vs users currently marked as online (might be from older dates)
-- =====================================================================

USE [klas];
GO

DECLARE @today DATE = CAST(GETDATE() AS DATE);

PRINT '===== TODAY''S LOGIN vs ONLINE STATUS ======'
PRINT 'Date: ' + CONVERT(VARCHAR(10), @today, 120)
PRINT ''

-- Count users who logged in TODAY
PRINT '===== USERS WHO LOGGED IN TODAY ======'
SELECT 
    COUNT(DISTINCT u.id) AS users_logged_in_today
FROM user_activity_logs l
INNER JOIN users u ON u.id = l.user_id
WHERE u.staff_type_category = 'MDC'
  AND u.is_active = 1
  AND CAST(l.login_time AS DATE) = @today;

PRINT ''
PRINT '===== USERS CURRENTLY MARKED ONLINE (Any date) ======'
SELECT 
    COUNT(DISTINCT u.id) AS users_currently_online,
    COUNT(l.id) AS total_online_sessions
FROM user_activity_logs l
INNER JOIN users u ON u.id = l.user_id
WHERE u.staff_type_category = 'MDC'
  AND u.is_active = 1
  AND l.is_online = 1;

PRINT ''
PRINT '===== BREAKDOWN: Online users by login date ======'
SELECT 
    CAST(l.login_time AS DATE) AS login_date,
    COUNT(DISTINCT u.id) AS users_count,
    CASE 
        WHEN CAST(l.login_time AS DATE) = @today THEN 'TODAY'
        WHEN CAST(l.login_time AS DATE) < @today THEN 'STALE (Old date)'
        ELSE 'FUTURE'
    END AS status
FROM user_activity_logs l
INNER JOIN users u ON u.id = l.user_id
WHERE u.staff_type_category = 'MDC'
  AND u.is_active = 1
  AND l.is_online = 1
GROUP BY CAST(l.login_time AS DATE)
ORDER BY login_date DESC;

PRINT ''
PRINT '===== STALE ONLINE SESSIONS (Wrong dates) ======'
SELECT 
    u.id,
    u.first_name + ' ' + u.last_name AS employee_name,
    l.login_time,
    l.logout_time,
    DATEDIFF(DAY, CAST(l.login_time AS DATE), @today) AS days_ago
FROM user_activity_logs l
INNER JOIN users u ON u.id = l.user_id
WHERE u.staff_type_category = 'MDC'
  AND u.is_active = 1
  AND l.is_online = 1
  AND CAST(l.login_time AS DATE) < @today
ORDER BY l.login_time DESC;

GO
