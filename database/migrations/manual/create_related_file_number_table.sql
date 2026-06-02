-- ============================================================================
-- create_related_file_number_table.sql
-- ----------------------------------------------------------------------------
-- Drops and recreates [related_file_number] and back-fills from six sources.
--
-- Adds in this revision:
--   * comment column          -- "KANGIS RECERTIFICATION <New KANGIS FileNo>"
--                             -- or "MINISTRY OF LAND AND PHYSICAL PLANNING
--                             --     RECERTIFICATION <RC FileNo>"
--   * JSON splitting          -- related_fileno values stored as JSON arrays
--                                are exploded into one row per element via
--                                OPENJSON, so each old KN reference gets its
--                                own row instead of being stacked.
--
-- Source -> column mapping:
--
--   source_table          related_fileno col   prop_id     file_title     party_2        location               new_kangis (for comment)
--   file_indexings        related_fileno       prop_id     file_title     current_holder location               new_kangis_file_no
--   deprecated_records    related_fileno       prop_id     file_title     current_holder location               (none)
--   fileNumber            related_fileno       NULL        FileName       NULL           location               NewKANGISFileNo
--   dciv_file_no          related_fileno       NULL        file_name      NULL           location               (none)
--   pra                   related_file_number  prop_id     NULL           party_2        COALESCE(prop_desc,loc)NewKANGISFileno
--   pic                   related_file_number  prop_id     NULL           party_2        COALESCE(prop_desc,loc)NewKANGISFileno
--
-- Comment rules (applied per output row, in this order):
--   1. Parent has a non-empty New KANGIS FileNo
--      -> "KANGIS RECERTIFICATION <NewKANGIS>"
--   2. Parent file_number contains "-RC-" AND the split related_fileno
--      matches the KANGIS legacy prefixes (KNML, MLKN, KNGP)
--      -> "KANGIS RECERTIFICATION <parent file_number>"
--   3. Parent file_number contains "-RC-" (any other related_fileno)
--      -> "MINISTRY OF LAND AND PHYSICAL PLANNING RECERTIFICATION <file_number>"
--   4. Else NULL
--
-- KANGIS legacy prefixes (from resources/views/components/global-fileno-modal.blade.php):
--   KNML, MLKN, KNGP
-- Notable: "KN <n>" (Old MLS), "GKN/MISC KN/LPKN" (Survey Registry) are NOT
-- treated as KANGIS — they fall through to the MLPP comment when parent has -RC-.
--
-- Target: SQL Server 2016+ (OPENJSON, ISJSON).
-- Idempotent: drops the table on each run.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

-- ---------------------------------------------------------------------------
-- 1) Drop and recreate
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
    [comment]        NVARCHAR(500)   NULL,
    [created_at]     DATETIME2(0)    NOT NULL,
    [updated_at]     DATETIME2(0)    NOT NULL,
    CONSTRAINT [PK_related_file_number] PRIMARY KEY CLUSTERED ([id] ASC)
);

CREATE NONCLUSTERED INDEX [IX_related_file_number_related_fileno]
    ON [dbo].[related_file_number] ([related_fileno]);

CREATE NONCLUSTERED INDEX [IX_related_file_number_prop_id]
    ON [dbo].[related_file_number] ([prop_id]);

CREATE NONCLUSTERED INDEX [IX_related_file_number_source]
    ON [dbo].[related_file_number] ([source_table], [source_id]);

PRINT 'Created [related_file_number] with comment + JSON-split.';
GO

-- ---------------------------------------------------------------------------
-- 2) file_indexings
-- ---------------------------------------------------------------------------
INSERT INTO [dbo].[related_file_number]
    (related_fileno, prop_id, source_table, source_id, file_number, file_title, party_2, location, comment, created_at, updated_at)
