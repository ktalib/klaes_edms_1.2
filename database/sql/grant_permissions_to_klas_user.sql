-- ============================================================================
-- GRANT PERMISSIONS TO KLAS USER
-- ============================================================================
-- Run this as SQL Server Administrator (sa or equivalent)
-- This grants necessary permissions to create tables and run migrations

USE [klas];
GO

-- Grant CREATE TABLE permissions
GRANT CREATE TABLE TO [klas];
GO

-- Grant ALTER permissions (for migrations)
GRANT ALTER TO [klas];
GO

-- Grant necessary permissions for DDL operations
GRANT CREATE PROCEDURE TO [klas];
GO
GRANT CREATE VIEW TO [klas];
GO
GRANT CREATE FUNCTION TO [klas];
GO

-- Grant db_ddladmin role (recommended for Laravel migrations)
ALTER ROLE db_ddladmin ADD MEMBER [klas];
GO

-- Grant db_datawriter and db_datareader (if not already granted)
ALTER ROLE db_datawriter ADD MEMBER [klas];
GO
ALTER ROLE db_datareader ADD MEMBER [klas];
GO

PRINT 'Permissions granted to klas user';
PRINT 'You can now run: php artisan migrate --database=sqlsrv';
GO