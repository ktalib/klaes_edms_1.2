-- Add middle_name column to ST file numbers table
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[st_file_numbers]') AND name = 'middle_name')
BEGIN
    ALTER TABLE [dbo].[st_file_numbers] 
    ADD [middle_name] NVARCHAR(100) NULL
END

-- Add middle_name column to mother_applications table if it exists
IF EXISTS (SELECT * FROM sys.tables WHERE name = 'mother_applications')
BEGIN
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'middle_name')
    BEGIN
        ALTER TABLE [dbo].[mother_applications] 
        ADD [middle_name] NVARCHAR(100) NULL
    END
END

-- Add middle_name column to subapplications table if it exists  
IF EXISTS (SELECT * FROM sys.tables WHERE name = 'subapplications')
BEGIN
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[subapplications]') AND name = 'middle_name')
    BEGIN
        ALTER TABLE [dbo].[subapplications] 
        ADD [middle_name] NVARCHAR(100) NULL
    END
END

-- Add middle_name column to mother_application_draft table if it exists
IF EXISTS (SELECT * FROM sys.tables WHERE name = 'mother_application_draft')
BEGIN
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_application_draft]') AND name = 'middle_name')
    BEGIN
        ALTER TABLE [dbo].[mother_application_draft] 
        ADD [middle_name] NVARCHAR(100) NULL
    END
END

PRINT 'Middle name columns added successfully to all relevant tables.'