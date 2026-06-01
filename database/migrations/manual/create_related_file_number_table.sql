-- ============================================================================
-- create_related_file_number_table.sql
-- ----------------------------------------------------------------------------
-- Drops and recreates the global [related_file_number] lookup table, then
-- back-fills it from six source tables. Adds three context columns to the
-- earlier version: file_title, party_2, location.
--
-- Sources (column names vary, but everything is mapped to the same shape):
--
--   source_table          related_fileno col   prop_id      file_title      party_2          location
--   ----------------      ------------------   ----------   ----------      ---------------  -------------------
--   file_indexings        related_fileno       prop_id      file_title      current_holder   location
--   deprecated_records    related_fileno       prop_id      file_title      current_holder   location
--   fileNumber            related_fileno       NULL         FileName        NULL             location
--   dciv_file_no          related_fileno       NULL         file_name       NULL             location
--   pra                   related_file_number  prop_id      NULL            party_2          COALESCE(property_description, location)
--   pic                   related_file_number  prop_id      NULL            party_2          COALESCE(property_description, location)
--
-- Idempotent: drops the table if it exists, so re-running is safe and resets
-- the data each time (no incremental dedupe on this run).
--
-- Target: SQL Server (sqlsrv connection)
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

-- ---------------------------------------------------------------------------
-- 1) Drop and recreate the global table
-- ---------------------------------------------------------------------------
IF OBJECT_ID('dbo.related_file_number', 'U') IS NOT NULL
    DROP TABLE [dbo].[related_file_number];

CREATE TABLE [dbo].[related_file_number] (
    [id]             BIGINT          IDENTITY(1,1) NOT NULL,
    [related_fileno] NVARCHAR(500)   NOT NULL,
    [prop_id]        NVARCHAR(100)   NULL,
    [source_table]   NVARCHAR(50)    NOT NULL,
    [source_id]      BIGINT          NOT NULL,
    [file_number]    NVARCHAR(255)   NULL,
    [file_title]     NVARCHAR(500)   NULL,
    [party_2]        NVARCHAR(500)   NULL,
    [location]       NVARCHAR(500)   NULL,
    [created_at]     DATETIME2(0)    NOT NULL,
    [updated_at]     DATETIME2(0)    NOT NULL,
    CONSTRAINT [PK_related_file_number] PRIMARY KEY CLUSTERED ([id] ASC)
);

CREATE NONCLUSTERED INDEX [IX_related_file_number_related_fileno]
    ON [dbo].[related_file_number] ([related_fileno]);

CREATE NONCLUSTERED INDEX [IX_related_file_number_prop_id]
    ON [dbo].[related_file_number] ([prop_id]);

CREATE UNIQUE NONCLUSTERED INDEX [uq_rfn_source]
    ON [dbo].[related_file_number] ([source_table], [source_id]);

PRINT 'Created table [related_file_number] with new columns: file_title, party_2, location.';
GO

-- ---------------------------------------------------------------------------
-- 2) Copy from file_indexings  (related_fileno, prop_id, file_title, current_holder, location)
-- ---------------------------------------------------------------------------
INSERT INTO [dbo].[related_file_number]
    (related_fileno, prop_id, source_table, source_id, file_number, file_title, party_2, location, created_at, updated_at)
SELECT
    LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500)))),
    CAST(s.prop_id AS NVARCHAR(100)),
    'file_indexings',
    s.id,
    s.file_number,
    s.file_title,
    s.current_holder,
    s.location,
    SYSUTCDATETIME(),
    SYSUTCDATETIME()
FROM [dbo].[file_indexings] s WITH (NOLOCK)
WHERE s.related_fileno IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500))))) > 0;

PRINT CONCAT('file_indexings: inserted ', @@ROWCOUNT, ' rows.');
GO

-- ---------------------------------------------------------------------------
-- 3) Copy from deprecated_records  (same shape as file_indexings)
-- ---------------------------------------------------------------------------
INSERT INTO [dbo].[related_file_number]
    (related_fileno, prop_id, source_table, source_id, file_number, file_title, party_2, location, created_at, updated_at)
SELECT
    LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500)))),
    CAST(s.prop_id AS NVARCHAR(100)),
    'deprecated_records',
    s.id,
    s.file_number,
    s.file_title,
    s.current_holder,
    s.location,
    SYSUTCDATETIME(),
    SYSUTCDATETIME()
FROM [dbo].[deprecated_records] s WITH (NOLOCK)
WHERE s.related_fileno IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500))))) > 0;

