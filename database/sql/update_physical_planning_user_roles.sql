-- MS SQL Script to Update Physical Planning User Roles
-- Department ID: 9 (Physical Planning)
-- Created: September 21, 2025

USE [klas];
GO

-- Update existing Physical Planning roles and insert new ones if they don't exist
-- First, let's update the existing roles or insert new ones

-- 1. PP - Regular Applications
IF EXISTS (SELECT 1 FROM [dbo].[user_roles] WHERE [name] = 'PP - Regular Applications' AND [department_id] = 9)
BEGIN
    UPDATE [dbo].[user_roles] 
    SET 
        [description] = 'Physical Planning - Regular Applications processing and management',
        [level] = 'High',
        [user_type] = 'Operations',
        [is_active] = 1,
        [updated_at] = GETDATE()
    WHERE [name] = 'PP - Regular Applications' AND [department_id] = 9;
    PRINT 'Updated: PP - Regular Applications';
END
ELSE
BEGIN
    INSERT INTO [dbo].[user_roles] ([name], [guard_name], [description], [department_id], [level], [user_type], [is_active], [created_at], [updated_at])
    VALUES ('PP - Regular Applications', 'web', 'Physical Planning - Regular Applications processing and management', 9, 'High', 'Operations', 1, GETDATE(), GETDATE());
    PRINT 'Inserted: PP - Regular Applications';
END

-- 2. Planning Recommendation
IF EXISTS (SELECT 1 FROM [dbo].[user_roles] WHERE [name] = 'Planning Recommendation' AND [department_id] = 9)
BEGIN
    UPDATE [dbo].[user_roles] 
    SET 
        [description] = 'Physical Planning - Planning Recommendation review and processing',
        [level] = 'High',
        [user_type] = 'Operations',
        [is_active] = 1,
        [updated_at] = GETDATE()
    WHERE [name] = 'Planning Recommendation' AND [department_id] = 9;
    PRINT 'Updated: Planning Recommendation';
END
ELSE
BEGIN
    INSERT INTO [dbo].[user_roles] ([name], [guard_name], [description], [department_id], [level], [user_type], [is_active], [created_at], [updated_at])
    VALUES ('Planning Recommendation', 'web', 'Physical Planning - Planning Recommendation review and processing', 9, 'High', 'Operations', 1, GETDATE(), GETDATE());
    PRINT 'Inserted: Planning Recommendation';
END

-- 3. ST eRegistry
IF EXISTS (SELECT 1 FROM [dbo].[user_roles] WHERE [name] = 'ST eRegistry' AND [department_id] = 9)
BEGIN
    UPDATE [dbo].[user_roles] 
    SET 
        [description] = 'Physical Planning - Sectional Titling Electronic Registry access',
        [level] = 'High',
        [user_type] = 'Operations',
        [is_active] = 1,
        [updated_at] = GETDATE()
    WHERE [name] = 'ST eRegistry' AND [department_id] = 9;
    PRINT 'Updated: ST eRegistry';
END
ELSE
BEGIN
    INSERT INTO [dbo].[user_roles] ([name], [guard_name], [description], [department_id], [level], [user_type], [is_active], [created_at], [updated_at])
    VALUES ('ST eRegistry', 'web', 'Physical Planning - Sectional Titling Electronic Registry access', 9, 'High', 'Operations', 1, GETDATE(), GETDATE());
    PRINT 'Inserted: ST eRegistry';
END

-- 4. ST Applications for Memo
IF EXISTS (SELECT 1 FROM [dbo].[user_roles] WHERE [name] = 'ST Applications for Memo' AND [department_id] = 9)
BEGIN
    UPDATE [dbo].[user_roles] 
    SET 
        [description] = 'Physical Planning - Sectional Titling Applications for Memo processing',
        [level] = 'High',
        [user_type] = 'Operations',
        [is_active] = 1,
        [updated_at] = GETDATE()
    WHERE [name] = 'ST Applications for Memo' AND [department_id] = 9;
    PRINT 'Updated: ST Applications for Memo';
END
ELSE
BEGIN
    INSERT INTO [dbo].[user_roles] ([name], [guard_name], [description], [department_id], [level], [user_type], [is_active], [created_at], [updated_at])
    VALUES ('ST Applications for Memo', 'web', 'Physical Planning - Sectional Titling Applications for Memo processing', 9, 'High', 'Operations', 1, GETDATE(), GETDATE());
    PRINT 'Inserted: ST Applications for Memo';
END

-- 5. Bills & Payments
IF EXISTS (SELECT 1 FROM [dbo].[user_roles] WHERE [name] = 'Bills & Payments' AND [department_id] = 9)
BEGIN
    UPDATE [dbo].[user_roles] 
    SET 
        [description] = 'Physical Planning - Bills and Payments management',
        [level] = 'High',
        [user_type] = 'Operations',
        [is_active] = 1,
        [updated_at] = GETDATE()
    WHERE [name] = 'Bills & Payments' AND [department_id] = 9;
    PRINT 'Updated: Bills & Payments';
