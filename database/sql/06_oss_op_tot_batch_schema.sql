/*
    OSS Batch Capture OP / OP-ToT update — PRODUCTION SCHEMA.

    Run this once on production BEFORE deploying the code for this feature.

    - Idempotent: guarded with IF-NOT-EXISTS checks, safe to run repeatedly.
    - SCHEMA ONLY. There are NO data changes here — every data write done while
      building this feature was a rolled-back verification test, so nothing on
      production needs to be back-filled.
    - Run in SSMS / sqlcmd (uses GO batch separators).

    What the feature needs on `pra`:
      op_batch          — shared batch id stamped on every captured OP row
                          (NO migration exists for this column — this is the one
                           production is most likely missing)
      source_op_table   \
      source_op_id       > OP<->ToT lineage (migration 2026_04_30_120000)
      parent_prop_id    /
      op_serial_number  — captured OP serial (migration 2026_03_05_120001)
    Plus the temp_fileno_sequence table used to allocate TEMP-##### numbers.
*/

------------------------------------------------------------------------
-- 1. op_batch  (NO migration exists for these — most likely missing on prod)
--    pra.op_batch          : stamped on every captured OP row and its ToT row
--    mls_file_no.op_batch  : stamped on the commissioned file at link time, so
--                            the batch is discoverable in the OP Batch view
------------------------------------------------------------------------
IF COL_LENGTH('dbo.pra', 'op_batch') IS NULL
    ALTER TABLE dbo.pra ADD op_batch NVARCHAR(50) NULL;
GO
IF COL_LENGTH('dbo.mls_file_no', 'op_batch') IS NULL
    ALTER TABLE dbo.mls_file_no ADD op_batch NVARCHAR(50) NULL;
GO

------------------------------------------------------------------------
-- 2. pra OP<->ToT lineage columns + indexes (migration 2026_04_30_120000)
------------------------------------------------------------------------
IF COL_LENGTH('dbo.pra', 'source_op_table') IS NULL
    ALTER TABLE dbo.pra ADD source_op_table NVARCHAR(50) NULL;
GO
IF COL_LENGTH('dbo.pra', 'source_op_id') IS NULL
    ALTER TABLE dbo.pra ADD source_op_id BIGINT NULL;
GO
IF COL_LENGTH('dbo.pra', 'parent_prop_id') IS NULL
    ALTER TABLE dbo.pra ADD parent_prop_id VARCHAR(50) NULL;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_pra_source_op' AND object_id = OBJECT_ID('dbo.pra'))
    CREATE INDEX IX_pra_source_op ON dbo.pra (source_op_table, source_op_id);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_pra_parent_prop_id' AND object_id = OBJECT_ID('dbo.pra'))
    CREATE INDEX IX_pra_parent_prop_id ON dbo.pra (parent_prop_id);
GO

------------------------------------------------------------------------
-- 3. pra.op_serial_number (migration 2026_03_05_120001)
------------------------------------------------------------------------
IF COL_LENGTH('dbo.pra', 'op_serial_number') IS NULL
    ALTER TABLE dbo.pra ADD op_serial_number NVARCHAR(100) NULL;
GO

------------------------------------------------------------------------
-- 4. temp_fileno_sequence  (TEMP-##### allocation for captured OPs)
--    Mirrors migrations 2026_02_07_221337 + 2026_02_19_183033.
------------------------------------------------------------------------
IF OBJECT_ID('dbo.temp_fileno_sequence', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.temp_fileno_sequence (
        id         BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_temp_fileno_sequence PRIMARY KEY,
        created_by BIGINT   NULL,
        is_used    BIT      NOT NULL CONSTRAINT DF_temp_fileno_sequence_is_used DEFAULT (0),
        created_at DATETIME NULL,
        updated_at DATETIME NULL
    );

    -- Reseed identity so new TEMP numbers continue AFTER the highest existing TEMP-#####.
    DECLARE @maxTemp INT = 0;
    SELECT @maxTemp = ISNULL(MAX(val), 0) FROM (
        SELECT CAST(SUBSTRING(temp_fileno, 6, LEN(temp_fileno)) AS INT) AS val
        FROM dbo.pra
        WHERE temp_fileno LIKE 'TEMP-%' AND ISNUMERIC(SUBSTRING(temp_fileno, 6, LEN(temp_fileno))) = 1
        UNION ALL
        SELECT CAST(SUBSTRING(temp_fileno, 6, LEN(temp_fileno)) AS INT)
        FROM dbo.instrument_capture
        WHERE temp_fileno LIKE 'TEMP-%' AND ISNUMERIC(SUBSTRING(temp_fileno, 6, LEN(temp_fileno))) = 1
    ) t;

    IF @maxTemp > 0
        DBCC CHECKIDENT ('dbo.temp_fileno_sequence', RESEED, @maxTemp);
END
GO
-- is_used column, if the table pre-existed without it.
IF COL_LENGTH('dbo.temp_fileno_sequence', 'is_used') IS NULL
    ALTER TABLE dbo.temp_fileno_sequence ADD is_used BIT NOT NULL CONSTRAINT DF_temp_fileno_sequence_is_used DEFAULT (0);
GO

PRINT 'OSS OP/ToT batch schema check complete.';
GO

/*
    NOT included on purpose:
      * pra_tot_staging2 and the OPB-0001..0376 remediation data — that is the
        separate OP-batch remediation, not this feature. This feature's shipped
        path (Batch Capture OP -> reopen Commission -> Generate -> link) does not
        touch pra_tot_staging2. Only the unused `op-match-tot-batch` endpoint does.
      * pra.purpose — the app does NOT write it (this DB has no such column); Purpose
        flows through the commission record's purpose_id instead.
*/
