-- ============================================================================
-- backfill_related_file_number_prop_id.sql
-- ----------------------------------------------------------------------------
-- Backfills NULL prop_id values on related_file_number by looking up the
-- row's file_number against PropID_Master.
--
-- Currently only rows from sources fileNumber and dciv_file_no have NULL
-- prop_id (those source tables don't have a prop_id column). We resolve them
-- by matching file_number against ANY of PropID_Master's identifier columns
-- (mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno) — case-insensitive,
-- trimmed.
--
-- Idempotent: only touches rows where prop_id IS NULL. Re-runnable safely.
-- Target: SQL Server (sqlsrv connection)
-- ============================================================================

SET NOCOUNT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

-- ---------------------------------------------------------------------------
-- 0) Pre-flight: how many NULL prop_id rows are there to fill?
-- ---------------------------------------------------------------------------
DECLARE @before_null INT = (
    SELECT COUNT(*) FROM [dbo].[related_file_number] WHERE prop_id IS NULL
);
PRINT CONCAT('BEFORE: rows with NULL prop_id = ', @before_null);

-- ---------------------------------------------------------------------------
-- 1) Build a flat identifier -> prop_id lookup from PropID_Master.
--    Combines all four identifier columns into one indexed table so each
--    file_number can be resolved in a single hash join.
-- ---------------------------------------------------------------------------
IF OBJECT_ID('tempdb..#PMLookup', 'U') IS NOT NULL DROP TABLE #PMLookup;

;WITH AllKeys AS (
    SELECT prop_id, UPPER(LTRIM(RTRIM(mlsFNo))) AS key_val
    FROM [dbo].[PropID_Master] WITH (NOLOCK)
    WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
    UNION
    SELECT prop_id, UPPER(LTRIM(RTRIM(kangisFileNo)))
    FROM [dbo].[PropID_Master] WITH (NOLOCK)
    WHERE kangisFileNo IS NOT NULL AND LTRIM(RTRIM(kangisFileNo)) <> ''
    UNION
    SELECT prop_id, UPPER(LTRIM(RTRIM(NewKANGISFileno)))
    FROM [dbo].[PropID_Master] WITH (NOLOCK)
    WHERE NewKANGISFileno IS NOT NULL AND LTRIM(RTRIM(NewKANGISFileno)) <> ''
    UNION
    SELECT prop_id, UPPER(LTRIM(RTRIM(temp_fileno)))
    FROM [dbo].[PropID_Master] WITH (NOLOCK)
    WHERE temp_fileno IS NOT NULL AND LTRIM(RTRIM(temp_fileno)) <> ''
)
SELECT key_val, MIN(prop_id) AS prop_id   -- if multiple, lowest prop_id wins (deterministic)
INTO #PMLookup
FROM AllKeys
GROUP BY key_val;

CREATE UNIQUE CLUSTERED INDEX IX_PMLookup_key ON #PMLookup(key_val);

DECLARE @keys INT = (SELECT COUNT(*) FROM #PMLookup);
PRINT CONCAT('#PMLookup built: ', @keys, ' identifier -> prop_id pairs.');

-- ---------------------------------------------------------------------------
-- 2a) Backfill prop_id via staging.file_number (the fast path)
-- ---------------------------------------------------------------------------
UPDATE t
SET t.prop_id = CAST(m.prop_id AS NVARCHAR(100))
FROM [dbo].[related_file_number] t
JOIN #PMLookup m ON m.key_val = UPPER(LTRIM(RTRIM(t.file_number)))
WHERE t.prop_id IS NULL
  AND t.file_number IS NOT NULL
  AND LTRIM(RTRIM(t.file_number)) <> '';

DECLARE @viaFn INT = @@ROWCOUNT;
PRINT CONCAT('Backfilled via file_number: ', @viaFn, ' rows.');

-- ---------------------------------------------------------------------------
-- 2b) Fallback: for rows where staging.file_number is NULL or didn't match,
--     join back to the original source row (using source_table + source_id)
--     and try every identifier that table actually has. Mirrors the bug where
--     the original migration only put NewKANGISFileNo|kangisFileNo into
--     file_number, missing rows that only have mlsfNo populated.
-- ---------------------------------------------------------------------------

-- fileNumber source: try NewKANGISFileNo, kangisFileNo, mlsfNo, temp_fileno
UPDATE t
SET t.prop_id = CAST(m.prop_id AS NVARCHAR(100)),
    t.file_number = COALESCE(t.file_number, s.NewKANGISFileNo, s.kangisFileNo, s.mlsfNo)
FROM [dbo].[related_file_number] t
JOIN [dbo].[fileNumber] s WITH (NOLOCK) ON s.id = t.source_id AND t.source_table = 'fileNumber'
JOIN #PMLookup m ON m.key_val IN (
        UPPER(LTRIM(RTRIM(s.NewKANGISFileNo))),
        UPPER(LTRIM(RTRIM(s.kangisFileNo))),
        UPPER(LTRIM(RTRIM(s.mlsfNo))),
        UPPER(LTRIM(RTRIM(s.temp_fileno)))
    )
WHERE t.prop_id IS NULL;

DECLARE @viaFn1 INT = @@ROWCOUNT;
PRINT CONCAT('Backfilled fileNumber rows via source-row lookup: ', @viaFn1, ' rows.');

-- dciv_file_no source: try full_file_number
UPDATE t
SET t.prop_id = CAST(m.prop_id AS NVARCHAR(100)),
    t.file_number = COALESCE(t.file_number, s.full_file_number)
FROM [dbo].[related_file_number] t
JOIN [dbo].[dciv_file_no] s WITH (NOLOCK) ON s.id = t.source_id AND t.source_table = 'dciv_file_no'
JOIN #PMLookup m ON m.key_val = UPPER(LTRIM(RTRIM(s.full_file_number)))
WHERE t.prop_id IS NULL;

DECLARE @viaFn2 INT = @@ROWCOUNT;
PRINT CONCAT('Backfilled dciv_file_no rows via source-row lookup: ', @viaFn2, ' rows.');

DECLARE @updated INT = @viaFn + @viaFn1 + @viaFn2;
PRINT CONCAT('TOTAL backfilled: ', @updated, ' rows.');

-- ---------------------------------------------------------------------------
-- 3) Cleanup
-- ---------------------------------------------------------------------------
DROP TABLE #PMLookup;

-- ---------------------------------------------------------------------------
-- 4) Summary
-- ---------------------------------------------------------------------------
DECLARE @after_null INT = (
    SELECT COUNT(*) FROM [dbo].[related_file_number] WHERE prop_id IS NULL
);
PRINT CONCAT('AFTER:  rows with NULL prop_id = ', @after_null,
             '   (filled ', @before_null - @after_null, ', still null ', @after_null, ')');

-- Breakdown per source
SELECT
    source_table,
    COUNT(*)            AS total,
    COUNT(prop_id)      AS with_prop_id,
    COUNT(*) - COUNT(prop_id) AS still_null
FROM [dbo].[related_file_number]
GROUP BY source_table
ORDER BY total DESC;

-- Sample of rows that still have NULL prop_id (so you can see why they didn't match)
PRINT '=== Sample 10 rows still NULL after backfill ===';
SELECT TOP 10 id, source_table, source_id, file_number, related_fileno
FROM [dbo].[related_file_number]
WHERE prop_id IS NULL
ORDER BY id;
