-- ============================================================================
-- rebuild_propids_all.sql
-- ----------------------------------------------------------------------------
-- Rebuilds prop_id values across all transaction-side tables so every property
-- shares ONE prop_id derived from its mlsFNo (with temp_fileno fallback for
-- rows that don't yet have an official mlsFNo).
--
-- Snapshots OP / ToT lineage BEFORE the rebuild into [propid_rebuild_lineage]
-- so the relationships can be fixed in a follow-up pass.
--
-- Coverage:
--   Primary writers (rebuilt from mlsFNo|temp_fileno):
--     pra, pic, instrument_capture, file_history_staging, CofO_staging
--   Follower tables (remapped via old_prop_id -> new_prop_id translation):
--     caveats, manual_file_linkages, deprecated_records
--   PRA's parent_prop_id is remapped using the same translation.
--
-- PropID_Master is cleared and repopulated from the rebuilt source tables so
-- the allocator's MAX(prop_id)+1 continues cleanly with no collisions.
--
-- NOT touched:
--   file_indexings, fileNumber  -- registry side, separate concern
--   temp_fileno / temp_file_no   -- caller asked to keep
--
-- Target: SQL Server (sqlsrv connection)
-- ============================================================================

SET NOCOUNT ON;
SET XACT_ABORT ON;

-- ---------------------------------------------------------------------------
-- 0) Create the lineage snapshot table (idempotent)
-- ---------------------------------------------------------------------------
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'propid_rebuild_lineage'
)
BEGIN
    CREATE TABLE [dbo].[propid_rebuild_lineage] (
        [id]                  BIGINT IDENTITY(1,1) NOT NULL,
        [batch_run_at]        DATETIME2(0)        NOT NULL,
        [tracking_no]         NVARCHAR(80)        NOT NULL,
        [lineage_group]       BIGINT              NULL,
        [role]                NVARCHAR(10)        NOT NULL,
        [source_table]        NVARCHAR(50)        NOT NULL,
        [source_row_id]       BIGINT              NOT NULL,
        [mlsFNo]              NVARCHAR(255)       NULL,
        [temp_fileno]         NVARCHAR(255)       NULL,
        [old_prop_id]         BIGINT              NULL,
        [old_parent_prop_id]  NVARCHAR(50)        NULL,
        [old_source_op_id]    NVARCHAR(50)        NULL,
        [old_source_op_table] NVARCHAR(50)        NULL,
        [new_prop_id]         BIGINT              NULL,
        CONSTRAINT [PK_propid_rebuild_lineage] PRIMARY KEY CLUSTERED ([id])
    );

    CREATE NONCLUSTERED INDEX [IX_prl_tracking_no]    ON [propid_rebuild_lineage]([tracking_no]);
    CREATE NONCLUSTERED INDEX [IX_prl_lineage_group] ON [propid_rebuild_lineage]([lineage_group]);
    CREATE NONCLUSTERED INDEX [IX_prl_source]         ON [propid_rebuild_lineage]([source_table],[source_row_id]);
    CREATE NONCLUSTERED INDEX [IX_prl_old_prop_id]    ON [propid_rebuild_lineage]([old_prop_id]);

    PRINT 'Created [propid_rebuild_lineage].';
END
GO

DECLARE @batch DATETIME2(0) = SYSUTCDATETIME();

-- ---------------------------------------------------------------------------
-- 1) Snapshot PRA OP/ToT lineage BEFORE rebuilding
--    role = 'TOT' when source_op_id IS NOT NULL else 'OP'
--    lineage_group = OP's old prop_id (COALESCE(parent_prop_id, prop_id))
-- ---------------------------------------------------------------------------
;WITH PraSnap AS (
    SELECT
        p.id, p.prop_id, p.mlsFNo, p.temp_fileno,
        p.parent_prop_id, p.source_op_id, p.source_op_table,
        -- TRY_CAST everywhere: handles non-numeric, overflow (>18 digits),
        -- leading-zero, whitespace cases without throwing.
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
           '-', s.id) AS tracking_no,
    s.lineage_group_val,
    CASE WHEN s.source_op_id IS NOT NULL THEN 'TOT' ELSE 'OP' END,
    'pra',
    s.id,
    s.mlsFNo,
    s.temp_fileno,
    TRY_CAST(s.prop_id AS BIGINT),
    s.parent_prop_id,
    s.source_op_id,
    s.source_op_table,
    NULL
FROM PraSnap s;

PRINT CONCAT('Lineage snapshot: ', @@ROWCOUNT, ' PRA rows captured.');
GO

