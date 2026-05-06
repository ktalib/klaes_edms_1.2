-- Add payment date and receipt number columns for each fee type in subapplications table
-- This script adds individual payment tracking for Processing Fee, Application Fee, and Survey Fee

USE [klas];
GO

-- Add Processing Fee payment columns
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[subapplications]') AND name = 'processing_fee_payment_date')
BEGIN
    ALTER TABLE [dbo].[subapplications] 
    ADD [processing_fee_payment_date] DATE NULL;
    PRINT 'Added processing_fee_payment_date column to subapplications table';
END
ELSE
BEGIN
    PRINT 'processing_fee_payment_date column already exists in subapplications table';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[subapplications]') AND name = 'processing_fee_receipt_no')
BEGIN
    ALTER TABLE [dbo].[subapplications] 
    ADD [processing_fee_receipt_no] NVARCHAR(100) NULL;
    PRINT 'Added processing_fee_receipt_no column to subapplications table';
END
ELSE
BEGIN
    PRINT 'processing_fee_receipt_no column already exists in subapplications table';
END

-- Add Application Fee payment columns
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[subapplications]') AND name = 'application_fee_payment_date')
BEGIN
    ALTER TABLE [dbo].[subapplications] 
    ADD [application_fee_payment_date] DATE NULL;
    PRINT 'Added application_fee_payment_date column to subapplications table';
END
ELSE
BEGIN
    PRINT 'application_fee_payment_date column already exists in subapplications table';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[subapplications]') AND name = 'application_fee_receipt_no')
BEGIN
    ALTER TABLE [dbo].[subapplications] 
    ADD [application_fee_receipt_no] NVARCHAR(100) NULL;
    PRINT 'Added application_fee_receipt_no column to subapplications table';
END
ELSE
BEGIN
    PRINT 'application_fee_receipt_no column already exists in subapplications table';
END

-- Add Survey Fee payment columns
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[subapplications]') AND name = 'survey_fee_payment_date')
BEGIN
    ALTER TABLE [dbo].[subapplications] 
    ADD [survey_fee_payment_date] DATE NULL;
    PRINT 'Added survey_fee_payment_date column to subapplications table';
END
ELSE
BEGIN
    PRINT 'survey_fee_payment_date column already exists in subapplications table';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[subapplications]') AND name = 'survey_fee_receipt_no')
BEGIN
    ALTER TABLE [dbo].[subapplications] 
    ADD [survey_fee_receipt_no] NVARCHAR(100) NULL;
    PRINT 'Added survey_fee_receipt_no column to subapplications table';
END
ELSE
BEGIN
    PRINT 'survey_fee_receipt_no column already exists in subapplications table';
END

-- Optional: Add indexes for better query performance
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID(N'[dbo].[subapplications]') AND name = 'IX_subapplications_payment_dates')
BEGIN
    CREATE INDEX [IX_subapplications_payment_dates] ON [dbo].[subapplications] 
    (
        [processing_fee_payment_date] ASC,
        [application_fee_payment_date] ASC,
        [survey_fee_payment_date] ASC
    );
    PRINT 'Created index IX_subapplications_payment_dates';
END
ELSE
BEGIN
    PRINT 'Index IX_subapplications_payment_dates already exists';
END

-- Check the added columns
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    CHARACTER_MAXIMUM_LENGTH
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'subapplications' 
AND COLUMN_NAME IN (
    'processing_fee_payment_date',
    'processing_fee_receipt_no',
    'application_fee_payment_date', 
    'application_fee_receipt_no',
    'survey_fee_payment_date',
    'survey_fee_receipt_no'
)
ORDER BY COLUMN_NAME;

PRINT 'Subapplications table payment columns update completed successfully!';
GO