-- ============================================================================
-- Create sua_file_numbers table for SUA file number management
-- ============================================================================

USE [klas];
GO

PRINT '';
PRINT 'Creating table: sua_file_numbers...';
GO

-- Drop the table if it exists (for development purposes)
IF EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[sua_file_numbers]') AND type in (N'U'))
BEGIN
    PRINT 'Dropping existing sua_file_numbers table...';
    DROP TABLE [dbo].[sua_file_numbers];
END
GO

CREATE TABLE [dbo].[sua_file_numbers] (
    -- Primary Key
    [id] BIGINT IDENTITY(1,1) NOT NULL,
    
    -- File Number Details
    [main_file_number] NVARCHAR(100) NOT NULL,      -- ST-RES-2025-1
    [sua_file_number] NVARCHAR(100) NOT NULL,       -- ST-RES-2025-1-001
    [mls_file_number] NVARCHAR(100) NULL,           -- Optional MLS file number
    
    -- File Number Components
    [land_use_code] NVARCHAR(10) NOT NULL,          -- RES, COM, IND
    [land_use_full] NVARCHAR(50) NOT NULL,          -- Residential, Commercial, Industrial
    [year] INT NOT NULL,                            -- 2025
    [sequence_number] INT NOT NULL,                 -- Sequential number for main file
    [unit_sequence] INT NOT NULL,                   -- Sequential number for SUA unit (001, 002, etc.)
    
    -- Status and Associations
    [status] NVARCHAR(20) NOT NULL DEFAULT 'active',
    [subapplication_id] BIGINT NULL,                -- Reference to subapplications table
    [mother_application_id] INT NULL,               -- Reference to mother_applications if applicable
    
    -- Metadata
    [is_auto_generated] BIT NOT NULL DEFAULT 1,
    [generation_method] NVARCHAR(50) NOT NULL DEFAULT 'sua_auto',
    [notes] NVARCHAR(MAX) NULL,
    
    -- Audit Trail
    [created_by] BIGINT NULL,
    [created_at] DATETIME2(0) NOT NULL DEFAULT GETDATE(),
    [updated_at] DATETIME2(0) NULL,
    
    -- Constraints
    CONSTRAINT [PK_sua_file_numbers] PRIMARY KEY CLUSTERED ([id] ASC),
    CONSTRAINT [UQ_sua_file_numbers_main] UNIQUE NONCLUSTERED ([main_file_number] ASC),
    CONSTRAINT [UQ_sua_file_numbers_sua] UNIQUE NONCLUSTERED ([sua_file_number] ASC),
    CONSTRAINT [CHK_sua_file_numbers_status] CHECK ([status] IN ('active', 'inactive', 'archived')),
    CONSTRAINT [CHK_sua_file_numbers_land_use_code] CHECK ([land_use_code] IN ('RES', 'COM', 'IND'))
);
GO

-- Create indexes for better performance
SET QUOTED_IDENTIFIER ON;  
GO

CREATE NONCLUSTERED INDEX [IX_sua_file_numbers_land_use_year] ON [dbo].[sua_file_numbers] 
(
    [land_use_code] ASC,
    [year] ASC,
    [sequence_number] ASC
);
GO

CREATE NONCLUSTERED INDEX [IX_sua_file_numbers_subapplication] ON [dbo].[sua_file_numbers] 
(
    [subapplication_id] ASC
) WHERE [subapplication_id] IS NOT NULL;
GO

CREATE NONCLUSTERED INDEX [IX_sua_file_numbers_status] ON [dbo].[sua_file_numbers] 
(
    [status] ASC,
    [created_at] DESC
);
GO

PRINT '✓ Table sua_file_numbers created successfully with indexes';
PRINT '';
PRINT 'Table structure:';
PRINT '- Main File Number: ST-[LAND_USE]-[YEAR]-[SEQUENCE]';
PRINT '- SUA File Number: ST-[LAND_USE]-[YEAR]-[SEQUENCE]-[UNIT_SEQUENCE]';
PRINT '- Supports RES, COM, IND land use codes';
PRINT '- Tracks both main and unit sequences independently';
GO