-- ---------------------------------------------------------------------------
-- 2) Build #PropIDMap: every distinct (M: mlsFNo) and (T: temp_fileno) key
--    gets a sequential new_prop_id starting from 1.
-- ---------------------------------------------------------------------------
IF OBJECT_ID('tempdb..#PropIDMap', 'U') IS NOT NULL DROP TABLE #PropIDMap;

;WITH AllKeys AS (
    SELECT DISTINCT CAST('M:' + UPPER(LTRIM(RTRIM(mlsFNo))) AS NVARCHAR(450)) AS key_val FROM (
        SELECT mlsFNo FROM [dbo].[pra]                   WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
        UNION SELECT mlsFNo FROM [dbo].[pic]             WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
        UNION SELECT mlsFNo FROM [dbo].[instrument_capture] WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
        UNION SELECT mlsFNo FROM [dbo].[file_history_staging] WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
        UNION SELECT mlsFNo FROM [dbo].[CofO_staging]    WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
    ) m
    UNION
    SELECT DISTINCT CAST('T:' + UPPER(LTRIM(RTRIM(temp_fileno))) AS NVARCHAR(450)) FROM (
        SELECT temp_fileno FROM [dbo].[pra]
            WHERE temp_fileno IS NOT NULL AND LTRIM(RTRIM(temp_fileno)) <> ''
              AND (mlsFNo IS NULL OR LTRIM(RTRIM(mlsFNo)) = '')
        UNION SELECT temp_fileno FROM [dbo].[pic]
            WHERE temp_fileno IS NOT NULL AND LTRIM(RTRIM(temp_fileno)) <> ''
              AND (mlsFNo IS NULL OR LTRIM(RTRIM(mlsFNo)) = '')
        UNION SELECT temp_fileno FROM [dbo].[instrument_capture]
            WHERE temp_fileno IS NOT NULL AND LTRIM(RTRIM(temp_fileno)) <> ''
              AND (mlsFNo IS NULL OR LTRIM(RTRIM(mlsFNo)) = '')
        UNION SELECT temp_fileno FROM [dbo].[CofO_staging]
            WHERE temp_fileno IS NOT NULL AND LTRIM(RTRIM(temp_fileno)) <> ''
              AND (mlsFNo IS NULL OR LTRIM(RTRIM(mlsFNo)) = '')
    ) t
)
SELECT key_val,
       ROW_NUMBER() OVER (ORDER BY key_val) AS new_prop_id
INTO #PropIDMap
FROM AllKeys;

CREATE UNIQUE CLUSTERED INDEX IX_PropIDMap_key ON #PropIDMap(key_val);
CREATE NONCLUSTERED INDEX IX_PropIDMap_propid  ON #PropIDMap(new_prop_id);

DECLARE @mapped INT = (SELECT COUNT(*) FROM #PropIDMap);
PRINT CONCAT('PropIDMap built: ', @mapped, ' distinct keys (mlsFNo + temp_fileno fallback).');
GO

-- ---------------------------------------------------------------------------
-- 3) Build old_prop_id -> new_prop_id translation for follower tables.
--    Built from current source state (before NULL pass) by joining each row's
--    identifier to #PropIDMap.
-- ---------------------------------------------------------------------------
IF OBJECT_ID('tempdb..#PropIDTranslation', 'U') IS NOT NULL DROP TABLE #PropIDTranslation;

;WITH SourceRows AS (
    SELECT prop_id, mlsFNo, temp_fileno FROM [dbo].[pra]                WHERE prop_id IS NOT NULL
    UNION ALL SELECT prop_id, mlsFNo, temp_fileno FROM [dbo].[pic]
        WHERE prop_id IS NOT NULL
    UNION ALL SELECT prop_id, mlsFNo, temp_fileno FROM [dbo].[instrument_capture]
        WHERE prop_id IS NOT NULL
    UNION ALL SELECT prop_id, mlsFNo, temp_fileno FROM [dbo].[file_history_staging]
        WHERE prop_id IS NOT NULL
    UNION ALL SELECT prop_id, mlsFNo, temp_fileno FROM [dbo].[CofO_staging]
        WHERE prop_id IS NOT NULL
), Keyed AS (
    SELECT
        s.prop_id AS old_prop_id,
        CASE
            WHEN s.mlsFNo IS NOT NULL AND LTRIM(RTRIM(s.mlsFNo)) <> ''
                THEN 'M:' + UPPER(LTRIM(RTRIM(s.mlsFNo)))
            WHEN s.temp_fileno IS NOT NULL AND LTRIM(RTRIM(s.temp_fileno)) <> ''
                THEN 'T:' + UPPER(LTRIM(RTRIM(s.temp_fileno)))
            ELSE NULL
        END AS key_val
    FROM SourceRows s
)
SELECT old_prop_id, new_prop_id INTO #PropIDTranslation
FROM (
    SELECT k.old_prop_id, m.new_prop_id,
           ROW_NUMBER() OVER (PARTITION BY k.old_prop_id ORDER BY m.new_prop_id) AS rn
    FROM Keyed k
    JOIN #PropIDMap m ON m.key_val = k.key_val
    WHERE k.key_val IS NOT NULL
) d
WHERE rn = 1;   -- one new_prop_id per old_prop_id (deterministic pick)

