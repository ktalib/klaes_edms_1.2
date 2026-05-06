-- Add shelf_label_id column to file_indexings table
-- Execute this SQL script to add the missing column

-- Check and add shelf_label_id column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'shelf_label_id')
BEGIN
    ALTER TABLE file_indexings ADD shelf_label_id BIGINT NULL;
    PRINT 'Added shelf_label_id column to file_indexings table';
END
ELSE
BEGIN
    PRINT 'shelf_label_id column already exists in file_indexings table';
END

-- Create index on shelf_label_id for better performance
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_file_indexings_shelf_label_id' AND object_id = OBJECT_ID('file_indexings'))
BEGIN
    CREATE INDEX IX_file_indexings_shelf_label_id ON file_indexings (shelf_label_id);
    PRINT 'Created index IX_file_indexings_shelf_label_id';
END
ELSE
BEGIN
    PRINT 'Index IX_file_indexings_shelf_label_id already exists';
END