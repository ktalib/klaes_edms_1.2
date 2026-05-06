-- Fix Land Use Serials Table - Mixed Use Consistency
-- This script fixes the inconsistent Mixed Use entries in the land_use_serials table

USE [your_database_name]; -- Replace with your actual database name

-- First, let's see what we currently have
SELECT * FROM land_use_serials WHERE land_use_type LIKE '%mixed%' OR land_use_type LIKE '%MIXED%';

-- Delete the incorrect "Mixed Use" entry with ST-COM prefix
DELETE FROM land_use_serials 
WHERE land_use_type = 'Mixed Use' AND prefix = 'ST-COM';

-- Update or ensure the correct MIXED entry exists
-- If the MIXED entry doesn't exist, insert it
IF NOT EXISTS (SELECT 1 FROM land_use_serials WHERE land_use_type = 'MIXED' AND year = 2025)
BEGIN
    INSERT INTO land_use_serials (land_use_type, prefix, year, current_serial, created_at, updated_at)
    VALUES ('MIXED', 'ST-MIXED', 2025, 0, GETDATE(), GETDATE());
    PRINT 'Created new MIXED land use entry';
END
ELSE
BEGIN
    -- Update existing entry to ensure correct prefix
    UPDATE land_use_serials 
    SET prefix = 'ST-MIXED', updated_at = GETDATE()
    WHERE land_use_type = 'MIXED' AND year = 2025;
    PRINT 'Updated existing MIXED land use entry';
END

-- Ensure all standard land use types exist for 2025
DECLARE @land_use_types TABLE (
    land_use_type VARCHAR(50),
    prefix VARCHAR(20)
);

INSERT INTO @land_use_types VALUES 
    ('COMMERCIAL', 'ST-COM'),
    ('RESIDENTIAL', 'ST-RES'),
    ('INDUSTRIAL', 'ST-IND'),
    ('MIXED', 'ST-MIXED');

-- Insert missing entries
INSERT INTO land_use_serials (land_use_type, prefix, year, current_serial, created_at, updated_at)
SELECT 
    lt.land_use_type,
    lt.prefix,
    2025,
    0,
    GETDATE(),
    GETDATE()
FROM @land_use_types lt
WHERE NOT EXISTS (
    SELECT 1 FROM land_use_serials ls 
    WHERE ls.land_use_type = lt.land_use_type 
    AND ls.year = 2025
);

-- Show final result
SELECT * FROM land_use_serials WHERE year = 2025 ORDER BY land_use_type;

PRINT 'Land use serials table cleanup completed!';
PRINT 'Expected entries:';
PRINT '- COMMERCIAL → ST-COM';  
PRINT '- RESIDENTIAL → ST-RES';
PRINT '- INDUSTRIAL → ST-IND';
PRINT '- MIXED → ST-MIXED';