PRINT CONCAT('deprecated_records: inserted ', @@ROWCOUNT, ' rows.');
GO

-- ---------------------------------------------------------------------------
-- 4) Copy from fileNumber  (FileName -> file_title; no party column; prop_id NULL)
-- ---------------------------------------------------------------------------
INSERT INTO [dbo].[related_file_number]
    (related_fileno, prop_id, source_table, source_id, file_number, file_title, party_2, location, created_at, updated_at)
SELECT
    LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500)))),
    NULL,
    'fileNumber',
    s.id,
    COALESCE(s.NewKANGISFileNo, s.kangisFileNo, s.mlsfNo),
    s.FileName,
    NULL,
    s.location,
    SYSUTCDATETIME(),
    SYSUTCDATETIME()
FROM [dbo].[fileNumber] s WITH (NOLOCK)
WHERE s.related_fileno IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500))))) > 0;

PRINT CONCAT('fileNumber: inserted ', @@ROWCOUNT, ' rows.');
GO

-- ---------------------------------------------------------------------------
-- 5) Copy from dciv_file_no  (file_name -> file_title; no party column; prop_id NULL)
-- ---------------------------------------------------------------------------
INSERT INTO [dbo].[related_file_number]
    (related_fileno, prop_id, source_table, source_id, file_number, file_title, party_2, location, created_at, updated_at)
SELECT
    LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500)))),
    NULL,
    'dciv_file_no',
    s.id,
    s.full_file_number,
    s.file_name,
    NULL,
    s.location,
    SYSUTCDATETIME(),
    SYSUTCDATETIME()
FROM [dbo].[dciv_file_no] s WITH (NOLOCK)
WHERE s.related_fileno IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500))))) > 0;

PRINT CONCAT('dciv_file_no: inserted ', @@ROWCOUNT, ' rows.');
GO

-- ---------------------------------------------------------------------------
-- 6) Copy from pra  (column: related_file_number; has party_2 + property_description)
-- ---------------------------------------------------------------------------
INSERT INTO [dbo].[related_file_number]
    (related_fileno, prop_id, source_table, source_id, file_number, file_title, party_2, location, created_at, updated_at)
SELECT
    LTRIM(RTRIM(CAST(s.related_file_number AS NVARCHAR(500)))),
    CAST(s.prop_id AS NVARCHAR(100)),
    'pra',
    s.id,
    s.mlsFNo,
    NULL,
    s.party_2,
    COALESCE(s.property_description, s.location),
    SYSUTCDATETIME(),
    SYSUTCDATETIME()
FROM [dbo].[pra] s WITH (NOLOCK)
WHERE s.related_file_number IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_file_number AS NVARCHAR(500))))) > 0;

PRINT CONCAT('pra: inserted ', @@ROWCOUNT, ' rows.');
GO

-- ---------------------------------------------------------------------------
-- 7) Copy from pic  (same shape as pra)
-- ---------------------------------------------------------------------------
INSERT INTO [dbo].[related_file_number]
    (related_fileno, prop_id, source_table, source_id, file_number, file_title, party_2, location, created_at, updated_at)
SELECT
    LTRIM(RTRIM(CAST(s.related_file_number AS NVARCHAR(500)))),
    CAST(s.prop_id AS NVARCHAR(100)),
    'pic',
    s.id,
    s.mlsFNo,
    NULL,
    s.party_2,
    COALESCE(s.property_description, s.location),
    SYSUTCDATETIME(),
    SYSUTCDATETIME()
FROM [dbo].[pic] s WITH (NOLOCK)
WHERE s.related_file_number IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_file_number AS NVARCHAR(500))))) > 0;

PRINT CONCAT('pic: inserted ', @@ROWCOUNT, ' rows.');
GO

-- ---------------------------------------------------------------------------
-- 8) Final summary
-- ---------------------------------------------------------------------------
SELECT
    source_table,
    COUNT(*)                    AS total_rows,
    COUNT(prop_id)              AS with_prop_id,
    COUNT(file_title)           AS with_file_title,
    COUNT(party_2)              AS with_party_2,
    COUNT(location)             AS with_location
FROM [dbo].[related_file_number]
GROUP BY source_table
ORDER BY total_rows DESC;

SELECT COUNT(*) AS grand_total FROM [dbo].[related_file_number];
GO

-- ============================================================================
-- ROLLBACK (run manually if you need to undo)
-- ----------------------------------------------------------------------------
-- IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'related_file_number')
--     DROP TABLE [dbo].[related_file_number];
-- ============================================================================
