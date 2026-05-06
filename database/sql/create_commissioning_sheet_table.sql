-- Create File Number Commissioning Sheet Table
-- USE [LandRegistry]
-- GO

-- Drop table if it exists
IF OBJECT_ID('dbo.file_commissioning_sheets', 'U') IS NOT NULL
    DROP TABLE dbo.file_commissioning_sheets;
GO

-- Create the table
CREATE TABLE [dbo].[file_commissioning_sheets] (
    [id] INT IDENTITY(1,1) PRIMARY KEY,
    [file_number] NVARCHAR(255) NOT NULL,
    [file_name] NVARCHAR(500) NULL,
    [name_or_allottee] NVARCHAR(500) NULL,
    [plot_number] NVARCHAR(255) NULL,
    [tp_number] NVARCHAR(255) NULL,
    [location] NVARCHAR(500) NULL,
    [date_created] DATETIME2 DEFAULT GETDATE(),
    [created_by] NVARCHAR(255) NULL,
    [created_by_signature] NVARCHAR(500) NULL, -- Path to signature image or base64
    [approved_by] NVARCHAR(255) NULL,
    [approved_by_signature] NVARCHAR(500) NULL, -- Path to signature image or base64
    [status] NVARCHAR(50) DEFAULT 'Draft', -- Draft, Approved, Printed
    [created_at] DATETIME2 DEFAULT GETDATE(),
    [updated_at] DATETIME2 DEFAULT GETDATE(),
    [created_user_id] INT NULL,
    [approved_user_id] INT NULL
);
GO

-- Create indexes for better performance
CREATE INDEX IX_file_commissioning_sheets_file_number ON [dbo].[file_commissioning_sheets] ([file_number]);
CREATE INDEX IX_file_commissioning_sheets_status ON [dbo].[file_commissioning_sheets] ([status]);
CREATE INDEX IX_file_commissioning_sheets_created_at ON [dbo].[file_commissioning_sheets] ([created_at]);
GO

-- Add some sample data for testing
INSERT INTO [dbo].[file_commissioning_sheets] 
([file_number], [file_name], [name_or_allottee], [plot_number], [tp_number], [location], [created_by], [status])
VALUES 
('MLS/2025/001', 'Sample Land Document', 'Musa Ali', 'Plot 123', 'TP456', 'Victoria Island, Lagos', 'System Administrator', 'Draft'),
('MLS/2025/002', 'Property Title Certificate', 'Jane Smith', 'Plot 789', 'TP012', 'Ikoyi, Lagos', 'System Administrator', 'Draft');
GO

PRINT 'File Commissioning Sheets table created successfully!';