END
ELSE
BEGIN
    INSERT INTO [dbo].[user_roles] ([name], [guard_name], [description], [department_id], [level], [user_type], [is_active], [created_at], [updated_at])
    VALUES ('Bills & Payments', 'web', 'Physical Planning - Bills and Payments management', 9, 'High', 'Operations', 1, GETDATE(), GETDATE());
    PRINT 'Inserted: Bills & Payments';
END

-- 6. PP Director
IF EXISTS (SELECT 1 FROM [dbo].[user_roles] WHERE [name] = 'PP Director' AND [department_id] = 9)
BEGIN
    UPDATE [dbo].[user_roles] 
    SET 
        [description] = 'Physical Planning - Director level access and approvals',
        [level] = 'Highest',
        [user_type] = 'Management',
        [is_active] = 1,
        [updated_at] = GETDATE()
    WHERE [name] = 'PP Director' AND [department_id] = 9;
    PRINT 'Updated: PP Director';
END
ELSE
BEGIN
    INSERT INTO [dbo].[user_roles] ([name], [guard_name], [description], [department_id], [level], [user_type], [is_active], [created_at], [updated_at])
    VALUES ('PP Director', 'web', 'Physical Planning - Director level access and approvals', 9, 'Highest', 'Management', 1, GETDATE(), GETDATE());
    PRINT 'Inserted: PP Director';
END

-- 7. PP - SLTR Applications
IF EXISTS (SELECT 1 FROM [dbo].[user_roles] WHERE [name] = 'PP - SLTR Applications' AND [department_id] = 9)
BEGIN
    UPDATE [dbo].[user_roles] 
    SET 
        [description] = 'Physical Planning - SLTR Applications processing',
        [level] = 'High',
        [user_type] = 'Operations',
        [is_active] = 1,
        [updated_at] = GETDATE()
    WHERE [name] = 'PP - SLTR Applications' AND [department_id] = 9;
    PRINT 'Updated: PP - SLTR Applications';
END
ELSE
BEGIN
    INSERT INTO [dbo].[user_roles] ([name], [guard_name], [description], [department_id], [level], [user_type], [is_active], [created_at], [updated_at])
    VALUES ('PP - SLTR Applications', 'web', 'Physical Planning - SLTR Applications processing', 9, 'High', 'Operations', 1, GETDATE(), GETDATE());
    PRINT 'Inserted: PP - SLTR Applications';
END

-- 8. PP Reports
IF EXISTS (SELECT 1 FROM [dbo].[user_roles] WHERE [name] = 'PP Reports' AND [department_id] = 9)
BEGIN
    UPDATE [dbo].[user_roles] 
    SET 
        [description] = 'Physical Planning - Reports generation and management',
        [level] = 'Highest',
        [user_type] = 'Management',
        [is_active] = 1,
        [updated_at] = GETDATE()
    WHERE [name] = 'PP Reports' AND [department_id] = 9;
    PRINT 'Updated: PP Reports';
END
ELSE
BEGIN
    INSERT INTO [dbo].[user_roles] ([name], [guard_name], [description], [department_id], [level], [user_type], [is_active], [created_at], [updated_at])
    VALUES ('PP Reports', 'web', 'Physical Planning - Reports generation and management', 9, 'Highest', 'Management', 1, GETDATE(), GETDATE());
    PRINT 'Inserted: PP Reports';
END

-- 9. PHYSICAL PLANNING (Master role)
IF EXISTS (SELECT 1 FROM [dbo].[user_roles] WHERE [name] = 'PHYSICAL PLANNING' AND [department_id] = 9)
BEGIN
    UPDATE [dbo].[user_roles] 
    SET 
        [description] = 'Physical Planning - Master role with full department access',
        [level] = 'Highest',
        [user_type] = 'Management',
        [is_active] = 1,
        [updated_at] = GETDATE()
    WHERE [name] = 'PHYSICAL PLANNING' AND [department_id] = 9;
    PRINT 'Updated: PHYSICAL PLANNING';
END
ELSE
BEGIN
    INSERT INTO [dbo].[user_roles] ([name], [guard_name], [description], [department_id], [level], [user_type], [is_active], [created_at], [updated_at])
    VALUES ('PHYSICAL PLANNING', 'web', 'Physical Planning - Master role with full department access', 9, 'Highest', 'Management', 1, GETDATE(), GETDATE());
    PRINT 'Inserted: PHYSICAL PLANNING';
END

GO

-- Query to verify the updated roles
PRINT '--- Physical Planning Roles (Department ID: 9) ---';
SELECT 
    [id],
    [name],
    [guard_name],
    [description],
    [department_id],
    [level],
    [user_type],
    [is_active],
    [created_at],
    [updated_at]
FROM [klas].[dbo].[user_roles]
WHERE [department_id] = 9
ORDER BY [name];

PRINT 'Physical Planning user roles update completed successfully!';