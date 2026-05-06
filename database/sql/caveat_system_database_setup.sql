-- =====================================================
-- CAVEAT MANAGEMENT SYSTEM DATABASE IMPLEMENTATION
-- SQL Server Database Scripts
-- Updated: September 7, 2025
-- =====================================================

-- =====================================================
-- FRONTEND INPUT FIELDS MAPPING (for reference)
--
-- Place New Caveat inputs:
-- - encumbrance-type (select)
-- - instrument-type (select)
-- - file_number (hidden/selected via selector)
-- - location (text)
-- - petitioner (text)
-- - grantee (text)
-- - serial-no (number)
-- - page-no (auto, number)
-- - volume-no (number)
-- - registration-number (display-only text)
-- -                                                                                                                                                      start-date (datetime-local)
-- - release-date (date, optional)
-- - instructions (textarea)
-- - remarks (textarea)
-- - caveat-number (display-only, generated)
-- - date-created (display-only, generated)
--
-- Lift Caveat inputs:
-- - lift-release-date (date)
-- - lift-instructions (textarea)
-- - lift-remarks (textarea)
-- =====================================================
GO

-- =====================================================
-- 1. (RE)CREATE CAVEATS TABLE
-- =====================================================

-- Drop existing table if desired (uncomment to force recreation)
IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'caveats')
BEGIN
    DROP TABLE caveats;
END
GO

-- Create the main caveats table aligned with frontend fields
CREATE TABLE caveats (
    id BIGINT IDENTITY(1,1) PRIMARY KEY,

    -- Business identifiers
    caveat_number NVARCHAR(50) NOT NULL UNIQUE,

    -- Classification
    encumbrance_type NVARCHAR(100) NULL,
    instrument_type_id INT NULL,

    -- File number relationship
    file_number_id BIGINT NULL,
    file_number_type NVARCHAR(50) NULL, -- MLSF, KANGIS, New KANGIS, etc.

    -- Registration particulars
    registration_number NVARCHAR(50) NULL, -- e.g. [Serial/Page/Volume]
    serial_no INT NULL,
    page_no INT NULL,
    volume_no INT NULL,

    -- Parties involved
    petitioner NVARCHAR(255) NOT NULL, -- petitioner
    petitioner_address NVARCHAR(MAX) NULL,
   grantee_name NVARCHAR(255) NULL,     -- grantee
   grantee_address NVARCHAR(MAX) NULL,

    -- Property / location
    location NVARCHAR(255) NULL,
    property_description NVARCHAR(MAX) NULL,

    -- Dates and status
    start_date DATETIME2 NOT NULL,        -- corresponds to start-date
    release_date DATE NULL,               -- corresponds to release-date (for lifting)
    status NVARCHAR(20) NOT NULL CONSTRAINT DF_caveats_status DEFAULT 'active',

    -- Narrative
    instructions NVARCHAR(MAX) NULL,
    remarks NVARCHAR(MAX) NULL,

    -- Audit fields
    created_by NVARCHAR(100) NULL,
    updated_by NVARCHAR(100) NULL,
    created_at DATETIME2 NOT NULL CONSTRAINT DF_caveats_created_at DEFAULT (GETDATE()),
    updated_at DATETIME2 NOT NULL CONSTRAINT DF_caveats_updated_at DEFAULT (GETDATE()),

    -- Constraints
    CONSTRAINT CHK_caveats_status CHECK (status IN ('active', 'released', 'lifted', 'expired', 'draft')),
    CONSTRAINT CHK_caveats_page_no CHECK (page_no IS NULL OR page_no > 0),
    CONSTRAINT CHK_caveats_serial_no CHECK (serial_no IS NULL OR serial_no > 0),
    CONSTRAINT CHK_caveats_volume_no CHECK (volume_no IS NULL OR volume_no > 0),
    CONSTRAINT CHK_caveats_dates CHECK (release_date IS NULL OR release_date >= CAST(start_date AS DATE))
);

-- Indexes for query performance
CREATE INDEX IX_caveats_caveat_number ON caveats(caveat_number);
CREATE INDEX IX_caveats_status_start ON caveats(status, start_date);
CREATE INDEX IX_caveats_file_number_id ON caveats(file_number_id);
CREATE INDEX IX_caveats_registration_number ON caveats(registration_number);
CREATE INDEX IX_caveats_petitioner ON caveats(petitioner);
CREATE INDEX IX_caveats_start_date ON caveats(start_date);

