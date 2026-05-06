-- Create SQL Server login and user for Laravel
-- Execute this script in SQL Server Management Studio as an administrator

-- 1. Create the SQL Server login (if it doesn't exist)
IF NOT EXISTS (SELECT * FROM sys.server_principals WHERE name = 'klasUser')
BEGIN
    CREATE LOGIN klasUser WITH PASSWORD = '12WithStrongPassword';
    PRINT 'Login created: klasUser';
END
ELSE
BEGIN
    PRINT 'Login already exists: klasUser';
END

-- 2. Create the database user mapped to the login
USE klas;
GO

IF NOT EXISTS (SELECT * FROM sys.database_principals WHERE name = 'klasUser')
BEGIN
    CREATE USER klasUser FOR LOGIN klasUser;
    PRINT 'Database user created: klasUser';
END
ELSE
BEGIN
    PRINT 'Database user already exists: klasUser';
END

-- 3. Grant all permissions to the user
ALTER ROLE db_owner ADD MEMBER klasUser;
PRINT 'User added to db_owner role';

-- Grant explicit permissions
GRANT CREATE TABLE TO klasUser;
GRANT ALTER ON SCHEMA::dbo TO klasUser;
GRANT CREATE SCHEMA TO klasUser;
GRANT EXECUTE TO klasUser;

PRINT 'All permissions granted to klasUser';

-- 4. Verify the user and permissions
PRINT '';
PRINT '=== Verification ===';
SELECT 'Login Name' = name, 'Type' = type_desc FROM sys.server_principals WHERE name = 'klasUser';
SELECT 'Database User' = name, 'Type' = type_desc FROM sys.database_principals WHERE name = 'klasUser';
SELECT 'Roles' = dp.name FROM sys.database_role_members drm 
INNER JOIN sys.database_principals dp ON drm.role_principal_id = dp.principal_id
WHERE drm.member_principal_id = (SELECT principal_id FROM sys.database_principals WHERE name = 'klasUser');
