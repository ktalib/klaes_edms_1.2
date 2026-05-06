-- Ensure required columns used by the Joint Site Inspection form exist
-- Run on SQL Server (database: klas)

USE [klas];
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.objects
    WHERE object_id = OBJECT_ID(N'[dbo].[joint_site_inspection_reports]')
      AND type = N'U'
)
BEGIN
    PRINT 'Table joint_site_inspection_reports does not exist. Create it before running this script.';
    RETURN;
END

-- Helper: add column if missing
DECLARE @table NVARCHAR(200) = N'[dbo].[joint_site_inspection_reports]';

-- Address storage
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'applicant_address' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: applicant_address (NVARCHAR(MAX))';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD applicant_address NVARCHAR(MAX) NULL;
END
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'applicant_address_components' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: applicant_address_components (NVARCHAR(MAX))';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD applicant_address_components NVARCHAR(MAX) NULL;
END
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'location_components' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: location_components (NVARCHAR(MAX))';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD location_components NVARCHAR(MAX) NULL;
END

-- Conversion and inspection context
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'source' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: source (NVARCHAR(100))';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD source NVARCHAR(100) NULL;
END
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'purpose' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: purpose (NVARCHAR(50))';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD purpose NVARCHAR(50) NULL;
END
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'private_layout_number' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: private_layout_number (NVARCHAR(100))';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD private_layout_number NVARCHAR(100) NULL;
END
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'conformity' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: conformity (BIT)';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD conformity BIT NULL;
END
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'accessibility' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: accessibility (BIT)';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD accessibility BIT NULL;
END
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'existing_road_reservation' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: existing_road_reservation (NVARCHAR(255))';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD existing_road_reservation NVARCHAR(255) NULL;
END
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'recommended_road_reservation' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: recommended_road_reservation (NVARCHAR(255))';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD recommended_road_reservation NVARCHAR(255) NULL;
END
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'adequate_size_requirement' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: adequate_size_requirement (BIT)';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD adequate_size_requirement BIT NULL;
END
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'land_traversing_utility' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: land_traversing_utility (BIT)';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD land_traversing_utility BIT NULL;
END

-- Measurement fields (no recommended height column)
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'existing_site_length' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: existing_site_length (DECIMAL(10,2))';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD existing_site_length DECIMAL(10,2) NULL;
END
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'existing_site_width' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: existing_site_width (DECIMAL(10,2))';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD existing_site_width DECIMAL(10,2) NULL;
END
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'existing_site_area' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: existing_site_area (DECIMAL(10,2))';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD existing_site_area DECIMAL(10,2) NULL;
END
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'recommended_site_length' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: recommended_site_length (DECIMAL(10,2))';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD recommended_site_length DECIMAL(10,2) NULL;
END
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'recommended_site_width' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: recommended_site_width (DECIMAL(10,2))';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD recommended_site_width DECIMAL(10,2) NULL;
END
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'recommended_site_area' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: recommended_site_area (DECIMAL(10,2))';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD recommended_site_area DECIMAL(10,2) NULL;
END

-- Unit and officer metadata
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'unit_number' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: unit_number (NVARCHAR(100))';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD unit_number NVARCHAR(100) NULL;
END
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'unit_dimension' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: unit_dimension (NVARCHAR(255))';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD unit_dimension NVARCHAR(255) NULL;
END
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE Name = N'inspection_officer_id' AND Object_ID = OBJECT_ID(@table))
BEGIN
    PRINT 'Adding missing column: inspection_officer_id (BIGINT)';
    ALTER TABLE [dbo].[joint_site_inspection_reports] ADD inspection_officer_id BIGINT NULL;
END

PRINT 'Column presence check complete.';
GO