-- Add foreign key to InstrumentTypes if available
IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'InstrumentTypes')
BEGIN
    ALTER TABLE caveats 
    ADD CONSTRAINT FK_caveats_instrument_types
    FOREIGN KEY (instrument_type_id) REFERENCES InstrumentTypes(InstrumentTypeID);
END

-- Add foreign key to file number table if available (support both naming conventions)
IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'fileNumber')
BEGIN
    ALTER TABLE caveats 
    ADD CONSTRAINT FK_caveats_fileNumber 
    FOREIGN KEY (file_number_id) REFERENCES fileNumber(id) ON DELETE SET NULL;
END
ELSE IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'file_numbers')
BEGIN
    ALTER TABLE caveats 
    ADD CONSTRAINT FK_caveats_file_numbers 
    FOREIGN KEY (file_number_id) REFERENCES file_numbers(id) ON DELETE SET NULL;
END

PRINT 'Caveats table created successfully';
GO

-- =====================================================
-- 2. ALTER PROPERTY_RECORDS TABLE
-- =====================================================

IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'property_records')
BEGIN
    -- is_caveated
    IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('property_records') AND name = 'is_caveated')
    BEGIN
        ALTER TABLE property_records 
        ADD is_caveated BIT NOT NULL CONSTRAINT DF_property_records_is_caveated DEFAULT 0;
        PRINT 'Added is_caveated column to property_records table';
    END
    ELSE PRINT 'is_caveated column already exists in property_records table';

    -- caveated_comment
    IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('property_records') AND name = 'caveated_comment')
    BEGIN
        ALTER TABLE property_records 
        ADD caveated_comment NVARCHAR(MAX) NULL;
        PRINT 'Added caveated_comment column to property_records table';
    END
    ELSE PRINT 'caveated_comment column already exists in property_records table';

    -- Index
    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('property_records') AND name = 'IX_property_records_is_caveated')
    BEGIN
        CREATE INDEX IX_property_records_is_caveated ON property_records(is_caveated);
        PRINT 'Created index on is_caveated column in property_records table';
    END
END
ELSE
    PRINT 'WARNING: property_records table does not exist. Skipping alterations.';
GO

-- =====================================================
-- 3. ALTER CofO TABLE (supports multiple naming variants)
-- =====================================================
DECLARE @CofOTableName NVARCHAR(128) = NULL;
IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'CofO') SET @CofOTableName = 'CofO';
ELSE IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'cofo') SET @CofOTableName = 'cofo';
ELSE IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'CoFO') SET @CofOTableName = 'CoFO';
ELSE IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'COFO') SET @CofOTableName = 'COFO';

IF @CofOTableName IS NOT NULL
BEGIN
    DECLARE @sql NVARCHAR(MAX);

    -- is_caveated
    IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID(@CofOTableName) AND name = 'is_caveated')
    BEGIN
        SET @sql = 'ALTER TABLE ' + @CofOTableName + ' ADD is_caveated BIT NOT NULL CONSTRAINT DF_' + @CofOTableName + '_is_caveated DEFAULT 0';
        EXEC sp_executesql @sql;
        PRINT 'Added is_caveated column to ' + @CofOTableName + ' table';
    END
    ELSE PRINT 'is_caveated column already exists in ' + @CofOTableName + ' table';

    -- caveated_comment
    IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID(@CofOTableName) AND name = 'caveated_comment')
    BEGIN
        SET @sql = 'ALTER TABLE ' + @CofOTableName + ' ADD caveated_comment NVARCHAR(MAX) NULL';
        EXEC sp_executesql @sql;
        PRINT 'Added caveated_comment column to ' + @CofOTableName + ' table';
    END
    ELSE PRINT 'caveated_comment column already exists in ' + @CofOTableName + ' table';

    -- Index
    DECLARE @indexName NVARCHAR(128) = 'IX_' + @CofOTableName + '_is_caveated';
    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(@CofOTableName) AND name = @indexName)
    BEGIN
        SET @sql = 'CREATE INDEX ' + @indexName + ' ON ' + @CofOTableName + '(is_caveated)';
        EXEC sp_executesql @sql;
        PRINT 'Created index on is_caveated column in ' + @CofOTableName + ' table';
    END
END
ELSE
    PRINT 'WARNING: CofO table does not exist (checked CofO, cofo, CoFO, COFO). Skipping alterations.';
GO

