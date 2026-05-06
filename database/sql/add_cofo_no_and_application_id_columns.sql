-- Add cofo_no and application_id columns to CofO table
-- Script to add CofO Number and Application ID columns to the CofO table

-- Check if CofO table exists and add the columns
DECLARE @CofOTableName NVARCHAR(50);

-- Find the correct CofO table name (case variations)
IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'CofO') SET @CofOTableName = 'CofO';
ELSE IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'cofo') SET @CofOTableName = 'cofo';
ELSE IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'CoFO') SET @CofOTableName = 'CoFO';
ELSE IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'COFO') SET @CofOTableName = 'COFO';

IF @CofOTableName IS NOT NULL
BEGIN
    DECLARE @sql NVARCHAR(MAX);
    
    -- Add cofo_no column if it doesn't exist
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(@CofOTableName) AND name = 'cofo_no')
    BEGIN
        SET @sql = 'ALTER TABLE ' + QUOTENAME(@CofOTableName) + ' ADD cofo_no NVARCHAR(255) NULL';
        EXEC sp_executesql @sql;
        PRINT 'Added cofo_no column to ' + @CofOTableName + ' table';
    END
    ELSE
    BEGIN
        PRINT 'cofo_no column already exists in ' + @CofOTableName + ' table';
    END
    
    -- Add application_id column if it doesn't exist
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(@CofOTableName) AND name = 'application_id')
    BEGIN
        SET @sql = 'ALTER TABLE ' + QUOTENAME(@CofOTableName) + ' ADD application_id INT NULL';
        EXEC sp_executesql @sql;
        PRINT 'Added application_id column to ' + @CofOTableName + ' table';
    END
    ELSE
    BEGIN
        PRINT 'application_id column already exists in ' + @CofOTableName + ' table';
    END
    
    PRINT 'CofO table update completed successfully';
END
ELSE
BEGIN
    PRINT 'CofO table not found - please check table name variations';
END

-- Create index on application_id for better performance
IF @CofOTableName IS NOT NULL
BEGIN
    DECLARE @indexName NVARCHAR(255) = 'IX_' + @CofOTableName + '_application_id';
    IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = @indexName)
    BEGIN
        SET @sql = 'CREATE INDEX ' + QUOTENAME(@indexName) + ' ON ' + QUOTENAME(@CofOTableName) + ' (application_id)';
        EXEC sp_executesql @sql;
        PRINT 'Created index on application_id column';
    END
END