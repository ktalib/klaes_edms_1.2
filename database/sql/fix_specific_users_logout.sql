-- =====================================================================
-- Quick Fix: Update Specific Users to Awaiting Logout
-- =====================================================================
-- Purpose: Update logout_time for specific MDC staff user IDs
--          (From screenshot: 50532, 50536, 50556, 50087, 50500)
-- Created: 2026-01-09
-- =====================================================================

USE [klaes];
GO

-- Preview affected records
PRINT '===== PREVIEW: Specific Users ======'
SELECT 
    u.id,
    u.first_name + ' ' + u.last_name AS employee_name,
    l.login_time,
    l.logout_time,
    l.is_online,
    l.status
FROM user_activity_logs l
INNER JOIN users u ON u.id = l.user_id
WHERE u.id IN (50532, 50536, 50556, 50087, 50500)
  AND l.is_online = 1
ORDER BY u.id;

PRINT ''
PRINT '===== UPDATING: Set logout_time = NULL (Awaiting logout) ======'

-- Update these specific users - set logout_time to NULL
UPDATE l
SET 
    l.logout_time = NULL,
    l.updated_at = GETDATE()
FROM user_activity_logs l
WHERE l.user_id IN (50532, 50536, 50556, 50087, 50500)
  AND l.is_online = 1;

PRINT 'Updated ' + CAST(@@ROWCOUNT AS VARCHAR) + ' records'
PRINT ''

-- Show results
PRINT '===== RESULTS: After Update ======'
SELECT 
    u.id,
    u.first_name + ' ' + u.last_name AS employee_name,
    l.login_time,
    l.logout_time,
    l.is_online,
    l.status
FROM user_activity_logs l
INNER JOIN users u ON u.id = l.user_id
WHERE u.id IN (50532, 50536, 50556, 50087, 50500)
ORDER BY u.id;

PRINT ''
PRINT '===== COMPLETE ======'
GO