CREATE UNIQUE CLUSTERED INDEX IX_PropIDTr_old ON #PropIDTranslation(old_prop_id);

DECLARE @trcount INT = (SELECT COUNT(*) FROM #PropIDTranslation);
PRINT CONCAT('Translation map built: ', @trcount, ' old_prop_id -> new_prop_id pairs.');
GO

-- ---------------------------------------------------------------------------
-- 4) NULL prop_id on the 5 primary writers
-- ---------------------------------------------------------------------------
UPDATE [dbo].[pra]                   SET prop_id = NULL;  PRINT CONCAT('Cleared pra:                  ', @@ROWCOUNT);
UPDATE [dbo].[pic]                   SET prop_id = NULL;  PRINT CONCAT('Cleared pic:                  ', @@ROWCOUNT);
UPDATE [dbo].[instrument_capture]    SET prop_id = NULL;  PRINT CONCAT('Cleared instrument_capture:   ', @@ROWCOUNT);
UPDATE [dbo].[file_history_staging]  SET prop_id = NULL;  PRINT CONCAT('Cleared file_history_staging: ', @@ROWCOUNT);
UPDATE [dbo].[CofO_staging]          SET prop_id = NULL;  PRINT CONCAT('Cleared CofO_staging:         ', @@ROWCOUNT);
GO

-- ---------------------------------------------------------------------------
-- 5) Re-apply prop_id from #PropIDMap (mlsFNo preferred, temp_fileno fallback)
-- ---------------------------------------------------------------------------
UPDATE t SET t.prop_id = m.new_prop_id
FROM [dbo].[pra] t
JOIN #PropIDMap m ON m.key_val =
    CASE WHEN t.mlsFNo IS NOT NULL AND LTRIM(RTRIM(t.mlsFNo)) <> ''
              THEN 'M:' + UPPER(LTRIM(RTRIM(t.mlsFNo)))
         WHEN t.temp_fileno IS NOT NULL AND LTRIM(RTRIM(t.temp_fileno)) <> ''
              THEN 'T:' + UPPER(LTRIM(RTRIM(t.temp_fileno)))
    END;
PRINT CONCAT('Wrote pra.prop_id:                  ', @@ROWCOUNT);

UPDATE t SET t.prop_id = m.new_prop_id
FROM [dbo].[pic] t
JOIN #PropIDMap m ON m.key_val =
    CASE WHEN t.mlsFNo IS NOT NULL AND LTRIM(RTRIM(t.mlsFNo)) <> ''
              THEN 'M:' + UPPER(LTRIM(RTRIM(t.mlsFNo)))
         WHEN t.temp_fileno IS NOT NULL AND LTRIM(RTRIM(t.temp_fileno)) <> ''
              THEN 'T:' + UPPER(LTRIM(RTRIM(t.temp_fileno)))
    END;
PRINT CONCAT('Wrote pic.prop_id:                  ', @@ROWCOUNT);

UPDATE t SET t.prop_id = m.new_prop_id
FROM [dbo].[instrument_capture] t
JOIN #PropIDMap m ON m.key_val =
    CASE WHEN t.mlsFNo IS NOT NULL AND LTRIM(RTRIM(t.mlsFNo)) <> ''
              THEN 'M:' + UPPER(LTRIM(RTRIM(t.mlsFNo)))
         WHEN t.temp_fileno IS NOT NULL AND LTRIM(RTRIM(t.temp_fileno)) <> ''
              THEN 'T:' + UPPER(LTRIM(RTRIM(t.temp_fileno)))
    END;
PRINT CONCAT('Wrote instrument_capture.prop_id:   ', @@ROWCOUNT);

