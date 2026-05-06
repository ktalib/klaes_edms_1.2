-- SQL Server script to add SUA fields to subapplications table
-- Execute this script directly in SQL Server Management Studio or your preferred SQL client

USE [klas];
GO

-- Check if the subapplications table exists
IF OBJECT_ID('subapplications', 'U') IS NOT NULL
BEGIN
    PRINT 'Adding SUA fields to subapplications table...';
    
    -- Add is_sua_unit column (boolean flag to identify SUA)
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('subapplications') AND name = 'is_sua_unit')
    BEGIN
        ALTER TABLE subapplications 
        ADD is_sua_unit BIT NOT NULL DEFAULT 0;
        PRINT 'Added is_sua_unit column';
    END
    ELSE
    BEGIN
        PRINT 'is_sua_unit column already exists';
    END

    -- Add allocation_source column (State Government, Local Government)
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('subapplications') AND name = 'allocation_source')
    BEGIN
        ALTER TABLE subapplications 
        ADD allocation_source NVARCHAR(255) NULL;
        PRINT 'Added allocation_source column';
    END
    ELSE
    BEGIN
        PRINT 'allocation_source column already exists';
    END

    -- Add allocation_entity column (KSIP, Housing, KUNPDA, LGA Name)
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('subapplications') AND name = 'allocation_entity')
    BEGIN
        ALTER TABLE subapplications 
        ADD allocation_entity NVARCHAR(255) NULL;
        PRINT 'Added allocation_entity column';
    END
    ELSE
    BEGIN
        PRINT 'allocation_entity column already exists';
    END

    -- Add mls_fileno column (Optional MLS file number)
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('subapplications') AND name = 'mls_fileno')
    BEGIN
        ALTER TABLE subapplications 
        ADD mls_fileno NVARCHAR(255) NULL;
        PRINT 'Added mls_fileno column';
    END
    ELSE
    BEGIN
        PRINT 'mls_fileno column already exists';
    END

    -- Add land_use column (For SUA since no mother app to inherit from)
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('subapplications') AND name = 'land_use')
    BEGIN
        ALTER TABLE subapplications 
        ADD land_use NVARCHAR(255) NULL;
        PRINT 'Added land_use column';
    END
    ELSE
    BEGIN
        PRINT 'land_use column already exists';
    END

    -- Add unit_type column (Apartment, Shop, Office, etc.)
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('subapplications') AND name = 'unit_type')
    BEGIN
        ALTER TABLE subapplications 
        ADD unit_type NVARCHAR(255) NULL;
        PRINT 'Added unit_type column';
    END
    ELSE
    BEGIN
        PRINT 'unit_type column already exists';
    END

    -- Add unit_area column (Unit area in square meters)
    IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('subapplications') AND name = 'unit_area')
    BEGIN
        ALTER TABLE subapplications 
        ADD unit_area DECIMAL(10, 2) NULL;
        PRINT 'Added unit_area column';
    END
    ELSE
    BEGIN
        PRINT 'unit_area column already exists';
    END

    PRINT 'SUA fields setup completed successfully!';
    
    -- Show the updated table structure
    PRINT 'Current subapplications table columns:';
    SELECT 
        COLUMN_NAME,
        DATA_TYPE,
        IS_NULLABLE,
        COLUMN_DEFAULT
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'subapplications'
    ORDER BY ORDINAL_POSITION;

END
ELSE
BEGIN
    PRINT 'ERROR: subapplications table does not exist!';
END
GO

-- Optional: Create indexes for better performance on SUA queries
IF OBJECT_ID('subapplications', 'U') IS NOT NULL
BEGIN
    -- Index on is_sua_unit for filtering SUA records
    IF NOT EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID('subapplications') AND name = 'IX_subapplications_is_sua_unit')
    BEGIN
        CREATE INDEX IX_subapplications_is_sua_unit ON subapplications (is_sua_unit);
        PRINT 'Created index on is_sua_unit column';
    END

    -- Index on allocation_source for filtering by allocation type
    IF NOT EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID('subapplications') AND name = 'IX_subapplications_allocation_source')
    BEGIN
        CREATE INDEX IX_subapplications_allocation_source ON subapplications (allocation_source);
        PRINT 'Created index on allocation_source column';
    END

    -- Index on land_use for SUA land use filtering
    IF NOT EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID('subapplications') AND name = 'IX_subapplications_land_use')
    BEGIN
        CREATE INDEX IX_subapplications_land_use ON subapplications (land_use);
        PRINT 'Created index on land_use column';
    END

    PRINT 'Performance indexes created successfully!';
END
GO

PRINT 'SUA database setup script completed!';