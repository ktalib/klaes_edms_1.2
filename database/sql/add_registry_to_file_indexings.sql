-- Add registry column to file_indexings if it doesn't exist
IF COL_LENGTH('file_indexings','registry') IS NULL
BEGIN
    ALTER TABLE file_indexings ADD registry NVARCHAR(255) NULL;
    PRINT 'Added registry column to file_indexings';
END
ELSE
BEGIN
    PRINT 'registry column already exists on file_indexings';
END
