/*═══════════════════════════════════════════════════════════════════════════════
  Decommission the two subdivision mother files that were linked but left active
  ──────────────────────────────────────────────────────────────────────────────
  CON-RES-2024-308  → 118 child plots (3 Aug 2026, linkage group e95a7299…)
  CON-AG-2022-49    →  36 child plots (3 Aug 2026, linkage group 5e5d82ab…)

  Why they are still active: decommissioned_files.successor_file_no is nvarchar(255),
  and a batch subdivision stores every child there as a CSV list (~2,100 chars for 118
  children). The archive INSERT failed with "String or binary data would be truncated";
  PlotWorkflowService::decommissionFiles() catches per file, so the linkage still
  committed and the mother survived.

  This script does exactly what that service does, in the same order:
     1. widen successor_file_no                     (STEP 1 — the actual fix)
     2. INSERT decommissioned_files                 (archive)
     3. INSERT deprecated_records                   (detailed indexing archive)
     4. DELETE from the active tables               (hard delete, as the service does)
  Legal Search staging rows (pra, CofO_staging, file_history_staging, deed_registrations)
  are deliberately NOT touched — the children's lineage is resolved from them.

  Run in SSMS against [klas]. Everything after STEP 1 is inside one transaction that
  only COMMITs if both files archive cleanly. Read the row counts before committing.

  NOTE: the reason text uses the → character, matching what the app writes. If your
  editor mangles it on paste, replace N'Subdivision → ' with N'Subdivision -> '.
═══════════════════════════════════════════════════════════════════════════════*/

USE [klas];
GO

/*───────────────────────────────────────────────────────────────────────────────
  STEP 1 — widen the two columns that hold the child list (this is what makes the
  archive possible). Both are written from the same text:
    decommissioned_files.successor_file_no  nvarchar(255) — the CSV child list
    deprecated_records.workflow_type        nvarchar(100) — the full reason string
                                            ("Subdivision → CON-…, CON-…"), see
                                            PlotWorkflowService::decommissionFiles()

  successor_file_no's index is dropped, not recreated: every read of it is either
  LIKE '%…%' or UPPER(LTRIM(RTRIM(col))) = ?, so it was never seekable, and
  nvarchar(max) cannot be an index key. workflow_type has no index.
  Equivalent to migration 2026_08_03_120000_widen_successor_file_no_on_decommissioned_files.
───────────────────────────────────────────────────────────────────────────────*/
IF EXISTS (SELECT 1 FROM sys.indexes
           WHERE name = 'decommissioned_files_successor_file_no_index'
             AND object_id = OBJECT_ID('dbo.decommissioned_files'))
    DROP INDEX [decommissioned_files_successor_file_no_index] ON [dbo].[decommissioned_files];
GO

IF EXISTS (SELECT 1 FROM sys.columns
           WHERE object_id = OBJECT_ID('dbo.decommissioned_files')
             AND name = 'successor_file_no'
             AND max_length <> -1)
    ALTER TABLE [dbo].[decommissioned_files] ALTER COLUMN [successor_file_no] NVARCHAR(MAX) NULL;
GO

IF EXISTS (SELECT 1 FROM sys.columns
           WHERE object_id = OBJECT_ID('dbo.deprecated_records')
             AND name = 'workflow_type'
             AND max_length <> -1)
    ALTER TABLE [dbo].[deprecated_records] ALTER COLUMN [workflow_type] NVARCHAR(MAX) NULL;
GO

/*───────────────────────────────────────────────────────────────────────────────
  STEP 2 — build the target list (successors + reason come from the saved linkages,
  so the archived text is identical to what the app would have written)
───────────────────────────────────────────────────────────────────────────────*/
DECLARE @targets TABLE (
    file_no     NVARCHAR(255) PRIMARY KEY,
    successors  NVARCHAR(MAX),
    reason      NVARCHAR(MAX),
    done_by     NVARCHAR(255)
);

INSERT INTO @targets (file_no) VALUES ('CON-RES-2024-308'), ('CON-AG-2022-49');

UPDATE t
   SET successors = s.list,
       reason     = N'Subdivision → ' + s.list,
       done_by    = ISNULL(s.processed_by, 'System')
  FROM @targets t
 CROSS APPLY (
        SELECT STRING_AGG(CAST(m.new_file_number AS NVARCHAR(MAX)), ', ')
                   WITHIN GROUP (ORDER BY m.id)          AS list,
               MIN(m.processed_by)                       AS processed_by
          FROM [dbo].[manual_file_linkages] m
         WHERE m.old_file_numbers LIKE '%"' + t.file_no + '"%'
 ) s;

-- Sanity check — expect 118 and 36 successors. This grid appears in Results while the
-- rest of the batch runs; the guard at the top of STEP 3 aborts if a list came back empty.
SELECT file_no,
       LEN(successors) - LEN(REPLACE(successors, ',', '')) + 1 AS successor_count,
       LEFT(successors, 80) + '…'                              AS successors_preview,
       done_by
  FROM @targets;

/*───────────────────────────────────────────────────────────────────────────────
  STEP 3 — archive + delete
───────────────────────────────────────────────────────────────────────────────*/
BEGIN TRANSACTION;

