-- ============================================================================
-- rebuild_propids_recovery.sql
-- ----------------------------------------------------------------------------
-- Run this AFTER rebuild_propids_all.sql failed mid-way at Step 9 with a
-- duplicate-key error on PropID_Master.UX_PropID_Master_primary_file_number.
--
-- Assumes:
--   * Source tables (pra, pic, instrument_capture, file_history_staging,
--     CofO_staging) and follower tables (caveats, manual_file_linkages,
--     deprecated_records) already hold the rebuilt prop_id values.
--   * PropID_Master is currently empty (DELETE + DBCC RESEED already ran;
--     the INSERT rolled back).
--   * propid_rebuild_lineage table exists but is empty (Step 1 failed before
--     any rows were written, and the old prop_ids are no longer recoverable
--     from the source tables).
--
-- This script:
--   A) Populates PropID_Master from the rebuilt sources with collision-safe
--      primary_file_number derivation ('-REF-{prop_id}' suffix on duplicates).
--   B) Repopulates propid_rebuild_lineage from PRA's CURRENT state. The
--      old_prop_id / old_parent_prop_id columns will hold the new (post-
--      rebuild) values since the originals are lost, but the OP/ToT
--      relationship (role, lineage_group) is still correctly recoverable.
--
-- Target: SQL Server (sqlsrv connection)
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

-- ---------------------------------------------------------------------------
-- Safety check: bail if PropID_Master isn't empty (someone re-ran something).
-- ---------------------------------------------------------------------------
IF (SELECT COUNT(*) FROM [dbo].[PropID_Master]) > 0
BEGIN
    RAISERROR('PropID_Master is not empty. Inspect manually before re-running.', 16, 1);
    RETURN;
END
GO

-- ---------------------------------------------------------------------------
-- A1) Reseed PropID_Master IDENTITY (no-op if already reseeded)
-- ---------------------------------------------------------------------------
DBCC CHECKIDENT ('[dbo].[PropID_Master]', RESEED, 0) WITH NO_INFOMSGS;
PRINT 'PropID_Master IDENTITY reseeded to 0.';
GO

-- ---------------------------------------------------------------------------
-- A2) Consolidate identifiers per (rebuilt) prop_id, dedupe ALL 4 unique
--     identifier columns (mlsFNo / kangisFileNo / NewKANGISFileno /
--     temp_fileno — each carries a filtered UNIQUE index), then INSERT.
--
--     PropID_Master enforces these UNIQUE indexes:
--       primary_file_number  (always unique)
--       mlsFNo               (unique where not null)
--       kangisFileNo         (unique where not null)
--       NewKANGISFileno      (unique where not null)
--       temp_fileno          (unique where not null)
--
--     The source data has cases where the same kangisFileNo / temp_fileno
--     is shared across multiple mlsFNos. After the prop_id rebuild keys by
--     mlsFNo, those distinct prop_ids would all want the same kangisFileNo.
--     PropertyIdAllocationService::insertMasterRow "steals" conflicts by
--     NULLing the older holder. We replicate that here: for each unique
--     identifier column, the LOWEST prop_id keeps the value; later prop_ids
--     get NULL for that column. primary_file_number falls back through the
--     remaining identifiers via COALESCE, then to 'AUTO-REF-<prop_id>',
--     with a final '-REF-<prop_id>' suffix on any leftover collision.
--
--     A2 + INSERT kept in a single batch so the temp table stays in scope.
-- ---------------------------------------------------------------------------
IF OBJECT_ID('tempdb..#MasterRebuild', 'U') IS NOT NULL DROP TABLE #MasterRebuild;

;WITH AllSource AS (
    SELECT prop_id, mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno, 'pra' AS src FROM [dbo].[pra]                WHERE prop_id IS NOT NULL
    UNION ALL SELECT prop_id, mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno, 'pic'                FROM [dbo].[pic]                WHERE prop_id IS NOT NULL
    UNION ALL SELECT prop_id, mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno, 'instrument_capture' FROM [dbo].[instrument_capture] WHERE prop_id IS NOT NULL
    UNION ALL SELECT prop_id, mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno, 'file_history_staging' FROM [dbo].[file_history_staging] WHERE prop_id IS NOT NULL
    UNION ALL SELECT prop_id, mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno, 'CofO_staging'       FROM [dbo].[CofO_staging]       WHERE prop_id IS NOT NULL
), Consolidated AS (
    SELECT
        prop_id,
        MAX(NULLIF(LTRIM(RTRIM(mlsFNo)),           '')) AS mlsFNo,
        MAX(NULLIF(LTRIM(RTRIM(kangisFileNo)),     '')) AS kangisFileNo,
        MAX(NULLIF(LTRIM(RTRIM(NewKANGISFileno)),  '')) AS NewKANGISFileno,
        MAX(NULLIF(LTRIM(RTRIM(temp_fileno)),      '')) AS temp_fileno,
        MAX(src)                                         AS source_table
    FROM AllSource
    GROUP BY prop_id
), Deduped AS (
    SELECT
        prop_id,
        CASE WHEN ROW_NUMBER() OVER (PARTITION BY UPPER(mlsFNo)          ORDER BY prop_id) = 1 OR mlsFNo IS NULL          THEN mlsFNo          END AS mlsFNo,
        CASE WHEN ROW_NUMBER() OVER (PARTITION BY UPPER(kangisFileNo)    ORDER BY prop_id) = 1 OR kangisFileNo IS NULL    THEN kangisFileNo    END AS kangisFileNo,
        CASE WHEN ROW_NUMBER() OVER (PARTITION BY UPPER(NewKANGISFileno) ORDER BY prop_id) = 1 OR NewKANGISFileno IS NULL THEN NewKANGISFileno END AS NewKANGISFileno,
        CASE WHEN ROW_NUMBER() OVER (PARTITION BY UPPER(temp_fileno)     ORDER BY prop_id) = 1 OR temp_fileno IS NULL     THEN temp_fileno     END AS temp_fileno,
        source_table
    FROM Consolidated
)
SELECT * INTO #MasterRebuild FROM Deduped;

