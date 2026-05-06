-- Create scan_reassignment_logs table
CREATE TABLE [scan_reassignment_logs] (
    [id] BIGINT PRIMARY KEY IDENTITY(1,1),
    [scanning_id] INT NOT NULL,
    [from_file_number] VARCHAR(100) NOT NULL,
    [to_file_number] VARCHAR(100) NOT NULL,
    [from_file_indexing_id] INT NULL,
    [to_file_indexing_id] INT NULL,
    [from_path] VARCHAR(500) NOT NULL,
    [to_path] VARCHAR(500) NOT NULL,
    [reason] VARCHAR(500) NULL,
    [reassigned_by] BIGINT NOT NULL,
    [created_at] DATETIME2 NULL,
    [updated_at] DATETIME2 NULL,
    
    -- Foreign keys
    CONSTRAINT [fk_scan_reassignment_logs_scanning_id] 
        FOREIGN KEY ([scanning_id]) 
        REFERENCES [scannings]([id]) 
        ON DELETE CASCADE,
    
    CONSTRAINT [fk_scan_reassignment_logs_from_file_indexing_id] 
        FOREIGN KEY ([from_file_indexing_id]) 
        REFERENCES [file_indexings]([id]) 
        ON DELETE NO ACTION,
    
    CONSTRAINT [fk_scan_reassignment_logs_to_file_indexing_id] 
        FOREIGN KEY ([to_file_indexing_id]) 
        REFERENCES [file_indexings]([id]) 
        ON DELETE NO ACTION,
    
    CONSTRAINT [fk_scan_reassignment_logs_reassigned_by] 
        FOREIGN KEY ([reassigned_by]) 
        REFERENCES [users]([id]) 
        ON DELETE NO ACTION
);

-- Create indexes
CREATE INDEX [idx_scan_reassignment_logs_scanning_id] ON [scan_reassignment_logs]([scanning_id]);
CREATE INDEX [idx_scan_reassignment_logs_from_file_number] ON [scan_reassignment_logs]([from_file_number]);
CREATE INDEX [idx_scan_reassignment_logs_to_file_number] ON [scan_reassignment_logs]([to_file_number]);
CREATE INDEX [idx_scan_reassignment_logs_created_at] ON [scan_reassignment_logs]([created_at]);

-- Drop table script
-- DROP TABLE [scan_reassignment_logs];
