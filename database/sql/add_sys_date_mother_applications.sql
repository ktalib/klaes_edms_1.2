-- Add sys_date column to mother_applications table
-- This column will automatically capture the date and time when a record is created

-- Step 1: Check if column exists and add it if it doesn't
IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'mother_applications' 
    AND COLUMN_NAME = 'sys_date'
)
BEGIN
    -- First add the column as nullable
    ALTER TABLE [dbo].[mother_applications]
    ADD [sys_date] DATETIME2(7) NULL;
    
    PRINT 'Added sys_date column to mother_applications table';
    
    -- Step 2: Update all existing records with calculated dates
    UPDATE [dbo].[mother_applications] 
    SET [sys_date] = DATEADD(SECOND, [id], '2024-01-01 00:00:00')
    WHERE [sys_date] IS NULL;
    
    PRINT 'Updated existing mother_applications records with calculated sys_date';
    
    -- Step 3: Make the column NOT NULL and add default constraint
    ALTER TABLE [dbo].[mother_applications]
    ALTER COLUMN [sys_date] DATETIME2(7) NOT NULL;
    
    ALTER TABLE [dbo].[mother_applications]
    ADD CONSTRAINT DF_mother_applications_sys_date DEFAULT GETDATE() FOR [sys_date];
    
    PRINT 'Set sys_date column to NOT NULL with GETDATE() default';
END
ELSE
BEGIN
    PRINT 'sys_date column already exists in mother_applications table';
    
    -- Update any NULL values that might exist
    UPDATE [dbo].[mother_applications] 
    SET [sys_date] = DATEADD(SECOND, [id], '2024-01-01 00:00:00')
    WHERE [sys_date] IS NULL;
    
    PRINT 'Updated any NULL sys_date values in existing records';
END