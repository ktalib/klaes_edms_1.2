-- SQL Server Script for land_recommendations table
-- Generated: 2026-02-14

CREATE TABLE land_recommendations (
    id INT IDENTITY(1,1) PRIMARY KEY,
    file_number NVARCHAR(255) NOT NULL,
    applicant_name NVARCHAR(255) NULL,
    purpose_of_clause NVARCHAR(255) NULL,
    location NVARCHAR(255) NULL,
    plot_number NVARCHAR(255) NULL,
    layout_plan_no NVARCHAR(255) NULL,
    term NVARCHAR(255) NULL,
    development_value DECIMAL(18, 2) NULL,
    development_period NVARCHAR(255) NULL,
    ground_rent DECIMAL(18, 2) NULL,
    development_charge DECIMAL(18, 2) NULL,
    preparation_fees DECIMAL(18, 2) NULL,
    recommendation NVARCHAR(MAX) NULL,
    
    -- Audit & Metadata (Used by system but not explicitly on print template)
    land_use NVARCHAR(255) NULL,
    area_sqm DECIMAL(18, 2) NULL,
    meeting_date DATE NULL,
    effective_date DATE NULL,
    premium DECIMAL(18, 2) NULL,
    
    -- System Audit Fields
    created_by NVARCHAR(255) NULL,
    updated_by NVARCHAR(255) NULL,
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE()
);

CREATE INDEX idx_land_recommendations_file_number ON land_recommendations(file_number);
GO

-- If updating existing table to add missing columns:
/*
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('land_recommendations') AND name = 'layout_plan_no')
BEGIN
    ALTER TABLE land_recommendations ADD layout_plan_no NVARCHAR(255) NULL;
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('land_recommendations') AND name = 'development_value')
BEGIN
    ALTER TABLE land_recommendations ADD development_value DECIMAL(18, 2) NULL;
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID('land_recommendations') AND name = 'development_charge')
BEGIN
    ALTER TABLE land_recommendations ADD development_charge DECIMAL(18, 2) NULL;
END
*/
