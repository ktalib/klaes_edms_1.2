-- Caveat System Database Updates
-- This script adds file number fields and caveat_id to support proper caveat functionality

USE [your_database_name]; -- Replace with your actual database name

-- =====================================================
-- 1. ADD FILE NUMBER FIELDS TO CAVEATS TABLE
-- =====================================================
-- Add file number fields directly to caveats table to support manual entries without id
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('caveats') AND name = 'file_number_kangis')
BEGIN
    ALTER TABLE caveats ADD file_number_kangis NVARCHAR(50) NULL;
    PRINT 'Added file_number_kangis to caveats table';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('caveats') AND name = 'file_number_mlsf')
BEGIN
    ALTER TABLE caveats ADD file_number_mlsf NVARCHAR(50) NULL;
    PRINT 'Added file_number_mlsf to caveats table';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('caveats') AND name = 'file_number_new_kangis')
BEGIN
    ALTER TABLE caveats ADD file_number_new_kangis NVARCHAR(50) NULL;
    PRINT 'Added file_number_new_kangis to caveats table';
END

-- =====================================================
-- 2. ADD CAVEAT_ID TO PROPERTY_RECORDS TABLE
-- =====================================================
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('property_records') AND name = 'caveat_id')
BEGIN
    ALTER TABLE property_records ADD caveat_id INT NULL;
    PRINT 'Added caveat_id to property_records table';
END

-- Add foreign key constraint if caveats table exists
IF EXISTS (SELECT * FROM sys.tables WHERE name = 'caveats')
BEGIN
    IF NOT EXISTS (SELECT * FROM sys.foreign_keys WHERE name = 'FK_property_records_caveat_id')
    BEGIN
        ALTER TABLE property_records 
        ADD CONSTRAINT FK_property_records_caveat_id 
        FOREIGN KEY (caveat_id) REFERENCES caveats(id);
        PRINT 'Added foreign key constraint FK_property_records_caveat_id';
    END
END

-- =====================================================
-- 3. ADD CAVEAT_ID TO COFO TABLE (try multiple variations)
-- =====================================================
-- Try different possible table names for CofO
DECLARE @cofo_table_name NVARCHAR(50) = NULL;

IF EXISTS (SELECT * FROM sys.tables WHERE name = 'CofO')
    SET @cofo_table_name = 'CofO';
ELSE IF EXISTS (SELECT * FROM sys.tables WHERE name = 'cofo')
    SET @cofo_table_name = 'cofo';
ELSE IF EXISTS (SELECT * FROM sys.tables WHERE name = 'CoFO')
    SET @cofo_table_name = 'CoFO';
ELSE IF EXISTS (SELECT * FROM sys.tables WHERE name = 'COFO')
    SET @cofo_table_name = 'COFO';

IF @cofo_table_name IS NOT NULL
BEGIN
    DECLARE @sql_cofo NVARCHAR(MAX);
    
    -- Check if caveat_id column exists
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(@cofo_table_name) AND name = 'caveat_id')
    BEGIN
        SET @sql_cofo = 'ALTER TABLE ' + @cofo_table_name + ' ADD caveat_id INT NULL';
        EXEC sp_executesql @sql_cofo;
        PRINT 'Added caveat_id to ' + @cofo_table_name + ' table';
    END
    
    -- Add foreign key constraint
    DECLARE @fk_name NVARCHAR(100) = 'FK_' + @cofo_table_name + '_caveat_id';
    IF NOT EXISTS (SELECT * FROM sys.foreign_keys WHERE name = @fk_name)
    BEGIN
        SET @sql_cofo = 'ALTER TABLE ' + @cofo_table_name + ' ADD CONSTRAINT ' + @fk_name + ' FOREIGN KEY (caveat_id) REFERENCES caveats(id)';
        EXEC sp_executesql @sql_cofo;
        PRINT 'Added foreign key constraint ' + @fk_name;
    END
END
ELSE
BEGIN
    PRINT 'WARNING: CofO table not found. Please manually add caveat_id column to your CofO table.';
END

-- =====================================================
-- 4. ADD CAVEAT_ID TO REGISTERED_INSTRUMENTS TABLE
-- =====================================================
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('registered_instruments') AND name = 'caveat_id')
BEGIN
    ALTER TABLE registered_instruments ADD caveat_id INT NULL;
    PRINT 'Added caveat_id to registered_instruments table';
END

-- Add foreign key constraint
IF EXISTS (SELECT * FROM sys.tables WHERE name = 'caveats')
BEGIN
    IF NOT EXISTS (SELECT * FROM sys.foreign_keys WHERE name = 'FK_registered_instruments_caveat_id')
    BEGIN
        ALTER TABLE registered_instruments 
        ADD CONSTRAINT FK_registered_instruments_caveat_id 
        FOREIGN KEY (caveat_id) REFERENCES caveats(id);
        PRINT 'Added foreign key constraint FK_registered_instruments_caveat_id';
    END
END

-- =====================================================
-- 5. CREATE INDEXES FOR PERFORMANCE
-- =====================================================
-- Index on file number fields in caveats table
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_caveats_file_numbers')
BEGIN
    CREATE INDEX IX_caveats_file_numbers ON caveats (file_number_kangis, file_number_mlsf, file_number_new_kangis);
    PRINT 'Created index IX_caveats_file_numbers';
END

-- Index on caveat_id in property_records
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_property_records_caveat_id')
BEGIN
    CREATE INDEX IX_property_records_caveat_id ON property_records (caveat_id);
    PRINT 'Created index IX_property_records_caveat_id';
END

-- Index on caveat_id in registered_instruments
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_registered_instruments_caveat_id')
BEGIN
    CREATE INDEX IX_registered_instruments_caveat_id ON registered_instruments (caveat_id);
    PRINT 'Created index IX_registered_instruments_caveat_id';
END

-- =====================================================
-- 6. UPDATE EXISTING DATA (OPTIONAL)
-- =====================================================
-- Migrate existing file_number data to new fields if needed
-- This is commented out - uncomment and modify as needed for your data

/*
-- Example: Update file_number_kangis from existing file_number field
UPDATE caveats 
SET file_number_kangis = file_number 
WHERE file_number IS NOT NULL 
  AND file_number LIKE 'KN%' 
  AND file_number_kangis IS NULL;

-- Example: Update file_number_mlsf from existing file_number field  
UPDATE caveats 
SET file_number_mlsf = file_number 
WHERE file_number IS NOT NULL 
  AND file_number LIKE 'RES-%' 
  AND file_number_mlsf IS NULL;
*/

PRINT '=== Caveat System Database Updates Completed ===';
PRINT 'Please review the changes and test the application.';
PRINT 'Remember to update your database name at the top of this script.';