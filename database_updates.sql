-- =============================================
-- PART 1: VERIFICATION QUERIES
-- Run these to check the current state
-- =============================================

-- Check for 'prefix' table
SELECT CASE WHEN EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'prefix') 
    THEN 'Exists' ELSE 'Missing' END AS prefix_table_status;

-- Check for 'purpose_id' in 'mls_file_no'
SELECT CASE WHEN EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'mls_file_no' AND COLUMN_NAME = 'purpose_id') 
    THEN 'Exists' ELSE 'Missing' END AS purpose_id_column_status;

-- Check for data in 'land_uses' (Verification)
SELECT id, landuse FROM land_uses;

-- =============================================
-- PART 2: UPDATE SCRIPT (Safe to run multiple times)
-- =============================================

-- 1. Create 'prefix' table if it doesn't exist
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[prefix]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[prefix](
        [id] [bigint] IDENTITY(1,1) NOT NULL,
        [prefix] [nvarchar](255) NOT NULL,
        [land_use_id] [int] NOT NULL,
        [created_at] [datetime] NULL,
        [updated_at] [datetime] NULL,
        CONSTRAINT [PK_prefix] PRIMARY KEY CLUSTERED ([id] ASC)
    );
    PRINT 'Table [prefix] created.';
END
ELSE
BEGIN
    PRINT 'Table [prefix] already exists.';
END
GO

-- 2. Add 'purpose_id' column to 'mls_file_no' table
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'mls_file_no' AND COLUMN_NAME = 'purpose_id')
BEGIN
    ALTER TABLE mls_file_no ADD purpose_id INT NULL;
    PRINT 'Column [purpose_id] added to [mls_file_no].';
END
ELSE
BEGIN
    PRINT 'Column [purpose_id] already exists in [mls_file_no].';
END
GO

-- 3. Drop Foreign Key constraint on 'purposes' table if strict constraint exists
-- (Adjust name 'FK_Purposes_Landuses' if your constraint has a different name)
IF EXISTS (SELECT * FROM sys.foreign_keys WHERE name = 'FK_Purposes_Landuses')
BEGIN
    ALTER TABLE [dbo].[purposes] DROP CONSTRAINT [FK_Purposes_Landuses];
    PRINT 'Foreign Key [FK_Purposes_Landuses] dropped.';
END
GO

-- 4. Populate 'prefix' table based on 'land_uses'
-- This logic mirrors the Seeder: Generates Standard, RC, Conversion, and Conversion-RC prefixes

DECLARE @LandUseID INT;
DECLARE @LandUseName NVARCHAR(255);
DECLARE @BaseCode NVARCHAR(50);

DECLARE landuse_cursor CURSOR FOR 
SELECT id, landuse FROM land_uses;

OPEN landuse_cursor;
FETCH NEXT FROM landuse_cursor INTO @LandUseID, @LandUseName;

WHILE @@FETCH_STATUS = 0
BEGIN
    -- Determine Base Code
        WHEN @LandUseName LIKE '%RESIDENTIAL%' THEN 'RES'
        WHEN @LandUseName LIKE '%COMMERCIAL%' THEN 'COM'
        WHEN @LandUseName LIKE '%INDUSTRIAL%' THEN 'IND'
        WHEN @LandUseName LIKE '%AGRICULTURAL%' THEN 'AG'
        ELSE LEFT(@LandUseName, 3)
    END;

    -- Insert Prefixes if they don't exist
    -- 1. Standard (e.g., RES)
    IF NOT EXISTS (SELECT 1 FROM prefix WHERE land_use_id = @LandUseID AND prefix = @BaseCode)
        INSERT INTO prefix (land_use_id, prefix, created_at, updated_at) VALUES (@LandUseID, @BaseCode, GETDATE(), GETDATE());

    -- 2. RC (e.g., RES-RC)
    IF NOT EXISTS (SELECT 1 FROM prefix WHERE land_use_id = @LandUseID AND prefix = @BaseCode + '-RC')
        INSERT INTO prefix (land_use_id, prefix, created_at, updated_at) VALUES (@LandUseID, @BaseCode + '-RC', GETDATE(), GETDATE());

    -- 3. Conversion (e.g., CON-RES)
    IF NOT EXISTS (SELECT 1 FROM prefix WHERE land_use_id = @LandUseID AND prefix = 'CON-' + @BaseCode)
        INSERT INTO prefix (land_use_id, prefix, created_at, updated_at) VALUES (@LandUseID, 'CON-' + @BaseCode, GETDATE(), GETDATE());

    -- 4. Conversion RC (e.g., CON-RES-RC)
    IF NOT EXISTS (SELECT 1 FROM prefix WHERE land_use_id = @LandUseID AND prefix = 'CON-' + @BaseCode + '-RC')
        INSERT INTO prefix (land_use_id, prefix, created_at, updated_at) VALUES (@LandUseID, 'CON-' + @BaseCode + '-RC', GETDATE(), GETDATE());

    FETCH NEXT FROM landuse_cursor INTO @LandUseID, @LandUseName;
END

CLOSE landuse_cursor;
DEALLOCATE landuse_cursor;
PRINT 'Prefix table populated.';
GO

-- 5. Populate 'purposes' table (Optional Sample Data)
-- This checks if purposes exist for the land use and inserts if empty
-- Note: You might want to customize these values for Production
    -- Truncate and Repopulate with Specified Data
    TRUNCATE TABLE purposes;

    INSERT INTO purposes (landuseid, name, code) VALUES 
    (1, 'RESIDENTIAL', 'RES'),
    (4, 'AGRICULTURAL', 'AGR'),
    (2, 'COMMERCIAL', 'COM'),
    (2, 'COMMERCIAL (HOTEL)', 'COM_WH'),
    (2, 'COMMERCIAL (OFFICES)', 'COM_OFF'),
    (2, 'COMMERCIAL (PETROL FILLING STATION)', 'COM_PFS'),
    (2, 'COMMERCIAL (RICE PROCESSING)', 'COM_RP'),
    (2, 'COMMERCIAL (SCHOOL)', 'COM_SCH'),
    (2, 'COMMERCIAL (SHOPS & PUBLIC CONVENIENCE)', 'COM_SPC'),
    (2, 'COMMERCIAL (SHOPS AND OFFICES)', 'COM_SO'),
    (2, 'COMMERCIAL (SHOPS)', 'COM_SH'),
    (2, 'COMMERCIAL (WAREHOUSE)', 'COM_WH2'),
    (2, 'COMMERCIAL (WORKSHOP AND OFFICES)', 'COM_WO'),
    (2, 'COMMERCIAL AND RESIDENTIAL', 'COM_RES'),
    (3, 'INDUSTRIAL', 'IND'),
    (3, 'INDUSTRIAL (SMALL SCALE)', 'IND_SS'),
    (2, 'RESIDENTIAL AND COMMERCIAL', 'RES_COM'),
    (2, 'RESIDENTIAL/COMMERCIAL', 'RES_COM2'),
    (2, 'RESIDENTIAL/COMMERCIAL LAYOUT', 'RES_COM_L');

CLOSE purpose_cursor;
DEALLOCATE purpose_cursor;
PRINT 'Purposes table populated.';
GO
