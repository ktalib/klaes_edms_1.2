-- =============================================
-- Add Customer Information Fields to file_indexings Table
-- Created: 2025-12-24
-- Description: Adds file_type, dob, nin, tin, rc_no, residence_address, 
--              country_code, and phone columns to the file_indexings table 
--              for storing customer contact and identification information
-- =============================================

USE [your_database_name];
GO

-- Add file_type column (Corporate or Individual)
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'file_type')
BEGIN
    ALTER TABLE [dbo].[file_indexings]
    ADD [file_type] NVARCHAR(20) NULL;
    
    PRINT 'Column file_type added successfully';
END
ELSE
BEGIN
    PRINT 'Column file_type already exists';
END
GO

-- Add dob column (Date of Birth)
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'dob')
BEGIN
    ALTER TABLE [dbo].[file_indexings]
    ADD [dob] DATE NULL;
    
    PRINT 'Column dob added successfully';
END
ELSE
BEGIN
    PRINT 'Column dob already exists';
END
GO

-- Add nin column (National Identification Number - 13 digits)
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'nin')
BEGIN
    ALTER TABLE [dbo].[file_indexings]
    ADD [nin] NVARCHAR(13) NULL;
    
    PRINT 'Column nin added successfully';
END
ELSE
BEGIN
    PRINT 'Column nin already exists';
END
GO

-- Add tin column (Tax Identification Number)
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'tin')
BEGIN
    ALTER TABLE [dbo].[file_indexings]
    ADD [tin] NVARCHAR(50) NULL;
    
    PRINT 'Column tin added successfully';
END
ELSE
BEGIN
    PRINT 'Column tin already exists';
END
GO

-- Add rc_no column (Registration Certificate Number for Corporate entities)
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'rc_no')
BEGIN
    ALTER TABLE [dbo].[file_indexings]
    ADD [rc_no] NVARCHAR(50) NULL;
    
    PRINT 'Column rc_no added successfully';
END
ELSE
BEGIN
    PRINT 'Column rc_no already exists';
END
GO

-- Add residence_address column (Customer's residence address)
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'residence_address')
BEGIN
    ALTER TABLE [dbo].[file_indexings]
    ADD [residence_address] NVARCHAR(500) NULL;
    
    PRINT 'Column residence_address added successfully';
END
ELSE
BEGIN
    PRINT 'Column residence_address already exists';
END
GO

-- Add country_code column (Phone country code, default +234 for Nigeria)
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'country_code')
BEGIN
    ALTER TABLE [dbo].[file_indexings]
    ADD [country_code] NVARCHAR(10) NULL DEFAULT '+234';
    
    PRINT 'Column country_code added successfully';
END
ELSE
BEGIN
    PRINT 'Column country_code already exists';
END
GO

-- Add phone column (Phone number without country code)
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'phone')
BEGIN
    ALTER TABLE [dbo].[file_indexings]
    ADD [phone] NVARCHAR(20) NULL;
    
    PRINT 'Column phone added successfully';
END
ELSE
BEGIN
    PRINT 'Column phone already exists';
END
GO

-- Verify all columns were added
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    CHARACTER_MAXIMUM_LENGTH,
    IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'file_indexings'
    AND COLUMN_NAME IN ('file_type', 'dob', 'nin', 'tin', 'rc_no', 'residence_address', 'country_code', 'phone')
ORDER BY ORDINAL_POSITION;
GO

PRINT 'Migration completed successfully!';
GO
