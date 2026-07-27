/* ============================================================================
   Backfill file_indexings rows for fileNumber records that have no matching
   file_indexings entry.

   Mapping:  fileNumber.mlsfNo | kangisFileNo | NewKANGISFileNo -> file_indexings.file_number
             fileNumber.id                                      -> file_indexings.file_number_id

   Match/dedup key: normalized number = UPPER, strip  -  /  space  .
   A fileNumber row is skipped when EITHER
     - one of its three numbers already exists in file_indexings (normalized), OR
     - its id already appears as file_indexings.file_number_id.

   Idempotent: safe to re-run (re-run inserts nothing once backfilled).

   NOTES / caveats vs. the app logic:
     * registry (physical 1/2/3) comes from RegistryDetector + config/file_ranges.php
       in the app and cannot be reproduced in plain SQL -> left NULL here.
     * general_registry IS derived below (pure prefix logic, mirrors the
       backfill_general_registry migration).
     * prop_id is intentionally left NULL (allocation is sensitive; let the
       allocation service assign it later).
     * land_use_type is derived by string parsing (token before the 4-digit year).

   Run STEP 1 (preview) first. Only run STEP 2 (INSERT) once the count looks right.
   ========================================================================== */

SET NOCOUNT ON;

/* -- Build helper temp tables: existing signatures -------------------------- */
IF OBJECT_ID('tempdb..#fi_existing') IS NOT NULL DROP TABLE #fi_existing;
SELECT DISTINCT
    norm = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(file_number)),'-',''),'/',''),' ',''),'.',''))
INTO #fi_existing
FROM file_indexings
WHERE file_number IS NOT NULL
  AND LTRIM(RTRIM(file_number)) <> '';
CREATE CLUSTERED INDEX ix_fi_existing_norm ON #fi_existing(norm);

IF OBJECT_ID('tempdb..#fi_ids') IS NOT NULL DROP TABLE #fi_ids;
SELECT DISTINCT file_number_id
INTO #fi_ids
FROM file_indexings
WHERE file_number_id IS NOT NULL;
CREATE CLUSTERED INDEX ix_fi_ids ON #fi_ids(file_number_id);

/* -- Candidate set: non-deleted fileNumber rows still missing from indexing -- */
IF OBJECT_ID('tempdb..#cand') IS NOT NULL DROP TABLE #cand;
SELECT
    fn.id,
    fn.FileName,
    fn.tracking_id,
    fn.mlsfNo,
    fn.kangisFileNo,
    fn.NewKANGISFileNo,
    fn.dciv_fileno,
    fn.plot_no,
    fn.tp_no,
    fn.location,
    fn.lga,
    fn.district,
    fn.has_transaction,
    fn.title_status,
    fn.title_status_type,
    fn.title_status_remark,
    fn.has_temp_file,
    fn.temp_file_no,
    fn.kangis_fileno_placeholder,
    fn.kangis_fileno_resolved,
    fn.parent_prop_id,
    fn.created_by,
    fn.updated_by,
    fn.created_at,
    p.primary_no
INTO #cand
FROM fileNumber fn
CROSS APPLY (
    SELECT primary_no = COALESCE(
        NULLIF(LTRIM(RTRIM(fn.mlsfNo)),''),
        NULLIF(LTRIM(RTRIM(fn.kangisFileNo)),''),
        NULLIF(LTRIM(RTRIM(fn.NewKANGISFileNo)),'')
    )
) p
WHERE (fn.is_deleted IS NULL OR fn.is_deleted = 0)
  AND p.primary_no IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM #fi_ids i WHERE i.file_number_id = fn.id)
  AND NOT EXISTS (
        SELECT 1 FROM #fi_existing e
        WHERE e.norm IN (
            UPPER(REPLACE(REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(fn.mlsfNo)),'-',''),'/',''),' ',''),'.','')),
            UPPER(REPLACE(REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(fn.kangisFileNo)),'-',''),'/',''),' ',''),'.','')),
            UPPER(REPLACE(REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(fn.NewKANGISFileNo)),'-',''),'/',''),' ',''),'.',''))
        )
        AND e.norm <> ''
  );

/* ============================================================================
   STEP 1 — PREVIEW.  Run this and confirm the count (expected ~886).
   ========================================================================== */
SELECT COUNT(*) AS rows_to_backfill FROM #cand;
-- SELECT TOP 50 id, primary_no, FileName, tracking_id FROM #cand ORDER BY id;

/* ============================================================================
   STEP 2 — INSERT.  Uncomment the block below to write the rows.
   ========================================================================== */
