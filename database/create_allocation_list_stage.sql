-- ============================================================
-- Allocation List Entry - SQL Server Table Creation
-- Run this in SSMS against the KLAS SQL Server database
-- ============================================================

USE [klas];
GO

-- Create the main table
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = 'allocation_list_stage'
)
BEGIN
    CREATE TABLE [dbo].[allocation_list_stage] (
        [id]           INT IDENTITY(1,1) NOT NULL,
        [title]        NVARCHAR(50)      NULL,
        [first_name]   NVARCHAR(100)     NOT NULL,
        [middle_name]  NVARCHAR(100)     NULL,
        [last_name]    NVARCHAR(100)     NOT NULL,
        [plot_number]  NVARCHAR(50)      NULL,
        [district]     NVARCHAR(100)     NULL,
        [lga]          NVARCHAR(100)     NULL,
        [state]        NVARCHAR(100)     NULL     DEFAULT N'Kano',
        [allocated_by] NVARCHAR(50)      NULL,   -- 'Governor' or 'Commissioner'
        [created_by]   INT               NULL,
        [updated_by]   INT               NULL,
        [created_at]   DATETIME2(0)      NOT NULL DEFAULT GETDATE(),
        [updated_at]   DATETIME2(0)      NOT NULL DEFAULT GETDATE(),
        CONSTRAINT [PK_allocation_list_stage] PRIMARY KEY CLUSTERED ([id] ASC)
    );

    PRINT 'Table [dbo].[allocation_list_stage] created.';
END
ELSE
BEGIN
    PRINT 'Table [dbo].[allocation_list_stage] already exists – skipped.';
END
GO

-- Indexes
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_alloc_last_name' AND object_id = OBJECT_ID('dbo.allocation_list_stage'))
    CREATE NONCLUSTERED INDEX [IX_alloc_last_name]   ON [dbo].[allocation_list_stage] ([last_name] ASC);

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_alloc_allocated_by' AND object_id = OBJECT_ID('dbo.allocation_list_stage'))
    CREATE NONCLUSTERED INDEX [IX_alloc_allocated_by] ON [dbo].[allocation_list_stage] ([allocated_by] ASC);

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_alloc_created_at' AND object_id = OBJECT_ID('dbo.allocation_list_stage'))
    CREATE NONCLUSTERED INDEX [IX_alloc_created_at]  ON [dbo].[allocation_list_stage] ([created_at] DESC);

PRINT 'Indexes ensured.';
GO
