/* ============================================================================
   IC-ONLY fix for the 7 known duplicate temp_fileno rows
   ----------------------------------------------------------------------------
   Targets only the 7 NEWER duplicate instrument_capture rows identified by the
   audit. Each row gets a fresh TEMP-XXXXX from temp_fileno_sequence, and its
   prop_id is cleared so the normal allocation flow can rebind cleanly.

   The 7 KEEPER rows (717, 720, 721, 1883, 3796, 3723, 1700) are NOT touched.
   The pra table is NOT touched (audit showed 0 PRA cross-prop_id duplicates).

   Re-runnable: if any of the 7 rogues no longer share their temp_fileno with
   the keeper, the script reports it and skips that row.
============================================================================ */

USE klas;        -- adjust if your production DB name differs
SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRAN;

DECLARE @ic_to_fix TABLE (
    ic_id    BIGINT PRIMARY KEY,
    keeper   BIGINT,
    old_temp VARCHAR(50) NULL,
    new_temp VARCHAR(50) NULL,
    note     VARCHAR(200) NULL
);

INSERT INTO @ic_to_fix (ic_id, keeper) VALUES
    (2360, 717),
    (2606, 720),
    (3437, 721),
    (2682, 1883),
    (3973, 3796),
    (3743, 3723),
    (2423, 1700);

/* Snapshot current temp_fileno + sanity checks. Skip any row that:
   - no longer exists
   - already has a different temp_fileno from its keeper (already fixed)
   - has been reassigned a prop_id since the audit (out of scope) */
UPDATE r
SET    r.old_temp = ic.temp_fileno,
       r.note = CASE
           WHEN ic.id IS NULL THEN 'SKIP: ic row not found'
           WHEN keeper_ic.id IS NULL THEN 'SKIP: keeper row not found'
           WHEN ic.temp_fileno IS NULL OR LTRIM(RTRIM(ic.temp_fileno)) = '' THEN 'SKIP: temp_fileno already empty'
           WHEN ic.temp_fileno <> keeper_ic.temp_fileno THEN 'SKIP: already differs from keeper'
           ELSE 'OK'
       END
FROM   @ic_to_fix r
LEFT   JOIN instrument_capture ic        ON ic.id        = r.ic_id
LEFT   JOIN instrument_capture keeper_ic ON keeper_ic.id = r.keeper;

DECLARE @ic_id BIGINT, @new_id INT, @new_temp VARCHAR(50);
DECLARE ic_cur CURSOR LOCAL FAST_FORWARD FOR
    SELECT ic_id FROM @ic_to_fix WHERE note = 'OK' ORDER BY ic_id;

OPEN ic_cur;
FETCH NEXT FROM ic_cur INTO @ic_id;
WHILE @@FETCH_STATUS = 0
BEGIN
    INSERT INTO temp_fileno_sequence (created_by, is_used, created_at, updated_at)
    VALUES (NULL, 1, GETDATE(), GETDATE());

    SET @new_id   = SCOPE_IDENTITY();
    SET @new_temp = 'TEMP-' + RIGHT('00000' + CAST(@new_id AS VARCHAR(10)), 5);

    UPDATE instrument_capture
    SET    temp_fileno = @new_temp,
           prop_id     = NULL,
           updated_at  = GETDATE()
    WHERE  id = @ic_id;

    UPDATE @ic_to_fix SET new_temp = @new_temp WHERE ic_id = @ic_id;

    FETCH NEXT FROM ic_cur INTO @ic_id;
END
CLOSE ic_cur;
DEALLOCATE ic_cur;


/* ──────────────────────────────────────────────────────────────────────────
   VERIFICATION — review these results before COMMIT/ROLLBACK
────────────────────────────────────────────────────────────────────────── */
PRINT '=== Plan + result for the 7 rogue rows ===';
SELECT * FROM @ic_to_fix ORDER BY ic_id;

PRINT '=== Snapshot of all 14 rows (7 keepers + 7 rogues) after the fix ===';
SELECT id, temp_fileno, prop_id, mlsFNo, instrument_type,
       op_serial_number, party_1_name, party_2_name,
       created_at, updated_at
FROM   instrument_capture
WHERE  id IN (717, 2360, 720, 2606, 721, 3437,
              1883, 2682, 3796, 3973, 3723, 3743, 1700, 2423)
ORDER  BY id;

PRINT '=== Remaining IC temp_fileno duplicates (should be 0 rows) ===';
SELECT temp_fileno, COUNT(*) AS row_count
FROM   instrument_capture
WHERE  temp_fileno IS NOT NULL
  AND  LTRIM(RTRIM(temp_fileno)) <> ''
  AND  UPPER(LTRIM(RTRIM(temp_fileno))) LIKE 'TEMP-%'
GROUP  BY temp_fileno
HAVING COUNT(*) > 1;

PRINT '=== OSS applications referencing any of the 14 IC rows ===';
SELECT oa.id AS oss_application_id, oa.file_no, oa.applicant_name,
       oa.instrument_capture_id, ic.temp_fileno, ic.prop_id
FROM   oss_applications oa
JOIN   instrument_capture ic ON ic.id = oa.instrument_capture_id
WHERE  ic.id IN (717, 2360, 720, 2606, 721, 3437,
                 1883, 2682, 3796, 3973, 3723, 3743, 1700, 2423)
ORDER  BY oa.id;

/* If everything looks right: */
-- COMMIT;
/* If anything looks wrong: */
-- ROLLBACK;
