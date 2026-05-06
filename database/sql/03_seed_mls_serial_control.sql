-- ============================================
-- MLS File Number Generator - Database Setup
-- Seeder: Initial Serial Control Data
-- ============================================
-- Purpose: Pre-populate serial control with known manual records
-- Created: 2026-01-28
-- ============================================

DECLARE @currentYear INT = YEAR(GETDATE());
DECLARE @now DATETIME2 = GETDATE();

-- Insert or update serial control records for all land uses
-- RES: 564, COM: 76, others: 0

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

PRINT 'Serial control data seeded successfully for year ' + CAST(@currentYear AS NVARCHAR(4));
PRINT 'RES initialized at serial 564';
PRINT 'COM initialized at serial 76';
PRINT 'Other land uses initialized at 0';
GO

-- Verify the seeded data
SELECT 
    land_use,
    year,
    last_serial,
    is_initialized,
    is_locked,
    initialized_by,
    initialized_at
FROM dbo.mls_serial_control
ORDER BY land_use;
GO