UPDATE t SET t.prop_id = m.new_prop_id
FROM [dbo].[file_history_staging] t
JOIN #PropIDMap m ON m.key_val =
    CASE WHEN t.mlsFNo IS NOT NULL AND LTRIM(RTRIM(t.mlsFNo)) <> ''
              THEN 'M:' + UPPER(LTRIM(RTRIM(t.mlsFNo)))
         WHEN t.temp_fileno IS NOT NULL AND LTRIM(RTRIM(t.temp_fileno)) <> ''
              THEN 'T:' + UPPER(LTRIM(RTRIM(t.temp_fileno)))
    END;
PRINT CONCAT('Wrote file_history_staging.prop_id: ', @@ROWCOUNT);

UPDATE t SET t.prop_id = m.new_prop_id
FROM [dbo].[CofO_staging] t
JOIN #PropIDMap m ON m.key_val =
    CASE WHEN t.mlsFNo IS NOT NULL AND LTRIM(RTRIM(t.mlsFNo)) <> ''
              THEN 'M:' + UPPER(LTRIM(RTRIM(t.mlsFNo)))
         WHEN t.temp_fileno IS NOT NULL AND LTRIM(RTRIM(t.temp_fileno)) <> ''
              THEN 'T:' + UPPER(LTRIM(RTRIM(t.temp_fileno)))
    END;
PRINT CONCAT('Wrote CofO_staging.prop_id:         ', @@ROWCOUNT);
GO

-- ---------------------------------------------------------------------------
-- 6) Backfill new_prop_id into the lineage snapshot (so OP and its ToT
--    siblings can be located and re-minted afterwards)
-- ---------------------------------------------------------------------------
UPDATE l SET l.new_prop_id = p.prop_id
FROM [dbo].[propid_rebuild_lineage] l
JOIN [dbo].[pra] p ON p.id = l.source_row_id
WHERE l.source_table = 'pra' AND l.new_prop_id IS NULL;
PRINT CONCAT('Lineage snapshot: backfilled new_prop_id on ', @@ROWCOUNT, ' rows.');
GO

-- ---------------------------------------------------------------------------
-- 7) Remap PRA.parent_prop_id using the translation map
--    (parent_prop_id is stored as NVARCHAR(50) in pra)
-- ---------------------------------------------------------------------------
UPDATE t SET t.parent_prop_id = CAST(tr.new_prop_id AS NVARCHAR(50))
FROM [dbo].[pra] t
JOIN #PropIDTranslation tr ON TRY_CAST(t.parent_prop_id AS BIGINT) = tr.old_prop_id
WHERE t.parent_prop_id IS NOT NULL;
PRINT CONCAT('Remapped pra.parent_prop_id:        ', @@ROWCOUNT);
GO

-- ---------------------------------------------------------------------------
-- 8) Remap follower tables via old_prop_id -> new_prop_id translation
-- ---------------------------------------------------------------------------
UPDATE t SET t.prop_id = tr.new_prop_id
FROM [dbo].[caveats] t
JOIN #PropIDTranslation tr ON tr.old_prop_id = t.prop_id;
PRINT CONCAT('Remapped caveats.prop_id:           ', @@ROWCOUNT);

UPDATE t SET t.prop_id = tr.new_prop_id
FROM [dbo].[manual_file_linkages] t
JOIN #PropIDTranslation tr ON tr.old_prop_id = TRY_CAST(t.prop_id AS BIGINT);
PRINT CONCAT('Remapped manual_file_linkages.prop_id: ', @@ROWCOUNT);

UPDATE t SET t.prop_id = CAST(tr.new_prop_id AS NVARCHAR(100))
FROM [dbo].[deprecated_records] t
JOIN #PropIDTranslation tr ON tr.old_prop_id = TRY_CAST(t.prop_id AS BIGINT);
PRINT CONCAT('Remapped deprecated_records.prop_id: ', @@ROWCOUNT);

UPDATE t SET t.parent_prop_id = CAST(tr.new_prop_id AS NVARCHAR(255))
FROM [dbo].[deprecated_records] t
JOIN #PropIDTranslation tr ON tr.old_prop_id = TRY_CAST(t.parent_prop_id AS BIGINT);
PRINT CONCAT('Remapped deprecated_records.parent_prop_id: ', @@ROWCOUNT);
GO

-- ---------------------------------------------------------------------------
-- 9) Rebuild PropID_Master
--    DELETE all existing rows, reseed the IDENTITY (id) to 0, then INSERT one
--    row per new prop_id with identifiers harvested from the rebuilt sources.
--    primary_file_number = COALESCE(mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno).
--    Future allocations via PropertyIdAllocationService will pick up at
--    MAX(prop_id)+1 with no collisions against the rebuilt source tables.
-- ---------------------------------------------------------------------------
DELETE FROM [dbo].[PropID_Master];
PRINT CONCAT('PropID_Master rows deleted: ', @@ROWCOUNT);

