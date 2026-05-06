-- Add tracking_id column to file_indexings table
USE klas;

-- Check if column exists first
IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'file_indexings' 
    AND COLUMN_NAME = 'tracking_id'
)
BEGIN
    ALTER TABLE file_indexings 
    ADD tracking_id NVARCHAR(100) NULL;
    
    -- Create index on tracking_id
    CREATE INDEX IX_file_indexings_tracking_id ON file_indexings(tracking_id);
    
    PRINT 'tracking_id column added successfully';
END
ELSE
BEGIN
    PRINT 'tracking_id column already exists';
END