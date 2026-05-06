-- Create file_tracker table for KLAES GIS EDMS
-- MS SQL Server script

USE klas;
GO

-- Check if table exists and drop if necessary
IF OBJECT_ID('dbo.file_tracker', 'U') IS NOT NULL
    DROP TABLE dbo.file_tracker;
GO

-- Create file_tracker table
CREATE TABLE file_tracker (
    id BIGINT IDENTITY(1,1) PRIMARY KEY,
    tracking_id NVARCHAR(255) UNIQUE NULL,
    file_number NVARCHAR(255) NULL,
    file_title NVARCHAR(255) NULL,
    file_type NVARCHAR(100) NULL,
    priority NVARCHAR(20) DEFAULT 'MEDIUM' NOT NULL,
    created_by NVARCHAR(255) NULL,
    created_by_name NVARCHAR(255) NULL,
    department NVARCHAR(100) NULL,
    description NTEXT NULL,
    status NVARCHAR(20) DEFAULT 'ACTIVE' NOT NULL,
    date_created DATETIME NULL,
    deadline DATETIME NULL,
    movement_log NTEXT NULL, -- JSON data stored as text
    current_office_code NVARCHAR(20) NULL,
    current_office_name NVARCHAR(255) NULL,
    total_offices INT DEFAULT 0 NOT NULL,
    completed_offices INT DEFAULT 0 NOT NULL,
    notes NTEXT NULL,
    created_at DATETIME2 DEFAULT GETDATE() NOT NULL,
    updated_at DATETIME2 DEFAULT GETDATE() NOT NULL
);
GO

-- Create indexes for better performance
CREATE INDEX IX_file_tracker_tracking_id ON file_tracker(tracking_id);
CREATE INDEX IX_file_tracker_file_number ON file_tracker(file_number);
CREATE INDEX IX_file_tracker_status ON file_tracker(status);
CREATE INDEX IX_file_tracker_priority ON file_tracker(priority);
CREATE INDEX IX_file_tracker_created_by ON file_tracker(created_by);
CREATE INDEX IX_file_tracker_current_office_code ON file_tracker(current_office_code);
CREATE INDEX IX_file_tracker_date_created ON file_tracker(date_created);
CREATE INDEX IX_file_tracker_deadline ON file_tracker(deadline);
GO

-- Add constraints
ALTER TABLE file_tracker ADD CONSTRAINT CK_file_tracker_priority 
CHECK (priority IN ('LOW', 'MEDIUM', 'HIGH'));

ALTER TABLE file_tracker ADD CONSTRAINT CK_file_tracker_status 
CHECK (status IN ('ACTIVE', 'COMPLETED', 'CANCELLED'));
GO

PRINT 'file_tracker table created successfully with indexes and constraints.';