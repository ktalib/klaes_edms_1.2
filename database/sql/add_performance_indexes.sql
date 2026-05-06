-- Performance Indexes for fileNumber table to improve DataTables loading speed
-- Run these indexes on the SQL Server database

-- Index on mlsfNo for fast filtering and searching
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_fileNumber_mlsfNo')
BEGIN
    CREATE NONCLUSTERED INDEX IX_fileNumber_mlsfNo 
    ON dbo.fileNumber (mlsfNo)
    WHERE mlsfNo IS NOT NULL AND LTRIM(RTRIM(mlsfNo)) != ''
    INCLUDE (id, kangisFileNo, NewKANGISFileNo, FileName, created_at)
END

-- Index on kangisFileNo for fast filtering and searching
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_fileNumber_kangisFileNo')
BEGIN
    CREATE NONCLUSTERED INDEX IX_fileNumber_kangisFileNo 
    ON dbo.fileNumber (kangisFileNo)
    WHERE kangisFileNo IS NOT NULL AND LTRIM(RTRIM(kangisFileNo)) != ''
    INCLUDE (id, mlsfNo, NewKANGISFileNo, FileName, created_at)
END

-- Index on NewKANGISFileNo for fast filtering and searching
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_fileNumber_NewKANGISFileNo')
BEGIN
    CREATE NONCLUSTERED INDEX IX_fileNumber_NewKANGISFileNo 
    ON dbo.fileNumber (NewKANGISFileNo)
    WHERE NewKANGISFileNo IS NOT NULL AND LTRIM(RTRIM(NewKANGISFileNo)) != ''
    INCLUDE (id, mlsfNo, kangisFileNo, FileName, created_at)
END

-- Index on st_file_no for ST file numbers
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_fileNumber_st_file_no')
BEGIN
    CREATE NONCLUSTERED INDEX IX_fileNumber_st_file_no 
    ON dbo.fileNumber (st_file_no)
    WHERE st_file_no IS NOT NULL AND LTRIM(RTRIM(st_file_no)) != ''
    INCLUDE (id, FileName, created_at)
END

-- Composite index for is_deleted and type filtering (most common filters)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_fileNumber_deleted_type')
BEGIN
    CREATE NONCLUSTERED INDEX IX_fileNumber_deleted_type 
    ON dbo.fileNumber (is_deleted, type, SOURCE)
    INCLUDE (id, mlsfNo, kangisFileNo, NewKANGISFileNo, FileName, created_at)
END

-- Index on created_at for ordering performance
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_fileNumber_created_at')
BEGIN
    CREATE NONCLUSTERED INDEX IX_fileNumber_created_at 
    ON dbo.fileNumber (created_at DESC, id DESC)
    WHERE is_deleted IS NULL OR is_deleted = 0
END

-- Full-text search index on FileName for better text searching
-- Note: This requires full-text search to be enabled on the database
-- IF NOT EXISTS (SELECT * FROM sys.fulltext_indexes WHERE object_id = OBJECT_ID('dbo.fileNumber'))
-- BEGIN
--     CREATE FULLTEXT INDEX ON dbo.fileNumber (FileName)
--     KEY INDEX PK_fileNumber_id -- Replace with your actual primary key index name
-- END

PRINT 'Performance indexes created successfully for fileNumber table!'
PRINT 'These indexes will significantly improve DataTables loading performance.'