CREATE UNIQUE CLUSTERED INDEX IX_MasterRebuild_propid ON #MasterRebuild(prop_id);

DECLARE @rb INT = (SELECT COUNT(*) FROM #MasterRebuild);
PRINT CONCAT('#MasterRebuild prepared: ', @rb, ' distinct prop_ids (per-column dedup applied).');

;WITH Candidates AS (
    SELECT
        prop_id,
        COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno,
                 CONCAT('AUTO-REF-', prop_id)) AS candidate,
        mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno, source_table
    FROM #MasterRebuild
), Ranked AS (
    SELECT
        prop_id, candidate, mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno, source_table,
        ROW_NUMBER() OVER (PARTITION BY candidate ORDER BY prop_id) AS rn
    FROM Candidates
)
INSERT INTO [dbo].[PropID_Master]
    (prop_id, primary_file_number, mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno,
     source_table, status, created_at, updated_at)
SELECT
    prop_id,
    CASE WHEN rn = 1 THEN candidate
         ELSE CONCAT(candidate, '-REF-', prop_id)
    END AS primary_file_number,
    mlsFNo,
    kangisFileNo,
    NewKANGISFileno,
    temp_fileno,
    source_table,
    'active',
    SYSUTCDATETIME(),
    SYSUTCDATETIME()
FROM Ranked;

PRINT CONCAT('PropID_Master repopulated: ', @@ROWCOUNT, ' rows.');

DROP TABLE #MasterRebuild;
GO

-- ---------------------------------------------------------------------------
-- B) Repopulate propid_rebuild_lineage from PRA's CURRENT (post-rebuild)
--    state, so OP/ToT pairs can be identified. old_prop_id will hold the
--    NEW prop_id values (originals were lost); old_parent_prop_id holds
--    the NEW parent_prop_id (already remapped in main run Step 7).
-- ---------------------------------------------------------------------------
DECLARE @batch DATETIME2(0) = SYSUTCDATETIME();

;WITH PraSnap AS (
    SELECT
        p.id, p.prop_id, p.mlsFNo, p.temp_fileno,
        p.parent_prop_id, p.source_op_id, p.source_op_table,
        CASE
            WHEN p.parent_prop_id IS NOT NULL
                 AND LTRIM(RTRIM(p.parent_prop_id)) <> ''
                 AND p.parent_prop_id NOT LIKE '%[^0-9]%'
                THEN CAST(p.parent_prop_id AS BIGINT)
            ELSE CAST(p.prop_id AS BIGINT)
        END AS lineage_group_val
    FROM [dbo].[pra] p
    WHERE p.prop_id IS NOT NULL
       OR p.source_op_id IS NOT NULL
       OR p.parent_prop_id IS NOT NULL
)
INSERT INTO [dbo].[propid_rebuild_lineage]
    (batch_run_at, tracking_no, lineage_group, role,
     source_table, source_row_id, mlsFNo, temp_fileno,
     old_prop_id, old_parent_prop_id, old_source_op_id, old_source_op_table,
     new_prop_id)
SELECT
    @batch,
    CONCAT('LIN-', s.lineage_group_val, '-',
           CASE WHEN s.source_op_id IS NOT NULL THEN 'TOT' ELSE 'OP' END,
           '-', s.id),
    s.lineage_group_val,
    CASE WHEN s.source_op_id IS NOT NULL THEN 'TOT' ELSE 'OP' END,
    'pra',
    s.id,
    s.mlsFNo,
    s.temp_fileno,
    s.prop_id,            -- NOTE: this is the NEW prop_id (original lost)
    s.parent_prop_id,     -- NOTE: this is the NEW parent_prop_id (remapped)
    s.source_op_id,
    s.source_op_table,
    s.prop_id             -- new_prop_id = current prop_id
FROM PraSnap s;

PRINT CONCAT('propid_rebuild_lineage repopulated: ', @@ROWCOUNT, ' PRA rows captured.');
GO

-- ---------------------------------------------------------------------------
-- Summary + lineage view
-- ---------------------------------------------------------------------------
SELECT 'PropID_Master' AS tbl, COUNT(*) AS rows_total,
       MIN(prop_id) AS min_prop_id, MAX(prop_id) AS max_prop_id
FROM [dbo].[PropID_Master];

SELECT 'propid_rebuild_lineage' AS tbl, role, COUNT(*) AS row_count
FROM [dbo].[propid_rebuild_lineage]
GROUP BY role;

-- OP rows that have at least one ToT sibling
SELECT
    op.lineage_group,
    op.tracking_no       AS op_tracking,
    op.source_row_id     AS op_pra_id,
    op.new_prop_id       AS op_prop_id,
    tot.tracking_no      AS tot_tracking,
    tot.source_row_id    AS tot_pra_id,
    tot.new_prop_id      AS tot_prop_id
FROM [dbo].[propid_rebuild_lineage] op
LEFT JOIN [dbo].[propid_rebuild_lineage] tot
       ON tot.lineage_group = op.lineage_group
      AND tot.role = 'TOT'
WHERE op.role = 'OP'
  AND EXISTS (SELECT 1 FROM [dbo].[propid_rebuild_lineage] x
              WHERE x.lineage_group = op.lineage_group AND x.role = 'TOT')
ORDER BY op.lineage_group;
GO
