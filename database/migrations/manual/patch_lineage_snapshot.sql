-- ============================================================================
-- patch_lineage_snapshot.sql
-- ----------------------------------------------------------------------------
-- Run this on PROD if rebuild_propids_all.sql Step 1 (lineage snapshot)
-- failed silently and propid_rebuild_lineage is empty.
--
-- Repopulates the snapshot from PRA's CURRENT (post-rebuild) state. The
-- old_prop_id / old_parent_prop_id columns will hold the NEW prop_ids since
-- the originals are not recoverable, but the OP/ToT relationship
-- (role, lineage_group, source_op_id pointing to OP's pra.id) is still
-- correctly recoverable because Step 7 of the main rebuild already remapped
-- parent_prop_id to the new prop_id space.
--
-- Uses TRY_CAST defensively to avoid the nvarchar-to-bigint error that
-- caused Step 1 to fail (overflow / non-numeric values in parent_prop_id).
--
-- Target: SQL Server (sqlsrv connection)
-- Safe to re-run: filters out rows already captured for the latest batch.
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @batch DATETIME2(0) = SYSUTCDATETIME();

;WITH PraSnap AS (
    SELECT
        p.id, p.prop_id, p.mlsFNo, p.temp_fileno,
        p.parent_prop_id, p.source_op_id, p.source_op_table,
        COALESCE(
            TRY_CAST(LTRIM(RTRIM(p.parent_prop_id)) AS BIGINT),
            TRY_CAST(p.prop_id AS BIGINT)
        ) AS lineage_group_val
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
    TRY_CAST(s.prop_id AS BIGINT),   -- NOTE: this is the NEW prop_id; original lost
    s.parent_prop_id,                 -- NOTE: this is the NEW parent_prop_id (remapped in main run Step 7)
    s.source_op_id,
    s.source_op_table,
    TRY_CAST(s.prop_id AS BIGINT)    -- new_prop_id = current prop_id
FROM PraSnap s
WHERE NOT EXISTS (
    SELECT 1 FROM [dbo].[propid_rebuild_lineage] x
    WHERE x.source_table = 'pra' AND x.source_row_id = s.id
);

PRINT CONCAT('propid_rebuild_lineage populated: ', @@ROWCOUNT, ' rows.');

-- Summary so you can sanity-check
SELECT role, COUNT(*) AS row_count
FROM [dbo].[propid_rebuild_lineage]
WHERE batch_run_at = @batch
GROUP BY role
ORDER BY role;

-- Side-by-side OP/ToT pairs that have a sibling
SELECT
    op.lineage_group,
    op.tracking_no    AS op_tracking,
    op.source_row_id  AS op_pra_id,
    op.new_prop_id    AS op_prop_id,
    tot.tracking_no   AS tot_tracking,
    tot.source_row_id AS tot_pra_id,
    tot.new_prop_id   AS tot_prop_id,
    CASE WHEN op.new_prop_id = tot.new_prop_id THEN 'COLLAPSED' ELSE 'DISTINCT' END AS status
FROM [dbo].[propid_rebuild_lineage] op
JOIN [dbo].[propid_rebuild_lineage] tot
       ON tot.lineage_group = op.lineage_group
      AND tot.role = 'TOT'
      AND tot.batch_run_at = op.batch_run_at
WHERE op.role = 'OP'
  AND op.batch_run_at = @batch
ORDER BY op.lineage_group;
GO
