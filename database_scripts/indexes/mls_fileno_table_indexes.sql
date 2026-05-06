-- =============================================================================
-- MLS File Number Generator – Performance Index Script
-- Target DB : SQL Server (sqlsrv connection)
-- Tables    : fileNumber, mls_file_no, pra, file_commissioning_sheets
-- =============================================================================
-- WHY THESE INDEXES ARE NEEDED
-- The mlsfno DataTable page was slow because:
-- 1. pra table had ZERO indexes. The OUTER APPLY "SELECT TOP 1 … FROM pra WHERE
--    mlsFNo = fn.mlsfNo ORDER BY id DESC" runs once per row in the result – a
--    full table scan on every single page fetch.
-- 2. fileNumber had no index covering (SOURCE, type, is_deleted) together, so
--    the DataTable's base query couldn't benefit from a seek on all three filter
--    columns at once.
-- 3. The page-load controller ran 3 separate COUNT queries (total / today /
--    month) against fileNumber with no cached result.  Those are now merged into
--    one query + Cache::remember() in FileNumberController::index().
-- =============================================================================

USE [YOUR_DATABASE_NAME];   -- replace with your actual DB name
GO

-- -----------------------------------------------------------------------------
-- 1. CRITICAL  –  pra.mlsFNo index
--    The OUTER APPLY in getData() does:
--      SELECT TOP 1 p.id, p.prop_id, p.temp_fileno
--      FROM pra p WHERE p.mlsFNo = fn.mlsfNo ORDER BY p.id DESC
--    Without an index on mlsFNo this is a full scan of pra on *every row*
--    returned from fileNumber.
-- -----------------------------------------------------------------------------
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('pra') AND name = 'IX_pra_mlsFNo'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_pra_mlsFNo
        ON pra (mlsFNo)
        INCLUDE (id, prop_id, temp_fileno)
        WITH (ONLINE = ON, FILLFACTOR = 90);

    PRINT 'Created IX_pra_mlsFNo';
END
ELSE
    PRINT 'IX_pra_mlsFNo already exists – skipped';
GO

-- -----------------------------------------------------------------------------
-- 2. HIGH  –  fileNumber covering index for the MLS DataTable query
--    getData() filters: SOURCE IN (…) AND type = 'MlsFileNO'
--                       AND (is_deleted IS NULL OR is_deleted = 0)
--    The existing IX_fileNumber_SourceFiltered covers (SOURCE, is_deleted) but
--    lacks `type` as a key column, and its INCLUDE list does not cover all the
--    columns that need to be fetched, forcing many key look-ups.
--
--    This new index adds `type` into the key and provides a wide INCLUDE list
--    that satisfies the SELECT list of the DataTable outer query without
--    needing to touch the clustered index at all.
-- -----------------------------------------------------------------------------
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('fileNumber') AND name = 'IX_fileNumber_MLS_DataTable'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_fileNumber_MLS_DataTable
        ON fileNumber (SOURCE, type, is_deleted)
        INCLUDE (
            id,
            mlsfNo,
            FileName,
            kangisFileNo,
            NewKANGISFileNo,
            st_file_no,
            plot_no,
            tp_no,
            location,
            tracking_id,
            created_at,
            lga,
            created_by
        )
        WITH (ONLINE = ON, FILLFACTOR = 90);

    PRINT 'Created IX_fileNumber_MLS_DataTable';
END
ELSE
    PRINT 'IX_fileNumber_MLS_DataTable already exists – skipped';
GO

-- -----------------------------------------------------------------------------
-- 3. MEDIUM  –  fileNumber date-filter index for page-load stats counts
--    FileNumberController::index() (now merged into a single query + cache) and
--    getStats() both filter on (SOURCE, type, is_deleted) and then aggregate by
--    date ranges.  Having created_at in the key lets SQL Server do a range seek
--    instead of scanning the entire data page.
--
--    NOTE: If IX_fileNumber_MLS_DataTable (above) is already used by the planner
--    for these counts, this index may not be needed.  Run the execution plan
--    after adding Index #2 before deciding.
-- -----------------------------------------------------------------------------
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('fileNumber') AND name = 'IX_fileNumber_MLS_StatsCounts'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_fileNumber_MLS_StatsCounts
        ON fileNumber (SOURCE, type, is_deleted, created_at)
        INCLUDE (mlsfNo)
        WITH (ONLINE = ON, FILLFACTOR = 90);

    PRINT 'Created IX_fileNumber_MLS_StatsCounts';
END
ELSE
    PRINT 'IX_fileNumber_MLS_StatsCounts already exists – skipped';
GO

-- -----------------------------------------------------------------------------
-- 4. VERIFY – list all indexes on the three tables after creation
-- -----------------------------------------------------------------------------
SELECT
    t.name          AS table_name,
    i.name          AS index_name,
    i.type_desc,
    c.name          AS col_name,
    ic.key_ordinal,
    ic.is_included_column
FROM sys.indexes i
INNER JOIN sys.index_columns ic
       ON  ic.object_id = i.object_id AND ic.index_id = i.index_id
INNER JOIN sys.columns c
       ON  c.object_id  = ic.object_id AND c.column_id = ic.column_id
INNER JOIN sys.tables t
       ON  t.object_id  = i.object_id
WHERE i.object_id IN (
        OBJECT_ID('fileNumber'),
        OBJECT_ID('mls_file_no'),
        OBJECT_ID('pra'),
        OBJECT_ID('file_commissioning_sheets')
      )
  AND i.type > 0
ORDER BY t.name, i.name, ic.key_ordinal, ic.is_included_column;
GO
