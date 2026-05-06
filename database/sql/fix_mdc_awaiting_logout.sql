-- =====================================================================
-- Quick Fix: Mark Stale MDC Sessions as Awaiting Logout
-- =====================================================================
-- Purpose: Update logout_time status for MDC staff currently online
--          with old login dates to mark as "Awaiting logout"
-- Created: 2026-01-09
-- =====================================================================

USE [klaes];
GO

-- Preview affected records
PRINT '===== PREVIEW: MDC Staff Awaiting Logout ======'
SELECT 
    u.id,
    u.first_name + ' ' + u.last_name AS employee_name,
    l.login_time,
    l.logout_time,
    l.is_online,
    l.status,
    CASE 
        WHEN l.logout_time IS NULL THEN 'Already Null'
        ELSE 'Will be updated'
    END AS action
FROM user_activity_logs l
INNER JOIN users u ON u.id = l.user_id
WHERE u.staff_type_category = 'MDC'
  AND u.is_active = '1' 
  AND l.is_online = 1
  AND CAST(l.login_time AS DATE) < CAST(GETDATE() AS DATE)
ORDER BY l.login_time DESC;

PRINT ''
PRINT '===== UPDATING: Set logout_time = NULL (Awaiting logout) ======'

-- Update logout_time to NULL and set status to Awaiting Logout
UPDATE l
SET 
    l.logout_time = NULL,
    l.status = 'Awaiting Logout',
    l.is_online = 1,
    l.updated_at = GETDATE()
FROM user_activity_logs l
INNER JOIN users u ON u.id = l.user_id
WHERE u.staff_type_category = 'MDC'
  AND u.is_active = '1' 
  AND l.is_online = 1
  AND CAST(l.login_time AS DATE) < CAST(GETDATE() AS DATE);

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
WHERE u.staff_type_category = 'MDC'
  AND u.is_active = '1' 
  AND l.status = 'Awaiting Logout'
ORDER BY l.login_time DESC;

PRINT ''
PRINT '===== COMPLETE: All stale sessions marked as "Awaiting Logout" ======'
GO