DBCC CHECKIDENT ('[dbo].[PropID_Master]', RESEED, 0) WITH NO_INFOMSGS;
PRINT 'PropID_Master IDENTITY reseeded to 0.';

-- Consolidate identifiers per new prop_id, then dedupe each of the 4 unique
-- identifier columns. PropID_Master enforces filtered UNIQUE indexes on
-- mlsFNo / kangisFileNo / NewKANGISFileno / temp_fileno — same kangisFileNo
-- shared across multiple mlsFNos in the raw data would otherwise collide.
-- For each identifier column, the LOWEST prop_id keeps the value; others
-- get NULL (PropertyIdAllocationService::insertMasterRow does the same).
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

-- Insert. primary_file_number is NOT NULL AND UNIQUE.
-- Falls back through the chain; if every identifier is null we use
-- 'AUTO-REF-{prop_id}'. If two prop_ids end up wanting the same primary
-- (e.g. PRA has mlsFNo='TEMP-00989' while IC has temp_fileno='TEMP-00989'
-- with no mlsFNo) the lower prop_id keeps the clean name and later ones
-- get '<candidate>-REF-<prop_id>' appended. Same dedup pattern that
-- PropertyIdAllocationService::insertMasterRow() uses.
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
-- 10) Cleanup
-- ---------------------------------------------------------------------------
IF OBJECT_ID('tempdb..#PropIDMap', 'U') IS NOT NULL DROP TABLE #PropIDMap;
IF OBJECT_ID('tempdb..#PropIDTranslation', 'U') IS NOT NULL DROP TABLE #PropIDTranslation;
GO

-- ---------------------------------------------------------------------------
-- 11) Summary
-- ---------------------------------------------------------------------------
SELECT 'pra'                  AS tbl, COUNT(*) AS rows_total, COUNT(prop_id) AS with_prop_id FROM [dbo].[pra]
UNION ALL SELECT 'pic',                   COUNT(*), COUNT(prop_id) FROM [dbo].[pic]
UNION ALL SELECT 'instrument_capture',    COUNT(*), COUNT(prop_id) FROM [dbo].[instrument_capture]
UNION ALL SELECT 'file_history_staging',  COUNT(*), COUNT(prop_id) FROM [dbo].[file_history_staging]
UNION ALL SELECT 'CofO_staging',          COUNT(*), COUNT(prop_id) FROM [dbo].[CofO_staging]
UNION ALL SELECT 'caveats',               COUNT(*), COUNT(prop_id) FROM [dbo].[caveats]
UNION ALL SELECT 'manual_file_linkages',  COUNT(*), COUNT(prop_id) FROM [dbo].[manual_file_linkages]
UNION ALL SELECT 'deprecated_records',    COUNT(*), COUNT(prop_id) FROM [dbo].[deprecated_records];

-- Lineage check: OP rows + their ToT siblings, side by side
SELECT
    op.lineage_group,
    op.tracking_no       AS op_tracking,
    op.source_row_id     AS op_pra_id,
    op.old_prop_id       AS op_old_prop_id,
    op.new_prop_id       AS op_new_prop_id,
    tot.tracking_no      AS tot_tracking,
    tot.source_row_id    AS tot_pra_id,
    tot.old_prop_id      AS tot_old_prop_id,
    tot.new_prop_id      AS tot_new_prop_id
FROM [dbo].[propid_rebuild_lineage] op
LEFT JOIN [dbo].[propid_rebuild_lineage] tot
       ON tot.lineage_group = op.lineage_group
      AND tot.role = 'TOT'
WHERE op.role = 'OP'
  AND op.batch_run_at = (SELECT MAX(batch_run_at) FROM [dbo].[propid_rebuild_lineage])
  AND EXISTS (SELECT 1 FROM [dbo].[propid_rebuild_lineage] x
              WHERE x.lineage_group = op.lineage_group AND x.role = 'TOT')
ORDER BY op.lineage_group;
GO

-- ============================================================================
-- PropID_Master is cleared and repopulated by Step 9 from the rebuilt source
-- tables. Future allocations via PropertyIdAllocationService will continue
-- from MAX(prop_id)+1 with no collisions against the new mapping.
-- ============================================================================

-- ============================================================================
-- ROLLBACK helper (manual): the old prop_ids are preserved in
--   propid_rebuild_lineage.old_prop_id  (PRA rows only)
-- Other tables have no backup snapshot — run a full DB restore if needed.
-- ============================================================================