SELECT
    LTRIM(RTRIM(j.[value])),
    CAST(s.prop_id AS NVARCHAR(100)),
    'file_indexings',
    s.id,
    s.file_number,
    s.file_title,
    s.current_holder,
    s.location,
    CASE
        WHEN s.new_kangis_file_no IS NOT NULL AND LTRIM(RTRIM(s.new_kangis_file_no)) <> ''
            THEN CONCAT('KANGIS RECERTIFICATION ', s.new_kangis_file_no)
        WHEN s.file_number LIKE '%-RC-%' AND
             (   UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNML%'
              OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'MLKN%'
              OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNGP%')
            THEN CONCAT('KANGIS RECERTIFICATION ', s.file_number)
        WHEN s.file_number LIKE '%-RC-%'
            THEN CONCAT('MINISTRY OF LAND AND PHYSICAL PLANNING RECERTIFICATION ', s.file_number)
        ELSE NULL
    END,
    SYSUTCDATETIME(), SYSUTCDATETIME()
FROM [dbo].[file_indexings] s WITH (NOLOCK)
CROSS APPLY OPENJSON(
    CASE WHEN ISJSON(s.related_fileno) = 1
         THEN s.related_fileno
         ELSE CONCAT('["', REPLACE(REPLACE(s.related_fileno, '\', '\\'), '"', '\"'), '"]')
    END
) j
WHERE s.related_fileno IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500))))) > 0
  AND LTRIM(RTRIM(j.[value])) <> '';
PRINT CONCAT('file_indexings: inserted ', @@ROWCOUNT);
GO

-- ---------------------------------------------------------------------------
-- 3) deprecated_records
-- ---------------------------------------------------------------------------
INSERT INTO [dbo].[related_file_number]
    (related_fileno, prop_id, source_table, source_id, file_number, file_title, party_2, location, comment, created_at, updated_at)
SELECT
    LTRIM(RTRIM(j.[value])),
    CAST(s.prop_id AS NVARCHAR(100)),
    'deprecated_records',
    s.id,
    s.file_number,
    s.file_title,
    s.current_holder,
    s.location,
    CASE
        WHEN s.file_number LIKE '%-RC-%' AND
             (   UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNML%'
              OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'MLKN%'
              OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNGP%')
            THEN CONCAT('KANGIS RECERTIFICATION ', s.file_number)
        WHEN s.file_number LIKE '%-RC-%'
            THEN CONCAT('MINISTRY OF LAND AND PHYSICAL PLANNING RECERTIFICATION ', s.file_number)
        ELSE NULL
    END,
    SYSUTCDATETIME(), SYSUTCDATETIME()
FROM [dbo].[deprecated_records] s WITH (NOLOCK)
CROSS APPLY OPENJSON(
    CASE WHEN ISJSON(s.related_fileno) = 1
         THEN s.related_fileno
         ELSE CONCAT('["', REPLACE(REPLACE(s.related_fileno, '\', '\\'), '"', '\"'), '"]')
    END
) j
WHERE s.related_fileno IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500))))) > 0
  AND LTRIM(RTRIM(j.[value])) <> '';
PRINT CONCAT('deprecated_records: inserted ', @@ROWCOUNT);
GO

-- ---------------------------------------------------------------------------
-- 4) fileNumber
-- ---------------------------------------------------------------------------
INSERT INTO [dbo].[related_file_number]
    (related_fileno, prop_id, source_table, source_id, file_number, file_title, party_2, location, comment, created_at, updated_at)
