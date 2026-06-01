-- ============================================================================
-- remap_file_indexings_propid.sql
-- ----------------------------------------------------------------------------
-- After rebuild_propids_all.sql has run, file_indexings.prop_id values are
-- STALE — they were allocated against the old PropID_Master numbering scheme
-- and no longer point to any current PropID_Master row.
--
-- This script remaps file_indexings.prop_id (and parent_prop_id where present)
-- using PropID_Master as the lookup table:
--
--   file_indexings.file_number      -> PropID_Master.mlsFNo
--   file_indexings.mls_file_no      -> PropID_Master.mlsFNo (fallback)
--   file_indexings.temp_file_no     -> PropID_Master.temp_fileno (fallback)
--
-- Rows whose identifiers don't match any PropID_Master entry have their
-- prop_id set to NULL (the stale value is unsafe to leave in place — it now
-- points to an unrelated property).
--
-- Target: SQL Server (sqlsrv connection)
-- Prerequisite: rebuild_propids_all.sql has completed.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

-- ---------------------------------------------------------------------------
-- 0) Pre-flight snapshot
-- ---------------------------------------------------------------------------
DECLARE @before_with_prop INT = (SELECT COUNT(*) FROM [dbo].[file_indexings] WHERE prop_id IS NOT NULL);
DECLARE @before_with_parent INT = (SELECT COUNT(*) FROM [dbo].[file_indexings] WHERE parent_prop_id IS NOT NULL);
PRINT CONCAT('BEFORE: file_indexings rows with prop_id = ', @before_with_prop,
             ', with parent_prop_id = ', @before_with_parent);

-- ---------------------------------------------------------------------------
-- 1) Build a lookup map keyed by all identifier columns of PropID_Master.
--    PropID_Master enforces UNIQUE on mlsFNo / temp_fileno (filtered to non-
--    null) so each key maps to exactly one prop_id.
-- ---------------------------------------------------------------------------
IF OBJECT_ID('tempdb..#PMLookup', 'U') IS NOT NULL DROP TABLE #PMLookup;

;WITH AllKeys AS (
    SELECT prop_id, CAST('M:' + UPPER(LTRIM(RTRIM(mlsFNo))) AS NVARCHAR(450)) AS key_val
    FROM [dbo].[PropID_Master]
    WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
    UNION ALL
    SELECT prop_id, CAST('M:' + UPPER(LTRIM(RTRIM(kangisFileNo))) AS NVARCHAR(450))
    FROM [dbo].[PropID_Master]
    WHERE kangisFileNo IS NOT NULL AND LTRIM(RTRIM(kangisFileNo)) <> ''
    UNION ALL
    SELECT prop_id, CAST('M:' + UPPER(LTRIM(RTRIM(NewKANGISFileno))) AS NVARCHAR(450))
    FROM [dbo].[PropID_Master]
    WHERE NewKANGISFileno IS NOT NULL AND LTRIM(RTRIM(NewKANGISFileno)) <> ''
    UNION ALL
    SELECT prop_id, CAST('T:' + UPPER(LTRIM(RTRIM(temp_fileno))) AS NVARCHAR(450))
    FROM [dbo].[PropID_Master]
    WHERE temp_fileno IS NOT NULL AND LTRIM(RTRIM(temp_fileno)) <> ''
)
SELECT key_val, MIN(prop_id) AS prop_id   -- if two distinct prop_ids hold the same key (shouldn't happen post-rebuild, but defensive), pick the lowest
INTO #PMLookup
FROM AllKeys
GROUP BY key_val;

CREATE UNIQUE CLUSTERED INDEX IX_PMLookup_key ON #PMLookup(key_val);

DECLARE @keys INT = (SELECT COUNT(*) FROM #PMLookup);
PRINT CONCAT('#PMLookup built: ', @keys, ' unique identifier keys.');

-- ---------------------------------------------------------------------------
-- 2) NULL out current (stale) prop_id and parent_prop_id values in
--    file_indexings so the remap is a clean rewrite.
-- ---------------------------------------------------------------------------
UPDATE [dbo].[file_indexings] SET prop_id = NULL WHERE prop_id IS NOT NULL;
PRINT CONCAT('Cleared file_indexings.prop_id on ', @@ROWCOUNT, ' rows.');

UPDATE [dbo].[file_indexings] SET parent_prop_id = NULL WHERE parent_prop_id IS NOT NULL;
PRINT CONCAT('Cleared file_indexings.parent_prop_id on ', @@ROWCOUNT, ' rows.');

-- ---------------------------------------------------------------------------
-- 3) Remap prop_id via identifier lookup (file_number / mls_file_no /
--    temp_file_no — first match wins).
-- ---------------------------------------------------------------------------
UPDATE t SET t.prop_id = m.prop_id
FROM [dbo].[file_indexings] t
JOIN #PMLookup m ON m.key_val =
    CASE
        WHEN t.file_number IS NOT NULL AND LTRIM(RTRIM(t.file_number)) <> ''
             THEN 'M:' + UPPER(LTRIM(RTRIM(t.file_number)))
        WHEN t.mls_file_no IS NOT NULL AND LTRIM(RTRIM(t.mls_file_no)) <> ''
             THEN 'M:' + UPPER(LTRIM(RTRIM(t.mls_file_no)))
        WHEN t.temp_file_no IS NOT NULL AND LTRIM(RTRIM(t.temp_file_no)) <> ''
             THEN 'T:' + UPPER(LTRIM(RTRIM(t.temp_file_no)))
    END;
PRINT CONCAT('Remapped file_indexings.prop_id on ', @@ROWCOUNT, ' rows.');

-- ---------------------------------------------------------------------------
-- 4) Remap parent_prop_id where the file_indexings row points to a parent
--    that we can also resolve through the same identifier lookup.
--    file_indexings.parent_prop_id was historically populated only on
--    sub-applications / child files where the parent has its OWN file_number.
--    We don't have an explicit "parent file number" column, so this is a
--    best-effort pass — if you have a parent-identifier column we missed,
--    extend this UPDATE to use it.
-- ---------------------------------------------------------------------------
-- (Intentionally no-op for now; parent_prop_id was nulled in Step 2 and
--  there's no parent-file-identifier column on file_indexings to look up.
--  If a join via subapplications or another table is required, add it here.)
PRINT 'parent_prop_id remap: skipped (no parent-identifier column on file_indexings).';

-- ---------------------------------------------------------------------------
-- 5) Cleanup
-- ---------------------------------------------------------------------------
IF OBJECT_ID('tempdb..#PMLookup', 'U') IS NOT NULL DROP TABLE #PMLookup;

-- ---------------------------------------------------------------------------
-- 6) Summary
-- ---------------------------------------------------------------------------
DECLARE @after_with_prop INT = (SELECT COUNT(*) FROM [dbo].[file_indexings] WHERE prop_id IS NOT NULL);
DECLARE @lost INT = @before_with_prop - @after_with_prop;
PRINT CONCAT('AFTER:  file_indexings rows with prop_id = ', @after_with_prop,
             '  (', @lost, ' rows had prop_id but no resolvable identifier in PropID_Master)');

SELECT
    'file_indexings.prop_id' AS metric,
    @before_with_prop        AS before_count,
    @after_with_prop         AS after_count,
    @after_with_prop - @before_with_prop AS delta;
GO

-- ============================================================================
-- ROLLBACK helper (manual): there is no automatic snapshot of the old
-- (stale) prop_id values. If you need to restore them, use the database
-- backup taken before rebuild_propids_all.sql ran.
-- ============================================================================
