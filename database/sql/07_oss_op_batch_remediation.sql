/*
    OSS OP Batch Commissioning — PRODUCTION REMEDIATION (schema + data).

    Companion to 06_oss_op_tot_batch_schema.sql (which is schema-only, for the Batch Capture
    OP feature). THIS script is different: it also carries DATA changes, because the OP Batch
    Commissioning screen only shows records that this script flags.

    WHAT IT FIXES
      Batch Mode was mistakenly enabled on the OP Change-of-Name commissioning flow. Between
      2026-06-09 and 2026-07-14, 376 file numbers were commissioned through it. The single-record
      flow creates a PAIR of pra rows sharing one prop_id — Row 1 'Occupancy Permit (OP)' and
      Row 2 'Transfer of Title (OP)'. The batch path only ever created Row 1, so every one of
      those files is missing its Transfer of Title sibling.

      This script:
        1. adds the tracking column `op_batch` to mls_file_no + pra,
        2. stamps a tracking id OPB-0001..OPB-NNNN on each affected file (both tables),
        3. RELABELS the affected pra rows to 'Transfer of Title (OP)' (the row that carries the
           commissioned mlsFNo is the ToT — a snapshot is taken first, see REVERT below),
        4. builds pra_tot_staging2, one row per affected file, to track the OP backfill.

    SAFETY
      - Idempotent. Schema steps are IF-NOT-EXISTS. The data step is skipped entirely if any
        OPB-NNNN tracking id already exists, so re-running is a no-op.
      - The data step runs in ONE transaction and aborts on any error (XACT_ABORT).
      - Nothing is deleted. The relabel is snapshotted into pra_op_batch_relabel_backup first.
      - Run in SSMS / sqlcmd (uses GO batch separators). TAKE A BACKUP FIRST.

    EXPECTED ON PRODUCTION (verified against the live restore, 2026-07-16)
        mls_file_no flagged : 376
        pra flagged/relabel : 374   (2 files have no pra row at all -> staging status='ignored')
        staging rows        : 376
      2 pra rows that share a file number but are NOT ours are deliberately left alone:
      'Deed of Sub Under Lease' (IND-2026-39) and 'Right of Occupancy' (RES-2026-3009).
      They predate the batch run; the instrument_type filter below excludes them.

    REVERT THE RELABEL (if ever needed)
        UPDATE p SET instrument_type = b.old_instrument_type,
                     transaction_type = b.old_transaction_type
        FROM dbo.pra p
        JOIN dbo.pra_op_batch_relabel_backup b ON b.pra_id = p.id;
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

------------------------------------------------------------------------
-- 1. Tracking column: mls_file_no.op_batch / pra.op_batch
--    (pra.op_batch may already exist from 06_oss_op_tot_batch_schema.sql)
------------------------------------------------------------------------
IF COL_LENGTH('dbo.mls_file_no', 'op_batch') IS NULL
    ALTER TABLE dbo.mls_file_no ADD op_batch NVARCHAR(50) NULL;
GO
IF COL_LENGTH('dbo.pra', 'op_batch') IS NULL
    ALTER TABLE dbo.pra ADD op_batch NVARCHAR(50) NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_mls_file_no_op_batch' AND object_id = OBJECT_ID('dbo.mls_file_no'))
    CREATE INDEX IX_mls_file_no_op_batch ON dbo.mls_file_no (op_batch);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_pra_op_batch' AND object_id = OBJECT_ID('dbo.pra'))
    CREATE INDEX IX_pra_op_batch ON dbo.pra (op_batch);
GO

------------------------------------------------------------------------
-- 2. pra_tot_staging2 — one row per affected file, tracks the OP backfill.
--    MUST exist before the code is deployed: Capture OP writes to it on every link.
------------------------------------------------------------------------
IF OBJECT_ID('dbo.pra_tot_staging2', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.pra_tot_staging2
    (
        id               BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_pra_tot_staging2 PRIMARY KEY,
        op_batch         NVARCHAR(50)   NOT NULL,
        mls_id           BIGINT         NOT NULL,
        full_file_number NVARCHAR(100)  NOT NULL,
        batch_no         NVARCHAR(50)   NULL,
        created_by       NVARCHAR(255)  NULL,
        source           NVARCHAR(50)   NULL,
        sub_source       NVARCHAR(50)   NULL,
        op_pra_id        BIGINT         NULL,
        prop_id          NVARCHAR(100)  NULL,
        op_type          NVARCHAR(100)  NULL,
        transaction_type NVARCHAR(100)  NULL,
        party_1          VARCHAR(255)   NULL,
        party_2          VARCHAR(255)   NULL,
        pra_row_count    INT            NOT NULL,
        has_op           BIT            NOT NULL,
        has_tot          BIT            NOT NULL,
        status           NVARCHAR(20)   NOT NULL,
        is_processed     BIT            NOT NULL,
        processed_at     DATETIME       NULL,
        processed_by     INT            NULL,
        remarks          NVARCHAR(1000) NULL,
        created_at       DATETIME       NOT NULL,
        has_op_row       BIT            NULL
    );

    CREATE UNIQUE INDEX UX_pra_tot_staging2_op_batch ON dbo.pra_tot_staging2 (op_batch);
    CREATE INDEX IX_pra_tot_staging2_file ON dbo.pra_tot_staging2 (full_file_number);
END
GO

------------------------------------------------------------------------
-- 3. pra_op_batch_relabel_backup — pre-relabel snapshot (the undo).
------------------------------------------------------------------------
IF OBJECT_ID('dbo.pra_op_batch_relabel_backup', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.pra_op_batch_relabel_backup
    (
        id                  BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_pra_op_batch_relabel_backup PRIMARY KEY,
        pra_id              BIGINT        NOT NULL,
        op_batch            NVARCHAR(50)  NULL,
        mlsFNo              NVARCHAR(100) NULL,
        prop_id             NVARCHAR(100) NULL,
        old_instrument_type NVARCHAR(150) NULL,
        old_transaction_type NVARCHAR(100) NULL,
        old_party_1         VARCHAR(255)  NULL,
        old_party_2         VARCHAR(255)  NULL,
        changed_at          DATETIME      NOT NULL,
        note                NVARCHAR(400) NULL
    );
END
GO

------------------------------------------------------------------------
-- 4. DATA. Skipped entirely if the remediation has already run.
------------------------------------------------------------------------
IF EXISTS (SELECT 1 FROM dbo.mls_file_no WHERE op_batch LIKE 'OPB-[0-9][0-9][0-9][0-9]')
BEGIN
    PRINT 'Remediation tracking ids already present — data step skipped (nothing to do).';
END
ELSE
BEGIN
    BEGIN TRANSACTION;

    ----------------------------------------------------------------
    -- 4a. Stamp OPB-NNNN on every in-scope file, ordered by mls_file_no.id.
    --     Scope predicate uses the exact production condition:
    --     created_by='ABDULSAMAD ADO SALISU' AND batch_no IS NOT NULL.
    ----------------------------------------------------------------
    ;WITH s AS (
        SELECT id, ROW_NUMBER() OVER (ORDER BY id) AS rn
        FROM dbo.mls_file_no
        WHERE created_by = 'ABDULSAMAD ADO SALISU'
          AND batch_no IS NOT NULL
    )
    UPDATE m
    SET op_batch = 'OPB-' + RIGHT('0000' + CAST(s.rn AS VARCHAR(10)), 4)
    FROM dbo.mls_file_no m
    JOIN s ON s.id = m.id;

    DECLARE @mls INT = @@ROWCOUNT;
    PRINT 'mls_file_no flagged: ' + CAST(@mls AS VARCHAR(10));

    ----------------------------------------------------------------
    -- 4b. Mirror the tracking id onto the matching pra OP row.
    --     instrument_type filter excludes the 2 pre-existing non-OP
    --     instruments that happen to share a flagged file number.
    ----------------------------------------------------------------
    UPDATE p
    SET op_batch = m.op_batch
    FROM dbo.pra p
    JOIN dbo.mls_file_no m
      ON UPPER(LTRIM(RTRIM(p.mlsFNo))) = UPPER(LTRIM(RTRIM(m.full_file_number)))
    WHERE m.op_batch IS NOT NULL
      AND p.instrument_type = 'Occupancy Permit (OP)'
      AND (p.is_deleted IS NULL OR p.is_deleted = 0);

    DECLARE @praFlagged INT = @@ROWCOUNT;
    PRINT 'pra flagged: ' + CAST(@praFlagged AS VARCHAR(10));

    ----------------------------------------------------------------
    -- 4c. Snapshot BEFORE the relabel.
    ----------------------------------------------------------------
    INSERT INTO dbo.pra_op_batch_relabel_backup
        (pra_id, op_batch, mlsFNo, prop_id, old_instrument_type, old_transaction_type,
         old_party_1, old_party_2, changed_at, note)
    SELECT p.id, p.op_batch, p.mlsFNo, p.prop_id, p.instrument_type, p.transaction_type,
           p.party_1, p.party_2, SYSDATETIME(),
           'Pre-relabel snapshot: OP Batch Commissioning remediation'
    FROM dbo.pra p
    WHERE p.op_batch LIKE 'OPB-[0-9][0-9][0-9][0-9]';

    PRINT 'backup rows: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

    ----------------------------------------------------------------
    -- 4d. RELABEL. The row carrying the commissioned mlsFNo is the ToT.
    --     op_type is deliberately preserved — a correct ToT inherits it.
    ----------------------------------------------------------------
    UPDATE dbo.pra
    SET instrument_type  = 'Transfer of Title (OP)',
        transaction_type = 'Transfer of Title (OP)'
    WHERE op_batch LIKE 'OPB-[0-9][0-9][0-9][0-9]';

    PRINT 'pra relabelled: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

    ----------------------------------------------------------------
    -- 4e. Staging: one row per affected file.
    --     status 'pending'  -> awaiting an OP (the backfill target)
    --     status 'ignored'  -> file has no pra row at all, nothing to backfill
    ----------------------------------------------------------------
    INSERT INTO dbo.pra_tot_staging2
        (op_batch, mls_id, full_file_number, batch_no, created_by, source, sub_source,
         op_pra_id, prop_id, op_type, transaction_type, party_1, party_2,
         pra_row_count, has_op, has_tot, status, is_processed, remarks, created_at, has_op_row)
    SELECT
        m.op_batch, m.id, m.full_file_number, m.batch_no, m.created_by, m.source, m.sub_source,
        p.id, p.prop_id, p.op_type, p.transaction_type, p.party_1, p.party_2,
        ISNULL(rc.cnt, 0),
        CASE WHEN p.id IS NULL THEN 0 ELSE 1 END,
        CASE WHEN p.id IS NULL THEN 0 ELSE 1 END,
        CASE WHEN p.id IS NULL THEN 'ignored' ELSE 'pending' END,
        0,
        CASE WHEN p.id IS NULL
             THEN 'No pra row matches this file number — nothing to backfill'
             ELSE 'Relabelled to Transfer of Title (OP); party_1 still Kano State Government (pending fix); OP row absent for this prop_id'
                  + CASE WHEN ISNULL(rc.cnt, 0) > 1
                         THEN '; NOTE: ' + CAST(rc.cnt AS VARCHAR(10)) + ' pra rows share this file number (pre-existing non-OP instrument left unflagged)'
                         ELSE '' END
        END,
        SYSDATETIME(),
        CASE WHEN p.id IS NULL THEN NULL ELSE 0 END
    FROM dbo.mls_file_no m
    LEFT JOIN dbo.pra p
           ON p.op_batch = m.op_batch
    OUTER APPLY (
        SELECT COUNT(*) AS cnt
        FROM dbo.pra x
        WHERE UPPER(LTRIM(RTRIM(x.mlsFNo))) = UPPER(LTRIM(RTRIM(m.full_file_number)))
          AND (x.is_deleted IS NULL OR x.is_deleted = 0)
    ) rc
    WHERE m.op_batch LIKE 'OPB-[0-9][0-9][0-9][0-9]';

    PRINT 'staging rows: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

    COMMIT TRANSACTION;
    PRINT 'Remediation committed.';
END
GO

------------------------------------------------------------------------
-- 5. Verification — expect 376 / 374 / 376 / 0 / 0
------------------------------------------------------------------------
SELECT
    (SELECT COUNT(*) FROM dbo.mls_file_no WHERE op_batch LIKE 'OPB-[0-9][0-9][0-9][0-9]')          AS mls_flagged,
    (SELECT COUNT(*) FROM dbo.pra         WHERE op_batch LIKE 'OPB-[0-9][0-9][0-9][0-9]')          AS pra_flagged,
    (SELECT COUNT(*) FROM dbo.pra_tot_staging2)                                                     AS staging_rows,
    (SELECT COUNT(*) FROM dbo.pra_op_batch_relabel_backup)                                          AS backup_rows,
    -- must be 0: every flagged pra row is now a ToT
    (SELECT COUNT(*) FROM dbo.pra WHERE op_batch LIKE 'OPB-[0-9][0-9][0-9][0-9]'
                                    AND instrument_type <> 'Transfer of Title (OP)')                AS not_relabelled,
    -- must be 0: no tracking id may map to more than one pra row
    (SELECT COUNT(*) FROM (SELECT op_batch FROM dbo.pra
                            WHERE op_batch LIKE 'OPB-[0-9][0-9][0-9][0-9]'
                            GROUP BY op_batch HAVING COUNT(*) > 1) d)                               AS duplicate_tracking_ids;

-- The 2 pre-existing non-OP instruments MUST remain unflagged (expect 2 rows, op_batch NULL).
SELECT id, mlsFNo, instrument_type, prop_id, op_batch
FROM dbo.pra
WHERE instrument_type IN ('Deed of Sub Under Lease', 'Right of Occupancy')
  AND UPPER(LTRIM(RTRIM(mlsFNo))) IN (
        SELECT UPPER(LTRIM(RTRIM(full_file_number))) FROM dbo.mls_file_no
        WHERE op_batch LIKE 'OPB-[0-9][0-9][0-9][0-9]'
  );

SELECT status, COUNT(*) AS rows_per_status FROM dbo.pra_tot_staging2 GROUP BY status;
GO