SELECT
    LTRIM(RTRIM(j.[value])),
    NULL,
    'fileNumber',
    s.id,
    COALESCE(s.NewKANGISFileNo, s.kangisFileNo, s.mlsfNo),
    s.FileName,
    NULL,
    s.location,
    CASE
        WHEN s.NewKANGISFileNo IS NOT NULL AND LTRIM(RTRIM(s.NewKANGISFileNo)) <> ''
            THEN CONCAT('KANGIS RECERTIFICATION ', s.NewKANGISFileNo)
        WHEN COALESCE(s.NewKANGISFileNo, s.kangisFileNo, s.mlsfNo) LIKE '%-RC-%' AND
             (   UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNML%'
              OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'MLKN%'
              OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNGP%')
            THEN CONCAT('KANGIS RECERTIFICATION ', COALESCE(s.NewKANGISFileNo, s.kangisFileNo, s.mlsfNo))
        WHEN COALESCE(s.NewKANGISFileNo, s.kangisFileNo, s.mlsfNo) LIKE '%-RC-%'
            THEN CONCAT('MINISTRY OF LAND AND PHYSICAL PLANNING RECERTIFICATION ', COALESCE(s.NewKANGISFileNo, s.kangisFileNo, s.mlsfNo))
        ELSE NULL
    END,
    SYSUTCDATETIME(), SYSUTCDATETIME()
FROM [dbo].[fileNumber] s WITH (NOLOCK)
CROSS APPLY OPENJSON(
    CASE WHEN ISJSON(s.related_fileno) = 1
         THEN s.related_fileno
         ELSE CONCAT('["', REPLACE(REPLACE(s.related_fileno, '\', '\\'), '"', '\"'), '"]')
    END
) j
WHERE s.related_fileno IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500))))) > 0
  AND LTRIM(RTRIM(j.[value])) <> '';
PRINT CONCAT('fileNumber: inserted ', @@ROWCOUNT);
GO

-- ---------------------------------------------------------------------------
-- 5) dciv_file_no
-- ---------------------------------------------------------------------------
INSERT INTO [dbo].[related_file_number]
    (related_fileno, prop_id, source_table, source_id, file_number, file_title, party_2, location, comment, created_at, updated_at)
SELECT
    LTRIM(RTRIM(j.[value])),
    NULL,
    'dciv_file_no',
    s.id,
    s.full_file_number,
    s.file_name,
    NULL,
    s.location,
    CASE
        WHEN s.full_file_number LIKE '%-RC-%' AND
             (   UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNML%'
              OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'MLKN%'
              OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNGP%')
            THEN CONCAT('KANGIS RECERTIFICATION ', s.full_file_number)
        WHEN s.full_file_number LIKE '%-RC-%'
            THEN CONCAT('MINISTRY OF LAND AND PHYSICAL PLANNING RECERTIFICATION ', s.full_file_number)
        ELSE NULL
    END,
    SYSUTCDATETIME(), SYSUTCDATETIME()
FROM [dbo].[dciv_file_no] s WITH (NOLOCK)
CROSS APPLY OPENJSON(
    CASE WHEN ISJSON(s.related_fileno) = 1
         THEN s.related_fileno
         ELSE CONCAT('["', REPLACE(REPLACE(s.related_fileno, '\', '\\'), '"', '\"'), '"]')
    END
) j
WHERE s.related_fileno IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_fileno AS NVARCHAR(500))))) > 0
  AND LTRIM(RTRIM(j.[value])) <> '';
PRINT CONCAT('dciv_file_no: inserted ', @@ROWCOUNT);
GO

-- ---------------------------------------------------------------------------
-- 6) pra  (note column: related_file_number, NewKANGISFileno lowercase 'no')
-- ---------------------------------------------------------------------------
INSERT INTO [dbo].[related_file_number]
    (related_fileno, prop_id, source_table, source_id, file_number, file_title, party_2, location, comment, created_at, updated_at)
