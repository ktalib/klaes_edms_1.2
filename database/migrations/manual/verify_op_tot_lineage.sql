-- ============================================================================
-- verify_op_tot_lineage.sql
-- ----------------------------------------------------------------------------
-- Read-only checks (NO writes) to verify OP / ToT lineage integrity after:
--   1) rebuild_propids_all.sql
--   2) remap_file_indexings_propid.sql
--
-- What "OK" means:
--   * Every ToT row (source_op_id IS NOT NULL) still points to a valid OP row
--     in pra via source_op_id (foreign key not enforced, so we check explicitly).
--   * ToT.parent_prop_id == OP.prop_id  (Step 7 of the main rebuild remapped
--     parent_prop_id to the OP's new prop_id; this verifies that succeeded).
--   * OP rows referenced by some ToT exist in PropID_Master.
--   * ToT rows have a prop_id (otherwise their lineage is half-broken).
-- ============================================================================

SET NOCOUNT ON;

-- ---------------------------------------------------------------------------
-- A) Headline counts
-- ---------------------------------------------------------------------------
PRINT '=== A) Headline counts ===';

SELECT
    (SELECT COUNT(*) FROM pra WHERE source_op_id IS NOT NULL)                                       AS tot_rows,
    (SELECT COUNT(*) FROM pra WHERE source_op_id IS NOT NULL AND prop_id IS NULL)                  AS tot_rows_missing_prop_id,
    (SELECT COUNT(*) FROM pra WHERE source_op_id IS NOT NULL AND parent_prop_id IS NULL)           AS tot_rows_missing_parent_prop_id,
    (SELECT COUNT(DISTINCT parent_prop_id) FROM pra WHERE source_op_id IS NOT NULL AND parent_prop_id IS NOT NULL) AS distinct_op_parents_referenced,
    (SELECT COUNT(*) FROM propid_rebuild_lineage WHERE role = 'TOT')                                AS snapshot_tot_rows;
GO

-- ---------------------------------------------------------------------------
-- B) Per-ToT integrity matrix
--    Show every ToT row alongside the OP it claims (via source_op_id) and
--    flag any inconsistency. parent_prop_id and OP.prop_id must agree.
-- ---------------------------------------------------------------------------
PRINT '=== B) Per-ToT integrity matrix ===';

;WITH ToT AS (
    SELECT
        t.id              AS tot_pra_id,
        t.prop_id         AS tot_prop_id,
        t.parent_prop_id  AS tot_parent_prop_id,
        t.mlsFNo          AS tot_mls,
        t.temp_fileno     AS tot_temp,
        TRY_CAST(t.source_op_id AS BIGINT) AS source_op_id_int,
        t.source_op_table
    FROM pra t
    WHERE t.source_op_id IS NOT NULL
)
SELECT
    tot.tot_pra_id,
    tot.source_op_table,
    tot.source_op_id_int                          AS source_op_pra_id,
    op.id                                          AS op_pra_id_found,
    op.prop_id                                     AS op_prop_id,
    tot.tot_prop_id,
    tot.tot_parent_prop_id,
    tot.tot_mls,
    op.mlsFNo                                      AS op_mls,
    tot.tot_temp,
    op.temp_fileno                                 AS op_temp,
    CASE WHEN op.id IS NULL                                                          THEN 'BROKEN: source_op_id does not exist in pra'
         WHEN tot.tot_prop_id IS NULL                                                THEN 'BROKEN: ToT has no prop_id'
         WHEN op.prop_id IS NULL                                                     THEN 'BROKEN: OP has no prop_id'
         WHEN TRY_CAST(tot.tot_parent_prop_id AS BIGINT) <> op.prop_id                THEN 'MISMATCH: parent_prop_id != OP.prop_id'
         WHEN tot.tot_prop_id = op.prop_id                                            THEN 'COLLAPSED: ToT and OP share the same prop_id (re-mint needed)'
         ELSE 'OK'
    END AS status
FROM ToT
LEFT JOIN pra op ON op.id = tot.source_op_id_int AND (tot.source_op_table IS NULL OR tot.source_op_table = 'pra')
ORDER BY status, tot.tot_pra_id;
GO

