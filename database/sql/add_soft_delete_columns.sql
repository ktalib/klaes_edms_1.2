-- Add soft delete columns to file_indexings table
-- Check if columns exist first to avoid errors

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'is_deleted')
BEGIN
    ALTER TABLE file_indexings ADD is_deleted TINYINT NOT NULL DEFAULT 0;
    PRINT 'Added is_deleted column to file_indexings table';
END
ELSE
BEGIN
    PRINT 'is_deleted column already exists in file_indexings table';
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'deleted_at')
BEGIN
    ALTER TABLE file_indexings ADD deleted_at DATETIME2 NULL;
    PRINT 'Added deleted_at column to file_indexings table';
END
ELSE
BEGIN
    PRINT 'deleted_at column already exists in file_indexings table';
END

-- Create index on is_deleted for better performance
IF NOT EXISTS (SELECT * FROM sys.indexes 
               WHERE object_id = OBJECT_ID('file_indexings') AND name = 'IX_file_indexings_is_deleted')
BEGIN
    CREATE INDEX IX_file_indexings_is_deleted ON file_indexings (is_deleted);
    PRINT 'Created index IX_file_indexings_is_deleted';
END
ELSE
BEGIN
    PRINT 'Index IX_file_indexings_is_deleted already exists';
END