-- =====================================================
-- 4. ALTER REGISTERED_INSTRUMENTS TABLE
-- =====================================================
IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'registered_instruments')
BEGIN
    -- is_caveated
    IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('registered_instruments') AND name = 'is_caveated')
    BEGIN
        ALTER TABLE registered_instruments 
        ADD is_caveated BIT NOT NULL CONSTRAINT DF_registered_instruments_is_caveated DEFAULT 0;
        PRINT 'Added is_caveated column to registered_instruments table';
    END
    ELSE PRINT 'is_caveated column already exists in registered_instruments table';

    -- caveated_comment
    IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('registered_instruments') AND name = 'caveated_comment')
    BEGIN
        ALTER TABLE registered_instruments 
        ADD caveated_comment NVARCHAR(MAX) NULL;
        PRINT 'Added caveated_comment column to registered_instruments table';
    END
    ELSE PRINT 'caveated_comment column already exists in registered_instruments table';

    -- Index
    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('registered_instruments') AND name = 'IX_registered_instruments_is_caveated')
    BEGIN
        CREATE INDEX IX_registered_instruments_is_caveated ON registered_instruments(is_caveated);
        PRINT 'Created index on is_caveated column in registered_instruments table';
    END
END
ELSE
    PRINT 'WARNING: registered_instruments table does not exist. Skipping alterations.';
GO

-- =====================================================
-- 5. OPTIONAL: CREATE file_numbers TABLE (ONLY IF NEITHER fileNumber NOR file_numbers EXISTS)
-- =====================================================
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'fileNumber')
   AND NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'file_numbers')
BEGIN
    CREATE TABLE file_numbers (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        file_number NVARCHAR(100) NOT NULL UNIQUE,
        file_type NVARCHAR(50) NOT NULL, -- MLSF, KANGIS, New KANGIS
        property_type NVARCHAR(100) NULL,
        property_description NVARCHAR(MAX) NULL,
        location NVARCHAR(255) NULL,
        status NVARCHAR(20) NOT NULL CONSTRAINT DF_file_numbers_status DEFAULT 'active',
        created_at DATETIME2 NOT NULL CONSTRAINT DF_file_numbers_created_at DEFAULT (GETDATE()),
        updated_at DATETIME2 NOT NULL CONSTRAINT DF_file_numbers_updated_at DEFAULT (GETDATE()),
        CONSTRAINT CHK_file_numbers_status CHECK (status IN ('active', 'inactive')),
        CONSTRAINT CHK_file_numbers_file_type CHECK (file_type IN ('MLSF', 'KANGIS', 'New KANGIS'))
    );

    CREATE INDEX IX_file_numbers_file_number ON file_numbers(file_number);
    CREATE INDEX IX_file_numbers_file_type ON file_numbers(file_type);
    CREATE INDEX IX_file_numbers_status ON file_numbers(status);

    PRINT 'Created file_numbers table (optional fallback)';

    -- Add FK to caveats if caveats exists
    IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'caveats')
    BEGIN
        ALTER TABLE caveats 
        ADD CONSTRAINT FK_caveats_file_numbers 
        FOREIGN KEY (file_number_id) REFERENCES file_numbers(id) ON DELETE SET NULL;
        PRINT 'Added foreign key between caveats and file_numbers';
    END
END
ELSE
    PRINT 'fileNumber/file_numbers table already exists. Skipping fallback creation.';
GO

-- =====================================================
-- 6. SAMPLE DATA (OPTIONAL)
-- =====================================================
-- Insert a sample file number only if we created the fallback table and it is empty
IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'file_numbers')
BEGIN
    IF NOT EXISTS (SELECT 1 FROM file_numbers)
    BEGIN
        INSERT INTO file_numbers (file_number, file_type, property_type, property_description, location)
        VALUES ('MLSF/2024/001', 'MLSF', 'Residential', 'Sample MLSF Property for Testing', 'Minna, Niger State');
        PRINT 'Inserted sample into file_numbers';
    END
END