/*
BEGIN TRAN;

INSERT INTO file_indexings (
    file_number, file_number_id, file_title, tracking_id, land_use_type,
    plot_number, tp_no, location, lga, district,
    mls_file_no, kangis_file_no, new_kangis_file_no, dciv_fileno,
    has_transaction, title_status, title_status_type, title_status_remark,
    has_temp_file, temp_file_no, kangis_fileno_placeholder, kangis_fileno_resolved,
    parent_prop_id, workflow_status, source, date_migrated, migrated_by,
    is_deleted, created_by, updated_by, created_at, updated_at, general_registry
)
SELECT
    c.primary_no,
    c.id,
    NULLIF(LTRIM(RTRIM(c.FileName)),''),
    NULLIF(LTRIM(RTRIM(c.tracking_id)),''),
    lu.land_use,                                   -- derived land_use_type
    NULLIF(LTRIM(RTRIM(c.plot_no)),''),
    NULLIF(LTRIM(RTRIM(c.tp_no)),''),
    NULLIF(LTRIM(RTRIM(c.location)),''),
    NULLIF(LTRIM(RTRIM(c.lga)),''),
    NULLIF(LTRIM(RTRIM(c.district)),''),
    NULLIF(LTRIM(RTRIM(c.mlsfNo)),''),
    NULLIF(LTRIM(RTRIM(c.kangisFileNo)),''),
    NULLIF(LTRIM(RTRIM(c.NewKANGISFileNo)),''),
    NULLIF(LTRIM(RTRIM(c.dciv_fileno)),''),
    COALESCE(c.has_transaction, 0),
    c.title_status,
    c.title_status_type,
    c.title_status_remark,
    COALESCE(c.has_temp_file, 0),
    NULLIF(LTRIM(RTRIM(c.temp_file_no)),''),
    c.kangis_fileno_placeholder,
    c.kangis_fileno_resolved,
    c.parent_prop_id,
    'indexed',                                     -- workflow_status
    'backfill_fileNumber',                         -- source (audit marker)
    SYSDATETIME(),                                 -- date_migrated
    'backfill_sql',                                -- migrated_by
    0,                                             -- is_deleted
    COALESCE(NULLIF(LTRIM(RTRIM(c.created_by)),''), 'backfill_sql'),
    COALESCE(NULLIF(LTRIM(RTRIM(c.updated_by)),''), 'backfill_sql'),
    COALESCE(c.created_at, SYSDATETIME()),
    SYSDATETIME(),
    -- general_registry (mirrors backfill_general_registry migration)
    CASE
        WHEN UPPER(c.primary_no) LIKE 'ST-%' THEN 'ST Registry'
        WHEN UPPER(c.primary_no) LIKE 'DCIV-%' OR UPPER(c.primary_no) LIKE 'DCIV/%' THEN 'DCIV Registry'
        WHEN UPPER(c.primary_no) LIKE 'SLTR-%' OR UPPER(c.primary_no) LIKE 'SLTR/%' THEN 'SLTR Registry'
        WHEN UPPER(c.primary_no) LIKE 'SIT-%' OR UPPER(c.primary_no) LIKE 'SIT/%' THEN 'SIT Registry'
        WHEN UPPER(c.primary_no) LIKE 'GKN-%' OR UPPER(c.primary_no) LIKE 'GKN/%' OR UPPER(c.primary_no) LIKE 'GKN %'
          OR UPPER(c.primary_no) LIKE 'LPKN-%' OR UPPER(c.primary_no) LIKE 'LPKN/%' OR UPPER(c.primary_no) LIKE 'LPKN %'
            THEN 'Survey Registry'
        WHEN UPPER(c.primary_no) LIKE 'KNML%' OR UPPER(c.primary_no) LIKE 'MLKN%'
          OR UPPER(c.primary_no) LIKE 'MNKL%' OR UPPER(c.primary_no) LIKE 'KNGP%'
            THEN 'KANGIS Registry'
        WHEN UPPER(c.primary_no) LIKE 'RES-%' OR UPPER(c.primary_no) LIKE 'RES/%'
          OR UPPER(c.primary_no) LIKE 'COM-%' OR UPPER(c.primary_no) LIKE 'COM/%'
          OR UPPER(c.primary_no) LIKE 'IND-%' OR UPPER(c.primary_no) LIKE 'IND/%'
          OR UPPER(c.primary_no) LIKE 'AG-%'  OR UPPER(c.primary_no) LIKE 'AG/%'
          OR UPPER(c.primary_no) LIKE 'MISC-%' OR UPPER(c.primary_no) LIKE 'MISC/%'
          OR UPPER(c.primary_no) LIKE 'CON-RES%' OR UPPER(c.primary_no) LIKE 'CON-COM%'
          OR UPPER(c.primary_no) LIKE 'CON-IND%' OR UPPER(c.primary_no) LIKE 'CON-AG%'
          OR UPPER(c.primary_no) LIKE 'CON-MISC%'
            THEN 'Lands Registry'
        ELSE NULL
    END
FROM #cand c
CROSS APPLY (
    /* land_use_type = the alpha token immediately before the 4-digit year.
       e.g. CON-RES-1983-1054 -> RES ; RES-1981-132 -> RES ; DCIV-2023-465 -> DCIV */
    SELECT n2 = REPLACE(UPPER(c.primary_no), '/', '-')
) a
CROSS APPLY (
    SELECT yp = PATINDEX('%-[12][0-9][0-9][0-9]%', a.n2)
) y
CROSS APPLY (
    SELECT left_part = CASE WHEN y.yp > 0 THEN LEFT(a.n2, y.yp - 1) ELSE a.n2 END
) l
CROSS APPLY (
    SELECT land_use = REVERSE(LEFT(REVERSE(l.left_part),
        CHARINDEX('-', REVERSE(l.left_part) + '-') - 1))
) lu;

PRINT 'Rows inserted: ' + CAST(@@ROWCOUNT AS varchar(20));

-- Review, then:  COMMIT;   (or ROLLBACK; to discard)
COMMIT;
*/
