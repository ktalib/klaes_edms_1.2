-- ============================================
-- MLS File Number Generator - Database Setup
-- Table 1: mls_file_no
-- ============================================
-- Purpose: Central registry for all MLS-generated file numbers
-- Created: 2026-01-28
-- ============================================

-- Drop table if exists (for clean installation)
-- IF OBJECT_ID('dbo.mls_file_no', 'U') IS NOT NULL
--     DROP TABLE dbo.mls_file_no;
-- GO

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
GO

-- Create indexes for performance
CREATE INDEX idx_mls_file_no_land_use ON dbo.mls_file_no(land_use);
CREATE INDEX idx_mls_file_no_year ON dbo.mls_file_no(year);
CREATE INDEX idx_mls_file_no_full_file_number ON dbo.mls_file_no(full_file_number);
CREATE INDEX idx_land_use_year_serial ON dbo.mls_file_no(land_use, year, serial_number);
GO

-- Add comments (SQL Server extended properties)
EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Central registry for MLS-generated file numbers with land use tracking', 
    @level0type = N'SCHEMA', @level0name = 'dbo',
    @level1type = N'TABLE',  @level1name = 'mls_file_no';
GO

PRINT 'Table mls_file_no created successfully';
GO
