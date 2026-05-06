-- ============================================
-- MLS File Number Generator - Database Setup
-- Table 2: mls_serial_control
-- ============================================
-- Purpose: Manages serial number allocation per land use and year
-- Created: 2026-01-28
-- ============================================

-- Drop table if exists (for clean installation)
-- IF OBJECT_ID('dbo.mls_serial_control', 'U') IS NOT NULL
--     DROP TABLE dbo.mls_serial_control;
-- GO

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
    
    -- Unique constraint to ensure one record per land use/year combination
    CONSTRAINT uniq_land_use_year UNIQUE (land_use, year)
);
GO

-- Create indexes for performance
CREATE INDEX idx_mls_serial_control_land_use ON dbo.mls_serial_control(land_use);
CREATE INDEX idx_mls_serial_control_year ON dbo.mls_serial_control(year);
GO

-- Add comments (SQL Server extended properties)
EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Serial number control table for managing last used serial per land use and year', 
    @level0type = N'SCHEMA', @level0name = 'dbo',
    @level1type = N'TABLE',  @level1name = 'mls_serial_control';
GO

PRINT 'Table mls_serial_control created successfully';
GO
