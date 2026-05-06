/*
 * Script: backfill_st_file_numbers_into_fileNumber.sql
 * Purpose: Populate fileNumber.st_file_no with the authoritative ST file numbers
 *          from st_file_numbers for records created before the ST integration
 *          reached the fileNumber table.
 *
 * Usage notes:
 *   - Review each preview query before running the UPDATE statements.
 *   - Execute inside a SQL Server session connected to the KLAES GIS EDMS database.
 *   - Wrap the UPDATE section in an explicit transaction so you can ROLLBACK if needed.
 *   - Always take a database backup or export the affected rows before running in production.
 */

/*
--------------------------------------------------------------------------------
STEP 0: Optional – capture a backup snapshot of the current fileNumber rows that
    will be touched by this script. Uncomment the SELECT INTO block below to
    persist a point-in-time copy in a staging table.
--------------------------------------------------------------------------------
*/
-- IF OBJECT_ID('dbo.fileNumber_st_sync_backup', 'U') IS NOT NULL
-- BEGIN
--     DROP TABLE dbo.fileNumber_st_sync_backup;
-- END;
--
-- SELECT fn.*
-- INTO dbo.fileNumber_st_sync_backup
-- FROM dbo.fileNumber fn
-- WHERE (fn.st_file_no IS NULL OR LTRIM(RTRIM(fn.st_file_no)) = '')
--   AND (fn.is_deleted = 0 OR fn.is_deleted IS NULL)
--   AND EXISTS (
--         SELECT 1
--         FROM dbo.st_file_numbers st
--         WHERE LTRIM(RTRIM(UPPER(ISNULL(st.fileno, '')))) <> ''
--   );

/*
--------------------------------------------------------------------------------
STEP 1: Build a reusable mapping table
    - #targets_to_backfill holds eligible fileNumber rows
    - #st_backfill_matches resolves the best ST record per fileNumber row
--------------------------------------------------------------------------------
*/
IF OBJECT_ID('tempdb..#targets_to_backfill', 'U') IS NOT NULL DROP TABLE #targets_to_backfill;
IF OBJECT_ID('tempdb..#st_backfill_matches', 'U') IS NOT NULL DROP TABLE #st_backfill_matches;

SELECT
  fn.id,
  fn.mlsfNo,
  fn.kangisFileNo,
  fn.NewKANGISFileNo,
  fn.st_file_no,
  LTRIM(RTRIM(UPPER(ISNULL(fn.mlsfNo, ''))))          AS key_mls,
  LTRIM(RTRIM(UPPER(ISNULL(fn.kangisFileNo, ''))))     AS key_kangis,
  LTRIM(RTRIM(UPPER(ISNULL(fn.NewKANGISFileNo, '')))) AS key_new_kangis
INTO #targets_to_backfill
FROM dbo.fileNumber fn
WHERE (fn.st_file_no IS NULL OR LTRIM(RTRIM(fn.st_file_no)) = '')
  AND (fn.is_deleted = 0 OR fn.is_deleted IS NULL);

WITH st_registry AS (
  SELECT
    st.id,
    st.file_no_type,
    st.mls_fileno,
    st.np_fileno,
    st.fileno AS st_fileno,
    LTRIM(RTRIM(UPPER(ISNULL(st.mls_fileno, '')))) AS key_mls,
    LTRIM(RTRIM(UPPER(ISNULL(st.np_fileno, ''))))  AS key_np,
    LTRIM(RTRIM(UPPER(ISNULL(st.fileno, ''))))     AS key_final
  FROM dbo.st_file_numbers st
  WHERE LTRIM(RTRIM(ISNULL(st.fileno, ''))) <> ''
),
match_candidates AS (
  SELECT
    t.id AS fileNumber_id,
    st.id AS st_record_id,
    st.st_fileno,
    st.file_no_type,
    st.mls_fileno,
    st.np_fileno,
    keys.match_source,
    keys.match_value,
    CASE
      WHEN keys.match_source = 'mlsfNo'       AND st.key_mls   = keys.match_value THEN 1
      WHEN keys.match_source = 'mlsfNo'       AND st.key_np    = keys.match_value THEN 2
      WHEN keys.match_source = 'mlsfNo'       AND st.key_final = keys.match_value THEN 3
      WHEN keys.match_source = 'kangisFileNo' AND st.key_final = keys.match_value THEN 4
      WHEN keys.match_source = 'kangisFileNo' AND st.key_mls   = keys.match_value THEN 5
      WHEN keys.match_source = 'newKangisFileNo' AND st.key_final = keys.match_value THEN 6
      WHEN keys.match_source = 'newKangisFileNo' AND st.key_mls   = keys.match_value THEN 7
      ELSE 8
    END AS priority_group
  FROM #targets_to_backfill t
  CROSS APPLY (VALUES
    ('mlsfNo',        t.key_mls),
    ('kangisFileNo',  t.key_kangis),
    ('newKangisFileNo', t.key_new_kangis)
  ) keys(match_source, match_value)
  JOIN st_registry st
    ON keys.match_value <> ''
   AND (
      st.key_mls   = keys.match_value
     OR st.key_np    = keys.match_value
     OR st.key_final = keys.match_value
    )
),
ranked_matches AS (
  SELECT mc.*,
       ROW_NUMBER() OVER (
         PARTITION BY mc.fileNumber_id
         ORDER BY mc.priority_group, mc.st_record_id
       ) AS rn
  FROM match_candidates mc
)
SELECT
  rm.fileNumber_id,
  rm.st_record_id,
  rm.st_fileno,
  rm.file_no_type,
  rm.mls_fileno,
  rm.np_fileno,
  rm.match_source,
  rm.match_value,
  rm.priority_group
