-- SQL Script to fix missing or misnamed columns in file_history_staging
-- Run this script in your SQL Server database management tool

USE [klas];
GO

-- 1. Fix SerialNo -> serialNo
IF EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'SerialNo' AND Object_ID = Object_ID(N'[dbo].[file_history_staging]'))
BEGIN
    PRINT 'Renaming SerialNo to serialNo';
    EXEC sp_rename '[dbo].[file_history_staging].SerialNo', 'serialNo', 'COLUMN';
END
ELSE IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'serialNo' AND Object_ID = Object_ID(N'[dbo].[file_history_staging]'))
BEGIN
    PRINT 'Adding missing column: serialNo';
    ALTER TABLE [dbo].[file_history_staging] ADD serialNo NVARCHAR(255) NULL;
END

-- 2. Fix PageNo -> pageNo
IF EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'PageNo' AND Object_ID = Object_ID(N'[dbo].[file_history_staging]'))
BEGIN
    PRINT 'Renaming PageNo to pageNo';
    EXEC sp_rename '[dbo].[file_history_staging].PageNo', 'pageNo', 'COLUMN';
END
ELSE IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'pageNo' AND Object_ID = Object_ID(N'[dbo].[file_history_staging]'))
BEGIN
    PRINT 'Adding missing column: pageNo';
    ALTER TABLE [dbo].[file_history_staging] ADD pageNo NVARCHAR(255) NULL;
END

-- 3. Fix VolNo -> volumeNo (Note the name change from Vol to Volume matching code)
IF EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'VolNo' AND Object_ID = Object_ID(N'[dbo].[file_history_staging]'))
BEGIN
    PRINT 'Renaming VolNo to volumeNo';
    EXEC sp_rename '[dbo].[file_history_staging].VolNo', 'volumeNo', 'COLUMN';
END
ELSE IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'volumeNo' AND Object_ID = Object_ID(N'[dbo].[file_history_staging]'))
BEGIN
    PRINT 'Adding missing column: volumeNo';
    ALTER TABLE [dbo].[file_history_staging] ADD volumeNo NVARCHAR(255) NULL;
END

-- 4. Ensure party columns exist (just in case)
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'party_1' AND Object_ID = Object_ID(N'[dbo].[file_history_staging]'))
BEGIN
    PRINT 'Adding missing column: party_1';
    ALTER TABLE [dbo].[file_history_staging] ADD party_1 NVARCHAR(max) NULL;
END

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'party_2' AND Object_ID = Object_ID(N'[dbo].[file_history_staging]'))
BEGIN
    PRINT 'Adding missing column: party_2';
    ALTER TABLE [dbo].[file_history_staging] ADD party_2 NVARCHAR(max) NULL;
END

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'party_3' AND Object_ID = Object_ID(N'[dbo].[file_history_staging]'))
BEGIN
    PRINT 'Adding missing column: party_3';
    ALTER TABLE [dbo].[file_history_staging] ADD party_3 NVARCHAR(max) NULL;
END

PRINT 'Schema check and fix completed.';
GO
