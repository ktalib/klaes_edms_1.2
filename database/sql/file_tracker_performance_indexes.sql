-- Performance Indexes for file_tracker table to improve query and filtering speed
-- MS SQL Server script

USE klas;
GO

-- Index for searching and filtering by File Number
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_file_tracker_file_number' AND object_id = OBJECT_ID('dbo.file_tracker'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_file_tracker_file_number 
    ON dbo.file_tracker (file_number)
    INCLUDE (tracking_id, file_title, status, priority, created_at);
END
GO

-- Index for filtering by Status and Priority (frequently used in dashboard)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_file_tracker_status_priority' AND object_id = OBJECT_ID('dbo.file_tracker'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_file_tracker_status_priority 
    ON dbo.file_tracker (status, priority)
    INCLUDE (tracking_id, file_number, file_title, created_at);
END
GO

-- Index for filtering by Department and Destination
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_file_tracker_dept_dest' AND object_id = OBJECT_ID('dbo.file_tracker'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_file_tracker_dept_dest 
    ON dbo.file_tracker (department, destination)
    INCLUDE (tracking_id, file_number, status, created_at);
END
GO

-- Index for Receiving Officer lookups
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_file_tracker_receiving_officer' AND object_id = OBJECT_ID('dbo.file_tracker'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_file_tracker_receiving_officer 
    ON dbo.file_tracker (receiving_officer_id, assignment_status)
    INCLUDE (tracking_id, file_number, status, created_at);
END
GO

-- Index for Chronological sorting
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_file_tracker_created_at' AND object_id = OBJECT_ID('dbo.file_tracker'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_file_tracker_created_at 
    ON dbo.file_tracker (created_at DESC)
    INCLUDE (tracking_id, file_number, file_title, status);
END
GO

-- Index for Tracking ID lookups 
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_file_tracker_tracking_id_lookup' AND object_id = OBJECT_ID('dbo.file_tracker'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_file_tracker_tracking_id_lookup 
    ON dbo.file_tracker (tracking_id)
    INCLUDE (file_number, file_title, status, priority);
END
GO

PRINT 'Performance indexes for file_tracker table created successfully!';
