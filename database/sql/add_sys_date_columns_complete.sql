-- ===================================================================
-- Add sys_date columns to sectional titling tables
-- This will provide reliable chronological sorting
-- ===================================================================

USE [klas];
GO

PRINT 'Starting sys_date column addition process...';

-- ===================================================================
-- 1. Add sys_date column to mother_applications table
-- ===================================================================

IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'mother_applications' 
    AND COLUMN_NAME = 'sys_date'
)
BEGIN
    -- First add the column as nullable
    ALTER TABLE [dbo].[mother_applications]
    ADD [sys_date] DATETIME2(7) NULL;
    
    PRINT '✅ Added sys_date column to mother_applications table';
    
    -- Update all existing records with calculated dates
    UPDATE [dbo].[mother_applications] 
    SET [sys_date] = DATEADD(SECOND, [id], '2024-01-01 00:00:00')
    WHERE [sys_date] IS NULL;
    
    DECLARE @updatedMother1 INT = @@ROWCOUNT;
    PRINT '✅ Updated ' + CAST(@updatedMother1 AS VARCHAR(10)) + ' mother_applications records with calculated sys_date';
    
    -- Make the column NOT NULL and add default constraint
    ALTER TABLE [dbo].[mother_applications]
    ALTER COLUMN [sys_date] DATETIME2(7) NOT NULL;
    
    ALTER TABLE [dbo].[mother_applications]
    ADD CONSTRAINT DF_mother_applications_sys_date DEFAULT GETDATE() FOR [sys_date];
    
    PRINT '✅ Set sys_date column to NOT NULL with GETDATE() default';
END
ELSE
BEGIN
    PRINT 'ℹ️ sys_date column already exists in mother_applications table';
    -- Skip updating since column already exists and should have data
END

-- ===================================================================
-- 2. Add sys_date column to subapplications table  
-- ===================================================================

IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'subapplications' 
    AND COLUMN_NAME = 'sys_date'
)
BEGIN
    -- First add the column as nullable
    ALTER TABLE [dbo].[subapplications]
    ADD [sys_date] DATETIME2(7) NULL;
    
    PRINT '✅ Added sys_date column to subapplications table';
    
    -- Update all existing records with calculated dates
    UPDATE [dbo].[subapplications] 
    SET [sys_date] = DATEADD(SECOND, [id], '2024-01-01 00:00:00')
    WHERE [sys_date] IS NULL;
    
    DECLARE @updatedSub1 INT = @@ROWCOUNT;
    PRINT '✅ Updated ' + CAST(@updatedSub1 AS VARCHAR(10)) + ' subapplications records with calculated sys_date';
    
    -- Make the column NOT NULL and add default constraint
    ALTER TABLE [dbo].[subapplications]
    ALTER COLUMN [sys_date] DATETIME2(7) NOT NULL;
    
    ALTER TABLE [dbo].[subapplications]
    ADD CONSTRAINT DF_subapplications_sys_date DEFAULT GETDATE() FOR [sys_date];
    
    PRINT '✅ Set sys_date column to NOT NULL with GETDATE() default';
END
ELSE
BEGIN
    PRINT 'ℹ️ sys_date column already exists in subapplications table';
    -- Skip updating since column already exists and should have data
END

-- ===================================================================
-- 3. Verification queries
-- ===================================================================

PRINT '';
PRINT '📊 Verification Results:';
PRINT '========================';

-- Check mother_applications
SELECT 
    'mother_applications' as table_name,
    COUNT(*) as total_records,
    MIN(sys_date) as earliest_sys_date,
    MAX(sys_date) as latest_sys_date,
    MIN(id) as min_id,
    MAX(id) as max_id
FROM [dbo].[mother_applications];

-- Check subapplications  
SELECT 
    'subapplications' as table_name,
    COUNT(*) as total_records,
    MIN(sys_date) as earliest_sys_date,
    MAX(sys_date) as latest_sys_date,
    MIN(id) as min_id,
    MAX(id) as max_id
FROM [dbo].[subapplications];

-- Show sample of recent records with proper ordering
PRINT '';
PRINT '📋 Sample of recent mother_applications (by sys_date DESC):';
SELECT TOP 5 
    id, 
    fileno, 
    sys_date,
    CASE 
        WHEN created_at IS NOT NULL THEN created_at 
        ELSE 'NULL' 
    END as original_created_at
FROM [dbo].[mother_applications] 
ORDER BY sys_date DESC;

PRINT '';
PRINT '📋 Sample of recent subapplications (by sys_date DESC):';
SELECT TOP 5 
    id, 
    fileno, 
    sys_date,
    CASE 
        WHEN created_at IS NOT NULL THEN created_at 
        ELSE 'NULL' 
    END as original_created_at
FROM [dbo].[subapplications] 
ORDER BY sys_date DESC;

PRINT '';
PRINT '✅ sys_date column addition process completed successfully!';
PRINT 'ℹ️ New records will automatically get current timestamp';
PRINT 'ℹ️ Update your Laravel controllers to use ORDER BY sys_date DESC';

GO