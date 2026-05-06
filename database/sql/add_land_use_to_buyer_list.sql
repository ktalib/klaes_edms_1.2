-- Add land_use column to buyer_list table
USE klas;

-- Check if column exists first
IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'buyer_list' 
    AND COLUMN_NAME = 'land_use'
)
BEGIN
    ALTER TABLE buyer_list 
    ADD land_use NVARCHAR(100) NULL;
    
    PRINT 'land_use column added successfully to buyer_list table';
END
ELSE
BEGIN
    PRINT 'land_use column already exists in buyer_list table';
END