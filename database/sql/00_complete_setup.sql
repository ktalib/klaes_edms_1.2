-- ============================================
-- MLS File Number Generator - Complete Setup
-- ============================================
-- This script runs all setup scripts in order
-- Usage: Execute this in SQL Server Management Studio
-- ============================================

PRINT '========================================';
PRINT 'MLS File Number Generator - Database Setup';
PRINT 'Started at: ' + CONVERT(NVARCHAR(30), GETDATE(), 120);
PRINT '========================================';
GO

-- ============================================
-- Step 1: Create mls_file_no table
-- ============================================
PRINT '';
PRINT 'Step 1: Creating mls_file_no table...';
GO

CREATE TABLE dbo.mls_file_no (
    id BIGINT IDENTITY(1,1) PRIMARY KEY,
    land_use NVARCHAR(50) NOT NULL,
    year INT NOT NULL,
    serial_number INT NOT NULL,
    full_file_number NVARCHAR(100) NOT NULL UNIQUE,
    file_name NVARCHAR(500) NULL,
    plot_no NVARCHAR(100) NULL,
    tp_no NVARCHAR(100) NULL,
    location NVARCHAR(MAX) NULL,
    tracking_id NVARCHAR(50) NULL,
    file_option NVARCHAR(50) NULL,
    created_by NVARCHAR(255) NOT NULL,
    created_at DATETIME2 NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME2 NOT NULL DEFAULT GETDATE(),
    is_deleted BIT NOT NULL DEFAULT 0
);

CREATE INDEX idx_mls_file_no_land_use ON dbo.mls_file_no(land_use);
CREATE INDEX idx_mls_file_no_year ON dbo.mls_file_no(year);
CREATE INDEX idx_mls_file_no_full_file_number ON dbo.mls_file_no(full_file_number);
CREATE INDEX idx_land_use_year_serial ON dbo.mls_file_no(land_use, year, serial_number);

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Central registry for MLS-generated file numbers with land use tracking', 
    @level0type = N'SCHEMA', @level0name = 'dbo',
    @level1type = N'TABLE',  @level1name = 'mls_file_no';

PRINT '✓ Table mls_file_no created successfully';
GO

-- ============================================
-- Step 2: Create mls_serial_control table
-- ============================================
PRINT '';
PRINT 'Step 2: Creating mls_serial_control table...';
GO

CREATE TABLE dbo.mls_serial_control (
    id BIGINT IDENTITY(1,1) PRIMARY KEY,
    land_use NVARCHAR(50) NOT NULL,
    year INT NOT NULL,
    last_serial INT NOT NULL DEFAULT 0,
    is_initialized BIT NOT NULL DEFAULT 0,
    initialized_at DATETIME2 NULL,
    initialized_by NVARCHAR(255) NULL,
    is_locked BIT NOT NULL DEFAULT 0,
    created_at DATETIME2 NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME2 NOT NULL DEFAULT GETDATE(),
    CONSTRAINT uniq_land_use_year UNIQUE (land_use, year)
);

CREATE INDEX idx_mls_serial_control_land_use ON dbo.mls_serial_control(land_use);
CREATE INDEX idx_mls_serial_control_year ON dbo.mls_serial_control(year);

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Serial number control table for managing last used serial per land use and year', 
    @level0type = N'SCHEMA', @level0name = 'dbo',
    @level1type = N'TABLE',  @level1name = 'mls_serial_control';

PRINT '✓ Table mls_serial_control created successfully';
GO

-- ============================================
-- Step 3: Seed initial data
-- ============================================
PRINT '';
PRINT 'Step 3: Seeding initial serial control data...';
GO

DECLARE @currentYear INT = YEAR(GETDATE());
DECLARE @now DATETIME2 = GETDATE();

MERGE INTO dbo.mls_serial_control AS target
USING (
    SELECT 'RES' AS land_use, @currentYear AS year, 564 AS last_serial UNION ALL
    SELECT 'COM', @currentYear, 76 UNION ALL
    SELECT 'IND', @currentYear, 0 UNION ALL
    SELECT 'AG', @currentYear, 0 UNION ALL
    SELECT 'RES-RC', @currentYear, 0 UNION ALL
    SELECT 'COM-RC', @currentYear, 0 UNION ALL
    SELECT 'AG-RC', @currentYear, 0 UNION ALL
    SELECT 'IND-RC', @currentYear, 0 UNION ALL
    SELECT 'CON-RES', @currentYear, 0 UNION ALL
    SELECT 'CON-COM', @currentYear, 0 UNION ALL
    SELECT 'CON-IND', @currentYear, 0 UNION ALL
    SELECT 'CON-AG', @currentYear, 0 UNION ALL
    SELECT 'CON-RES-RC', @currentYear, 0 UNION ALL
    SELECT 'CON-COM-RC', @currentYear, 0 UNION ALL
    SELECT 'CON-AG-RC', @currentYear, 0
) AS source (land_use, year, last_serial)
ON (target.land_use = source.land_use AND target.year = source.year)
WHEN MATCHED THEN
    UPDATE SET 
        last_serial = source.last_serial,
        is_initialized = 1,
        initialized_at = @now,
        initialized_by = 'System Migration',
        is_locked = 0,
        updated_at = @now
WHEN NOT MATCHED THEN
    INSERT (land_use, year, last_serial, is_initialized, initialized_at, initialized_by, is_locked, created_at, updated_at)
    VALUES (source.land_use, source.year, source.last_serial, 1, @now, 'System Migration', 0, @now, @now);

PRINT '✓ Seeded 15 land-use categories for year ' + CAST(@currentYear AS NVARCHAR(4));
PRINT '  - RES: 564';
PRINT '  - COM: 76';
PRINT '  - Others: 0';
GO

-- ============================================
-- Verification
-- ============================================
PRINT '';
PRINT '========================================';
PRINT 'Verification:';
PRINT '========================================';
GO

-- Count records in each table
DECLARE @mlsFileNoCount INT;
DECLARE @serialControlCount INT;

SELECT @mlsFileNoCount = COUNT(*) FROM dbo.mls_file_no;
SELECT @serialControlCount = COUNT(*) FROM dbo.mls_serial_control;

PRINT 'mls_file_no records: ' + CAST(@mlsFileNoCount AS NVARCHAR(10));
PRINT 'mls_serial_control records: ' + CAST(@serialControlCount AS NVARCHAR(10));
PRINT '';

-- Display serial control data
SELECT 
    land_use AS [Land Use],
    year AS [Year],
    last_serial AS [Last Serial],
    CASE WHEN is_initialized = 1 THEN 'Yes' ELSE 'No' END AS [Initialized],
    CASE WHEN is_locked = 1 THEN 'Yes' ELSE 'No' END AS [Locked],
    initialized_by AS [Initialized By],
    CONVERT(NVARCHAR(20), initialized_at, 120) AS [Initialized At]
FROM dbo.mls_serial_control
ORDER BY land_use;

PRINT '';
PRINT '========================================';
PRINT 'Setup completed successfully!';
PRINT 'Finished at: ' + CONVERT(NVARCHAR(30), GETDATE(), 120);
PRINT '========================================';
GO
