-- Create tracking_sheet table for batch tracking sheets
-- This table stores information about generated batch tracking sheets

USE [klas] -- Using the actual database name
GO

-- Drop table if it exists (for clean recreation)
IF OBJECT_ID('dbo.tracking_sheet', 'U') IS NOT NULL
    DROP TABLE dbo.tracking_sheet;
GO

-- Create tracking_sheet table
CREATE TABLE dbo.tracking_sheet (
    id BIGINT IDENTITY(1,1) PRIMARY KEY,
    batch_id VARCHAR(50) NOT NULL UNIQUE,
    batch_name VARCHAR(255) NOT NULL,
    file_count INT NOT NULL DEFAULT 0,
    selected_file_ids TEXT NULL, -- JSON array of selected file IDs
    generated_by BIGINT NULL, -- User ID who generated the batch
    generated_at DATETIME2(0) NOT NULL DEFAULT GETDATE(),
    batch_type VARCHAR(50) NOT NULL DEFAULT 'manual', -- 'manual', 'auto_100', 'auto_200'
    status VARCHAR(50) NOT NULL DEFAULT 'generated', -- 'generated', 'printed', 'archived'
    print_count INT NOT NULL DEFAULT 0,
    last_printed_at DATETIME2(0) NULL,
    last_printed_by BIGINT NULL,
    notes TEXT NULL,
    created_at DATETIME2(0) NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME2(0) NOT NULL DEFAULT GETDATE()
);
GO

-- Create indexes for better performance
CREATE INDEX IX_tracking_sheet_batch_id ON dbo.tracking_sheet(batch_id);
CREATE INDEX IX_tracking_sheet_generated_by ON dbo.tracking_sheet(generated_by);
CREATE INDEX IX_tracking_sheet_generated_at ON dbo.tracking_sheet(generated_at DESC);
CREATE INDEX IX_tracking_sheet_status ON dbo.tracking_sheet(status);
CREATE INDEX IX_tracking_sheet_batch_type ON dbo.tracking_sheet(batch_type);
GO

-- Create a trigger to update the updated_at timestamp
CREATE TRIGGER tr_tracking_sheet_updated_at
ON dbo.tracking_sheet
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.tracking_sheet
    SET updated_at = GETDATE()
    FROM dbo.tracking_sheet ts
    INNER JOIN inserted i ON ts.id = i.id;
END;
GO

PRINT 'tracking_sheet table created successfully!';
GO