BEGIN TRY

    -- Refuse to archive a file whose successor list came back empty: successor_file_no
    -- is what Legal Search follows from mother to child, so an empty one is worse than
    -- leaving the file active.
    IF EXISTS (SELECT 1 FROM @targets WHERE successors IS NULL OR LEN(successors) = 0)
        THROW 50001, 'No manual_file_linkages rows found for one of the target files — aborting.', 1;

    ---- 3a. decommissioned_files ------------------------------------------------
    INSERT INTO [dbo].[decommissioned_files]
        ([file_number_id], [file_no], [mls_file_no], [kangis_file_no], [new_kangis_file_no],
         [file_name], [commissioning_date], [decommissioning_date], [decommissioning_reason],
         [decommissioned_by], [false_decommissioning], [successor_file_no], [created_at], [updated_at])
    SELECT
        COALESCE(fn.id, fi.id, 0),
        t.file_no,
        t.file_no,
        COALESCE(fn.kangisFileNo, fi.kangis_file_no),
        COALESCE(fn.NewKANGISFileNo, fi.new_kangis_file_no),
        COALESCE(fn.FileName, fi.file_title, 'N/A'),
        fn.commissioning_date,
        SYSDATETIME(),
        t.reason,
        t.done_by,
        0,
        t.successors,
        SYSDATETIME(),
        SYSDATETIME()
      FROM @targets t
      OUTER APPLY (SELECT TOP 1 * FROM [dbo].[fileNumber]
                    WHERE mlsfNo = t.file_no OR kangisFileNo = t.file_no ORDER BY id) fn
      OUTER APPLY (SELECT TOP 1 * FROM [dbo].[file_indexings]
                    WHERE file_number = t.file_no OR kangis_file_no = t.file_no ORDER BY id) fi
     WHERE (fn.id IS NOT NULL OR fi.id IS NOT NULL)
       AND NOT EXISTS (SELECT 1 FROM [dbo].[decommissioned_files] d WHERE d.mls_file_no = t.file_no);

    PRINT 'decommissioned_files rows inserted: ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' (expected 2)';

    ---- 3b. deprecated_records (only for files that have an indexing row) --------
    INSERT INTO [dbo].[deprecated_records]
        ([file_indexing_id], [file_number], [file_title], [land_use_type], [plot_number],
         [district], [lga], [location], [plot_size], [tp_no], [lpkn_no], [tracking_id],
         [original_holder], [current_holder], [parent_prop_id], [related_fileno],
         [has_transaction], [workflow_type], [decommissioned_by], [decommissioned_at],
         [created_by], [updated_by], [serial_no], [batch_no], [workflow_status],
         [registry], [prop_id], [phone], [residence_address], [general_registry],
         [created_at], [updated_at])
    SELECT
        fi.id, fi.file_number, fi.file_title, fi.land_use_type, fi.plot_number,
        fi.district, fi.lga, fi.location, fi.plot_size, fi.tp_no, fi.lpkn_no, fi.tracking_id,
        fi.original_holder, fi.current_holder, fi.parent_prop_id, fi.related_fileno,
        ISNULL(fi.has_transaction, 0), t.reason, t.done_by, SYSDATETIME(),
        fi.created_by, fi.updated_by, fi.serial_no, fi.batch_no, fi.workflow_status,
        fi.registry, fi.prop_id, fi.phone, fi.residence_address, fi.general_registry,
        SYSDATETIME(), SYSDATETIME()
      FROM @targets t
      JOIN [dbo].[file_indexings] fi
        ON fi.file_number = t.file_no OR fi.kangis_file_no = t.file_no;

    PRINT 'deprecated_records rows inserted: ' + CAST(@@ROWCOUNT AS VARCHAR(10)) + ' (expected 2)';

    ---- 3c. remove the live records ---------------------------------------------
    DELETE fn FROM [dbo].[fileNumber] fn
      JOIN @targets t ON fn.mlsfNo = t.file_no OR fn.kangisFileNo = t.file_no;
    PRINT 'fileNumber rows deleted: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

    DELETE fi FROM [dbo].[file_indexings] fi
      JOIN @targets t ON fi.file_number = t.file_no OR fi.kangis_file_no = t.file_no;
    PRINT 'file_indexings rows deleted: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

    DELETE es FROM [dbo].[entities_staging] es
      JOIN @targets t ON es.file_number = t.file_no;
    PRINT 'entities_staging rows deleted: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

    DELETE cs FROM [dbo].[customers_staging] cs
      JOIN @targets t ON cs.file_number = t.file_no;
    PRINT 'customers_staging rows deleted: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

    DELETE kg FROM [dbo].[kangis_grouping] kg
      JOIN @targets t ON kg.kangis_fileno_placeholder = t.file_no;
    PRINT 'kangis_grouping rows deleted: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

    COMMIT TRANSACTION;
    PRINT 'COMMITTED.';

END TRY
BEGIN CATCH
    ROLLBACK TRANSACTION;
    PRINT 'ROLLED BACK — ' + ERROR_MESSAGE();
END CATCH;
GO

/*───────────────────────────────────────────────────────────────────────────────
  STEP 4 — verify (this is the query that returned 0 rows before)
───────────────────────────────────────────────────────────────────────────────*/
SELECT id, file_no, file_name, decommissioning_date,
       LEFT(decommissioning_reason, 60) + '…' AS reason_preview,
       LEN(successor_file_no)                 AS successor_len,
       decommissioned_by
  FROM [klas].[dbo].[decommissioned_files]
 WHERE file_no IN ('CON-AG-2022-49', 'CON-RES-2024-308');

-- Both should now be gone from the active tables (expect 0 rows each).
SELECT 'fileNumber' AS tbl, COUNT(*) AS still_live FROM [dbo].[fileNumber]
 WHERE mlsfNo IN ('CON-AG-2022-49', 'CON-RES-2024-308')
UNION ALL
SELECT 'file_indexings', COUNT(*) FROM [dbo].[file_indexings]
 WHERE file_number IN ('CON-AG-2022-49', 'CON-RES-2024-308');
GO
