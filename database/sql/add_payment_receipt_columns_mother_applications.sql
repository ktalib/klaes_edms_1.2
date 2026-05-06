-- Add individual payment tracking columns to mother_applications table
-- This enables separate tracking of Application Fee, Processing Fee, and Site Plan Fee payments

USE [klaes_gis_edms];

PRINT 'Adding individual payment tracking columns to mother_applications table...';

-- Add Application Fee Payment Fields
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'application_fee_payment_date')
BEGIN
    PRINT 'Adding application_fee_payment_date column...';
    ALTER TABLE [dbo].[mother_applications]
    ADD [application_fee_payment_date] DATE NULL;
END
ELSE
    PRINT 'application_fee_payment_date column already exists.';

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'application_fee_receipt_number')
BEGIN
    PRINT 'Adding application_fee_receipt_number column...';
    ALTER TABLE [dbo].[mother_applications]
    ADD [application_fee_receipt_number] NVARCHAR(100) NULL;
END
ELSE
    PRINT 'application_fee_receipt_number column already exists.';

-- Add Processing Fee Payment Fields
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'processing_fee_payment_date')
BEGIN
    PRINT 'Adding processing_fee_payment_date column...';
    ALTER TABLE [dbo].[mother_applications]
    ADD [processing_fee_payment_date] DATE NULL;
END
ELSE
    PRINT 'processing_fee_payment_date column already exists.';

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'processing_fee_receipt_number')
BEGIN
    PRINT 'Adding processing_fee_receipt_number column...';
    ALTER TABLE [dbo].[mother_applications]
    ADD [processing_fee_receipt_number] NVARCHAR(100) NULL;
END
ELSE
    PRINT 'processing_fee_receipt_number column already exists.';

-- Add Site Plan Fee Payment Fields
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'site_plan_fee_payment_date')
BEGIN
    PRINT 'Adding site_plan_fee_payment_date column...';
    ALTER TABLE [dbo].[mother_applications]
    ADD [site_plan_fee_payment_date] DATE NULL;
END
ELSE
    PRINT 'site_plan_fee_payment_date column already exists.';

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'site_plan_fee_receipt_number')
BEGIN
    PRINT 'Adding site_plan_fee_receipt_number column...';
    ALTER TABLE [dbo].[mother_applications]
    ADD [site_plan_fee_receipt_number] NVARCHAR(100) NULL;
END
ELSE
    PRINT 'site_plan_fee_receipt_number column already exists.';

-- Add index for payment date queries (performance optimization)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'IX_mother_applications_payment_dates')
BEGIN
    PRINT 'Creating index for payment dates...';
    CREATE NONCLUSTERED INDEX [IX_mother_applications_payment_dates]
    ON [dbo].[mother_applications] (
        [application_fee_payment_date],
        [processing_fee_payment_date],
        [site_plan_fee_payment_date]
    );
END
ELSE
    PRINT 'Payment dates index already exists.';

PRINT 'Verifying columns were added successfully...';

-- Verification query
SELECT 
    'application_fee_payment_date' as ColumnName,
    CASE WHEN EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'application_fee_payment_date') 
        THEN 'EXISTS' ELSE 'MISSING' END as Status
UNION ALL
SELECT 
    'application_fee_receipt_number' as ColumnName,
    CASE WHEN EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'application_fee_receipt_number') 
        THEN 'EXISTS' ELSE 'MISSING' END as Status
UNION ALL
SELECT 
    'processing_fee_payment_date' as ColumnName,
    CASE WHEN EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'processing_fee_payment_date') 
        THEN 'EXISTS' ELSE 'MISSING' END as Status
UNION ALL
SELECT 
    'processing_fee_receipt_number' as ColumnName,
    CASE WHEN EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'processing_fee_receipt_number') 
        THEN 'EXISTS' ELSE 'MISSING' END as Status
UNION ALL
SELECT 
    'site_plan_fee_payment_date' as ColumnName,
    CASE WHEN EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'site_plan_fee_payment_date') 
        THEN 'EXISTS' ELSE 'MISSING' END as Status
UNION ALL
SELECT 
    'site_plan_fee_receipt_number' as ColumnName,
    CASE WHEN EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'site_plan_fee_receipt_number') 
        THEN 'EXISTS' ELSE 'MISSING' END as Status;

PRINT 'Individual payment tracking columns setup complete for mother_applications table.';