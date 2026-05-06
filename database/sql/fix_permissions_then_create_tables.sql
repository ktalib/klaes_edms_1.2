-- ============================================================================
-- RUN AS DATABASE ADMINISTRATOR 
-- ============================================================================
-- This creates the file_number_reservations table directly as admin user
-- Bypasses Laravel migration permission issues

-- First grant permissions
USE [klas];
GO

GRANT CREATE TABLE TO [klas];
GRANT ALTER TO [klas];
ALTER ROLE db_ddladmin ADD MEMBER [klas];
GO

-- Then create the reservation table directly
-- (You can run create_file_number_reservations.sql after this)

PRINT 'Run this first, then run create_file_number_reservations.sql';
GO