-- ---------------------------------------------------------------------------
-- C) Status counts (so you see the breakdown at a glance)
-- ---------------------------------------------------------------------------
PRINT '=== C) Status breakdown ===';

;WITH ToT AS (
    SELECT
        t.id, t.prop_id, t.parent_prop_id, TRY_CAST(t.source_op_id AS BIGINT) AS source_op_id_int, t.source_op_table
    FROM pra t WHERE t.source_op_id IS NOT NULL
)
SELECT status, COUNT(*) AS row_count
FROM (
    SELECT
        CASE WHEN op.id IS NULL                                                          THEN 'BROKEN: source_op_id does not exist in pra'
             WHEN tot.prop_id IS NULL                                                    THEN 'BROKEN: ToT has no prop_id'
             WHEN op.prop_id IS NULL                                                     THEN 'BROKEN: OP has no prop_id'
             WHEN TRY_CAST(tot.parent_prop_id AS BIGINT) <> op.prop_id                   THEN 'MISMATCH: parent_prop_id != OP.prop_id'
             WHEN tot.prop_id = op.prop_id                                                THEN 'COLLAPSED: ToT and OP share the same prop_id (re-mint needed)'
             ELSE 'OK'
        END AS status
    FROM ToT tot
    LEFT JOIN pra op ON op.id = tot.source_op_id_int AND (tot.source_op_table IS NULL OR tot.source_op_table = 'pra')
) d
GROUP BY status
ORDER BY row_count DESC;
GO

-- ---------------------------------------------------------------------------
-- D) PropID_Master coverage of OP / ToT prop_ids
-- ---------------------------------------------------------------------------
PRINT '=== D) PropID_Master coverage ===';

;WITH OpAndTot AS (
    SELECT DISTINCT prop_id, 'OP'  AS role FROM pra WHERE id IN (SELECT TRY_CAST(source_op_id AS BIGINT) FROM pra WHERE source_op_id IS NOT NULL)
    UNION
    SELECT DISTINCT prop_id, 'TOT' AS role FROM pra WHERE source_op_id IS NOT NULL
)
SELECT
    role,
    COUNT(*)                                                  AS distinct_prop_ids,
    SUM(CASE WHEN pm.prop_id IS NOT NULL THEN 1 ELSE 0 END)   AS in_propid_master,
    SUM(CASE WHEN pm.prop_id IS NULL     THEN 1 ELSE 0 END)   AS missing_from_propid_master
FROM OpAndTot o
LEFT JOIN PropID_Master pm ON pm.prop_id = o.prop_id
GROUP BY role;
GO

-- ---------------------------------------------------------------------------
-- E) Snapshot-vs-live spot check
--    Confirms the lineage snapshot agrees with current PRA state.
-- ---------------------------------------------------------------------------
PRINT '=== E) Snapshot vs live spot check ===';

SELECT TOP 20
    l.tracking_no,
    l.role,
    l.source_row_id AS pra_id,
    l.new_prop_id   AS snapshot_prop_id,
    p.prop_id       AS live_prop_id,
    CASE WHEN l.new_prop_id = p.prop_id THEN 'OK' ELSE 'DRIFT' END AS check_result
FROM propid_rebuild_lineage l
JOIN pra p ON p.id = l.source_row_id
WHERE l.source_table = 'pra'
  AND l.batch_run_at = (SELECT MAX(batch_run_at) FROM propid_rebuild_lineage)
ORDER BY l.lineage_group, l.role DESC;
GO

-- ---------------------------------------------------------------------------
-- F) ToT rows whose source_op_id points to a DIFFERENT source table
--    (source_op_table != 'pra'). The main rebuild only touched pra/pic/IC/FH/
--    CofO — if a ToT's source_op_table is something else, it needs its own
--    handling.
-- ---------------------------------------------------------------------------
PRINT '=== F) ToTs pointing to non-pra source tables ===';

SELECT source_op_table, COUNT(*) AS row_count
FROM pra
WHERE source_op_id IS NOT NULL
GROUP BY source_op_table
ORDER BY row_count DESC;
GO