-- Insert sample caveat if table is empty
IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'caveats')
BEGIN
    IF NOT EXISTS (SELECT 1 FROM caveats)
    BEGIN
        DECLARE @fileId BIGINT = NULL;
        -- Prefer existing fileNumber table
        IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'fileNumber')
        BEGIN
            SELECT TOP 1 @fileId = id FROM fileNumber WHERE (is_decommissioned IS NULL OR is_decommissioned = 0) ORDER BY id DESC;
        END
        ELSE IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'file_numbers')
        BEGIN
            SELECT TOP 1 @fileId = id FROM file_numbers ORDER BY id DESC;
        END

        INSERT INTO caveats (
            caveat_number, encumbrance_type, instrument_type_id, file_number_id, file_number_type,
            registration_number, serial_no, page_no, volume_no,
            petitioner, petitioner_address,grantee_name,grantee_address,
            location, property_description, start_date, release_date, status,
            instructions, remarks, created_by
        ) VALUES (
            'CAV/2024/0001', 'mortgage', NULL, @fileId, 'MLSF',
            '1/1/2', 1, 1, 2,
            'Musa Ali', '123 Main Street, Lagos, Nigeria', 'Jane Smith', '456 Oak Avenue, Abuja, Nigeria',
            'Sample Location', 'Sample property under caveat for testing purposes', 
            SYSDATETIME(), DATEADD(MONTH, 6, CAST(SYSDATETIME() AS DATE)), 'active',
            'Mortgage for property acquisition loan', 'Sample caveat entry for system testing', 'System Administrator'
        );
        PRINT 'Inserted sample caveat data';
    END
END
GO

-- =====================================================
-- 7. VERIFICATION QUERIES
-- =====================================================
PRINT '=====================================================';
PRINT 'DATABASE IMPLEMENTATION VERIFICATION';
PRINT '=====================================================';

-- Check caveats table
IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'caveats')
BEGIN
    DECLARE @caveatCount INT;
    SELECT @caveatCount = COUNT(*) FROM caveats;
    PRINT 'Caveats table exists with ' + CAST(@caveatCount AS VARCHAR(10)) + ' records';
END
ELSE PRINT 'WARNING: caveats table not found';

-- Check property_records alterations
IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'property_records')
BEGIN
    IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('property_records') AND name = 'is_caveated')
        PRINT 'property_records updated with is_caveated';
    ELSE
        PRINT 'WARNING: property_records missing is_caveated';

    IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('property_records') AND name = 'caveated_comment')
        PRINT 'property_records updated with caveated_comment';
    ELSE
        PRINT 'WARNING: property_records missing caveated_comment';
END

-- Check registered_instruments alterations
IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'registered_instruments')
BEGIN
    IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('registered_instruments') AND name = 'is_caveated')
        PRINT 'registered_instruments updated with is_caveated';
    ELSE
        PRINT 'WARNING: registered_instruments missing is_caveated';

    IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('registered_instruments') AND name = 'caveated_comment')
        PRINT 'registered_instruments updated with caveated_comment';
    ELSE
        PRINT 'WARNING: registered_instruments missing caveated_comment';
END

-- Check CofO table alterations
DECLARE @CofOTableCheck NVARCHAR(128) = NULL;
IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'CofO') SET @CofOTableCheck = 'CofO';
ELSE IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'cofo') SET @CofOTableCheck = 'cofo';
ELSE IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'CoFO') SET @CofOTableCheck = 'CoFO';
ELSE IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'COFO') SET @CofOTableCheck = 'COFO';

IF @CofOTableCheck IS NOT NULL
BEGIN
    DECLARE @checkSql NVARCHAR(MAX);
    DECLARE @exists BIT = 0;
    SET @checkSql = 'IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID(''' + @CofOTableCheck + ''') AND name = ''is_caveated'') SET @exists = 1';
    EXEC sp_executesql @checkSql, N'@exists BIT OUTPUT', @exists OUTPUT;

    IF @exists = 1
        PRINT @CofOTableCheck + ' updated with is_caveated';
    ELSE
        PRINT 'WARNING: ' + @CofOTableCheck + ' missing is_caveated';

    SET @exists = 0;
    SET @checkSql = 'IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID(''' + @CofOTableCheck + ''') AND name = ''caveated_comment'') SET @exists = 1';
    EXEC sp_executesql @checkSql, N'@exists BIT OUTPUT', @exists OUTPUT;

    IF @exists = 1
        PRINT @CofOTableCheck + ' updated with caveated_comment';
    ELSE
        PRINT 'WARNING: ' + @CofOTableCheck + ' missing caveated_comment';
END
ELSE
    PRINT 'WARNING: CofO table not found for verification';

PRINT '=====================================================';
PRINT 'CAVEAT SYSTEM DATABASE IMPLEMENTATION COMPLETED';
PRINT '=====================================================';
GO