INTO #st_backfill_matches
FROM ranked_matches rm
WHERE rm.rn = 1
  AND rm.priority_group < 8;

/*
--------------------------------------------------------------------------------
STEP 2: Preview the mappings that will be applied. Inspect this result set before
    running the update to ensure the pairings are correct.
--------------------------------------------------------------------------------
*/
SELECT
  m.fileNumber_id,
  t.mlsfNo AS current_mlsfNo,
  t.kangisFileNo,
  t.NewKANGISFileNo,
  t.st_file_no AS existing_st_file_no,
  m.st_fileno AS st_file_no_to_apply,
  m.file_no_type,
  m.mls_fileno,
  m.np_fileno,
  m.match_source,
  m.match_value
FROM #st_backfill_matches m
JOIN #targets_to_backfill t ON t.id = m.fileNumber_id
ORDER BY m.fileNumber_id;

/*
--------------------------------------------------------------------------------
STEP 3: List any fileNumber rows that still have no match (should be empty once
    backfill logic covers all scenarios). Use this for troubleshooting.
--------------------------------------------------------------------------------
*/
SELECT t.*
FROM #targets_to_backfill t
WHERE NOT EXISTS (
  SELECT 1
  FROM #st_backfill_matches m
  WHERE m.fileNumber_id = t.id
);

/*
--------------------------------------------------------------------------------
STEP 4: Backfill the ST numbers. Run inside an explicit transaction so you can
    validate the impact before COMMIT. Remember to manually COMMIT once you
    confirm the preview looks good.
--------------------------------------------------------------------------------
*/
BEGIN TRANSACTION;

  UPDATE fn
    SET fn.st_file_no = m.st_fileno,
      fn.updated_at = GETDATE()
  FROM dbo.fileNumber fn
  INNER JOIN #st_backfill_matches m
      ON m.fileNumber_id = fn.id
  WHERE (fn.st_file_no IS NULL OR LTRIM(RTRIM(fn.st_file_no)) = '')
    AND (fn.is_deleted = 0 OR fn.is_deleted IS NULL);

  DECLARE @rows INT = @@ROWCOUNT;
  PRINT CONCAT('Rows updated in fileNumber: ', @rows);

  SELECT fn.id,
       fn.mlsfNo,
       fn.st_file_no,
       fn.updated_at
  FROM dbo.fileNumber fn
  WHERE fn.id IN (SELECT fileNumber_id FROM #st_backfill_matches)
    AND fn.updated_at >= DATEADD(MINUTE, -5, GETDATE())
  ORDER BY fn.updated_at DESC;

-- COMMIT TRANSACTION;   -- <<< IMPORTANT: Uncomment after verifying the results above
-- ROLLBACK TRANSACTION; -- Use this instead of COMMIT if validation fails

/*
--------------------------------------------------------------------------------
STEP 5: Post-run validation checklist (manual actions)
--------------------------------------------------------------------------------
1. Confirm that the row count printed after the UPDATE matches the number of
   expected backfill records from STEP 2.
2. Spot-check a handful of updated records in the application UI to ensure the
   ST file number now appears under "Capture Existing File Numbers".
3. If additional unmatched rows remain (see STEP 3), investigate whether their
   ST numbers live in other legacy tables such as file_commissioning_sheets.
4. Once satisfied, remember to drop the temp tables if you plan to rerun:
    DROP TABLE IF EXISTS #st_backfill_matches, #targets_to_backfill;
--------------------------------------------------------------------------------
*/