SELECT
    LTRIM(RTRIM(j.[value])),
    CAST(s.prop_id AS NVARCHAR(100)),
    'pra',
    s.id,
    s.mlsFNo,
    NULL,
    s.party_2,
    COALESCE(s.property_description, s.location),
    CASE
        WHEN s.NewKANGISFileno IS NOT NULL AND LTRIM(RTRIM(s.NewKANGISFileno)) <> ''
            THEN CONCAT('KANGIS RECERTIFICATION ', s.NewKANGISFileno)
        WHEN s.mlsFNo LIKE '%-RC-%' AND
             (   UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNML%'
              OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'MLKN%'
              OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNGP%')
            THEN CONCAT('KANGIS RECERTIFICATION ', s.mlsFNo)
        WHEN s.mlsFNo LIKE '%-RC-%'
            THEN CONCAT('MINISTRY OF LAND AND PHYSICAL PLANNING RECERTIFICATION ', s.mlsFNo)
        ELSE NULL
    END,
    SYSUTCDATETIME(), SYSUTCDATETIME()
FROM [dbo].[pra] s WITH (NOLOCK)
CROSS APPLY OPENJSON(
    CASE WHEN ISJSON(s.related_file_number) = 1
         THEN s.related_file_number
         ELSE CONCAT('["', REPLACE(REPLACE(s.related_file_number, '\', '\\'), '"', '\"'), '"]')
    END
) j
WHERE s.related_file_number IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_file_number AS NVARCHAR(500))))) > 0
  AND LTRIM(RTRIM(j.[value])) <> '';
PRINT CONCAT('pra: inserted ', @@ROWCOUNT);
GO

-- ---------------------------------------------------------------------------
-- 7) pic
-- ---------------------------------------------------------------------------
INSERT INTO [dbo].[related_file_number]
    (related_fileno, prop_id, source_table, source_id, file_number, file_title, party_2, location, comment, created_at, updated_at)
SELECT
    LTRIM(RTRIM(j.[value])),
    CAST(s.prop_id AS NVARCHAR(100)),
    'pic',
    s.id,
    s.mlsFNo,
    NULL,
    s.party_2,
    COALESCE(s.property_description, s.location),
    CASE
        WHEN s.NewKANGISFileno IS NOT NULL AND LTRIM(RTRIM(s.NewKANGISFileno)) <> ''
            THEN CONCAT('KANGIS RECERTIFICATION ', s.NewKANGISFileno)
        WHEN s.mlsFNo LIKE '%-RC-%' AND
             (   UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNML%'
              OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'MLKN%'
              OR UPPER(LTRIM(RTRIM(j.[value]))) LIKE 'KNGP%')
            THEN CONCAT('KANGIS RECERTIFICATION ', s.mlsFNo)
        WHEN s.mlsFNo LIKE '%-RC-%'
            THEN CONCAT('MINISTRY OF LAND AND PHYSICAL PLANNING RECERTIFICATION ', s.mlsFNo)
        ELSE NULL
    END,
    SYSUTCDATETIME(), SYSUTCDATETIME()
FROM [dbo].[pic] s WITH (NOLOCK)
CROSS APPLY OPENJSON(
    CASE WHEN ISJSON(s.related_file_number) = 1
         THEN s.related_file_number
         ELSE CONCAT('["', REPLACE(REPLACE(s.related_file_number, '\', '\\'), '"', '\"'), '"]')
    END
) j
WHERE s.related_file_number IS NOT NULL
  AND LEN(LTRIM(RTRIM(CAST(s.related_file_number AS NVARCHAR(500))))) > 0
  AND LTRIM(RTRIM(j.[value])) <> '';
PRINT CONCAT('pic: inserted ', @@ROWCOUNT);
GO

-- ---------------------------------------------------------------------------
-- 8) Summary
-- ---------------------------------------------------------------------------
SELECT
    source_table,
    COUNT(*)              AS total_rows,
    COUNT(prop_id)        AS with_prop_id,
    COUNT(comment)        AS with_comment,
    SUM(CASE WHEN comment LIKE 'KANGIS RECERTIFICATION%'        THEN 1 ELSE 0 END) AS kangis_comments,
    SUM(CASE WHEN comment LIKE 'MINISTRY OF LAND AND PHYSICAL%' THEN 1 ELSE 0 END) AS mlpp_comments
FROM [dbo].[related_file_number]
GROUP BY source_table
ORDER BY total_rows DESC;

SELECT COUNT(*) AS grand_total FROM [dbo].[related_file_number];
GO

-- ============================================================================
-- ROLLBACK (manual)
-- IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'related_file_number')
--     DROP TABLE [dbo].[related_file_number];
-- ============================================================================
