-- Add new columns for the primaryform bypass functionality
-- Run this manually in SQL Server Management Studio

USE [your_database_name];  -- Replace with your actual database name

-- Check if columns exist before adding them
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'scheme_number')
BEGIN
    ALTER TABLE [dbo].[mother_applications] ADD [scheme_number] NVARCHAR(255) NULL;
    PRINT 'Added scheme_number column';
END
ELSE
BEGIN
    PRINT 'scheme_number column already exists';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'property_house_no')
BEGIN
    ALTER TABLE [dbo].[mother_applications] ADD [property_house_no] NVARCHAR(255) NULL;
    PRINT 'Added property_house_no column';
END
ELSE
BEGIN
    PRINT 'property_house_no column already exists';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'applied_file_number')
BEGIN
    ALTER TABLE [dbo].[mother_applications] ADD [applied_file_number] NVARCHAR(255) NULL;
    PRINT 'Added applied_file_number column';
END
ELSE
BEGIN
    PRINT 'applied_file_number column already exists';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'selected_file_id')
BEGIN
    ALTER TABLE [dbo].[mother_applications] ADD [selected_file_id] NVARCHAR(255) NULL;
    PRINT 'Added selected_file_id column';
END
ELSE
BEGIN
    PRINT 'selected_file_id column already exists';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'selected_file_type')
BEGIN
    ALTER TABLE [dbo].[mother_applications] ADD [selected_file_type] NVARCHAR(255) NULL;
    PRINT 'Added selected_file_type column';
END
ELSE
BEGIN
    PRINT 'selected_file_type column already exists';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'selected_file_data')
BEGIN
    ALTER TABLE [dbo].[mother_applications] ADD [selected_file_data] NTEXT NULL;
    PRINT 'Added selected_file_data column';
END
ELSE
BEGIN
    PRINT 'selected_file_data column already exists';
END

PRINT 'Database schema update completed!';