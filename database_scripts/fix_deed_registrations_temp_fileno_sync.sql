/* ============================================================================
   AUDIT + FIX duplicate temp_fileno values in deed_registrations
   ----------------------------------------------------------------------------
   NOTE: in deed_registrations the column is named `fileno` (NOT temp_fileno).
   It still stores TEMP-XXXXX style values that mirror instrument_capture.temp_fileno.

   Diagnosis
   ---------
   Each deed_registrations row links to instrument_capture via
   instrument_capture_id, and snapshots the IC temp_fileno into dr.fileno.
   The duplicates you see are stale snapshots (different IC ids, same fileno),
   while IC.temp_fileno itself is now unique per row.

   Fix policy
   ----------
   Authoritative value lives in instrument_capture.temp_fileno. For every
   deed_registrations row whose fileno disagrees with its linked IC row,
   copy the IC value down. No new sequence allocations — just sync.

   How to run
   ----------
   Step 1: Run PART 1 (AUDIT). Save the results.
   Step 2: If the AUDIT preview looks right, run PART 2 (FIX). It runs in a
           transaction; review the verification output, then COMMIT or ROLLBACK.
============================================================================ */

USE klas;        -- adjust if your production DB name differs
SET NOCOUNT ON;
SET XACT_ABORT ON;


/* ============================================================================
   ████  PART 1 — AUDIT (READ ONLY, safe to run anytime)  ████
============================================================================ */

PRINT '=== AUDIT 1: deed_registrations duplicate fileno groups (TEMP-*) ===';
;WITH dr_dups AS (
    SELECT fileno, COUNT(*) AS row_count
    FROM   deed_registrations
    WHERE  fileno IS NOT NULL
      AND  LTRIM(RTRIM(fileno)) <> ''
      AND  UPPER(LTRIM(RTRIM(fileno))) LIKE 'TEMP-%'
    GROUP  BY fileno
    HAVING COUNT(*) > 1
)
SELECT fileno, row_count
FROM   dr_dups
ORDER  BY row_count DESC, fileno;


PRINT '=== AUDIT 2: per-row detail with IC linkage and current IC temp_fileno ===';
;WITH dr_dups AS (
    SELECT fileno
    FROM   deed_registrations
    WHERE  fileno IS NOT NULL
      AND  LTRIM(RTRIM(fileno)) <> ''
      AND  UPPER(LTRIM(RTRIM(fileno))) LIKE 'TEMP-%'
    GROUP  BY fileno
    HAVING COUNT(*) > 1
)
SELECT dr.id                 AS dr_id,
       dr.fileno             AS dr_fileno,
       dr.instrument_capture_id,
       ic.temp_fileno        AS ic_temp_fileno,
       CASE
           WHEN ic.id IS NULL                                              THEN 'orphan: no matching ic'
           WHEN ic.temp_fileno IS NULL OR LTRIM(RTRIM(ic.temp_fileno)) = '' THEN 'ic temp_fileno empty'
           WHEN ic.temp_fileno = dr.fileno                                 THEN 'already in sync'
           ELSE 'will update to ic value'
       END                   AS [plan],
       ic.prop_id            AS ic_prop_id,
       ic.mlsFNo             AS ic_mlsFNo,
       ic.instrument_type    AS ic_instrument_type,
       dr.created_at         AS dr_created_at
FROM   deed_registrations dr
JOIN   dr_dups d ON d.fileno = dr.fileno
LEFT   JOIN instrument_capture ic ON ic.id = dr.instrument_capture_id
ORDER  BY dr.fileno, dr.id;


PRINT '=== AUDIT 3: counts summary ===';
SELECT 'DR duplicate fileno groups' AS metric,
       COUNT(*) AS value
FROM   (SELECT fileno
        FROM   deed_registrations
        WHERE  fileno IS NOT NULL AND LTRIM(RTRIM(fileno)) <> ''
          AND  UPPER(LTRIM(RTRIM(fileno))) LIKE 'TEMP-%'
        GROUP  BY fileno
        HAVING COUNT(*) > 1) g
UNION ALL
SELECT 'DR rows currently out of sync with IC',
       COUNT(*)
FROM   deed_registrations dr
JOIN   instrument_capture ic ON ic.id = dr.instrument_capture_id
WHERE  dr.fileno IS NOT NULL
  AND  LTRIM(RTRIM(dr.fileno)) <> ''
  AND  UPPER(LTRIM(RTRIM(dr.fileno))) LIKE 'TEMP-%'
  AND  ic.temp_fileno IS NOT NULL
  AND  LTRIM(RTRIM(ic.temp_fileno)) <> ''
  AND  dr.fileno <> ic.temp_fileno
UNION ALL
SELECT 'DR rows whose IC has empty/null temp_fileno (cannot auto-sync)',
       COUNT(*)
FROM   deed_registrations dr
LEFT   JOIN instrument_capture ic ON ic.id = dr.instrument_capture_id
WHERE  dr.fileno IS NOT NULL
  AND  LTRIM(RTRIM(dr.fileno)) <> ''
  AND  UPPER(LTRIM(RTRIM(dr.fileno))) LIKE 'TEMP-%'
  AND  (ic.id IS NULL
        OR ic.temp_fileno IS NULL
        OR LTRIM(RTRIM(ic.temp_fileno)) = '');


/* STOP HERE if you only wanted the audit.
   The FIX section below makes changes inside a transaction. */
GOTO end_of_script;


/* ============================================================================
   ████  PART 2 — FIX (TRANSACTIONAL — review then COMMIT or ROLLBACK)  ████
============================================================================ */

BEGIN TRAN;

DECLARE @changed TABLE (
    dr_id        BIGINT PRIMARY KEY,
    ic_id        BIGINT,
    old_fileno   VARCHAR(50),
    new_fileno   VARCHAR(50)
);

/* Capture the rows we are about to change, with before/after values. */
INSERT INTO @changed (dr_id, ic_id, old_fileno, new_fileno)
SELECT dr.id, ic.id, dr.fileno, ic.temp_fileno
FROM   deed_registrations dr
JOIN   instrument_capture  ic ON ic.id = dr.instrument_capture_id
WHERE  dr.fileno IS NOT NULL
  AND  LTRIM(RTRIM(dr.fileno)) <> ''
  AND  UPPER(LTRIM(RTRIM(dr.fileno))) LIKE 'TEMP-%'
  AND  ic.temp_fileno  IS NOT NULL
  AND  LTRIM(RTRIM(ic.temp_fileno)) <> ''
  AND  dr.fileno <> ic.temp_fileno;

/* Apply the sync. */
UPDATE dr
SET    dr.fileno     = ic.temp_fileno,
       dr.updated_at = GETDATE()
FROM   deed_registrations dr
JOIN   instrument_capture  ic ON ic.id = dr.instrument_capture_id
WHERE  dr.fileno IS NOT NULL
  AND  LTRIM(RTRIM(dr.fileno)) <> ''
  AND  UPPER(LTRIM(RTRIM(dr.fileno))) LIKE 'TEMP-%'
  AND  ic.temp_fileno  IS NOT NULL
  AND  LTRIM(RTRIM(ic.temp_fileno)) <> ''
  AND  dr.fileno <> ic.temp_fileno;


/* ──────────────────────────────────────────────────────────────────────────
   VERIFICATION
────────────────────────────────────────────────────────────────────────── */
PRINT '=== VERIFY: rows changed (dr_id, ic_id, old -> new) ===';
SELECT * FROM @changed ORDER BY dr_id;

PRINT '=== VERIFY: remaining DR duplicate fileno groups (should be 0 rows) ===';
SELECT fileno, COUNT(*) AS row_count
FROM   deed_registrations
WHERE  fileno IS NOT NULL
  AND  LTRIM(RTRIM(fileno)) <> ''
  AND  UPPER(LTRIM(RTRIM(fileno))) LIKE 'TEMP-%'
GROUP  BY fileno
HAVING COUNT(*) > 1;

PRINT '=== VERIFY: remaining DR rows out of sync with IC (should be 0 rows) ===';
SELECT dr.id, dr.fileno, dr.instrument_capture_id, ic.temp_fileno AS ic_temp_fileno
FROM   deed_registrations dr
JOIN   instrument_capture  ic ON ic.id = dr.instrument_capture_id
WHERE  dr.fileno IS NOT NULL
  AND  LTRIM(RTRIM(dr.fileno)) <> ''
  AND  UPPER(LTRIM(RTRIM(dr.fileno))) LIKE 'TEMP-%'
  AND  ic.temp_fileno  IS NOT NULL
  AND  LTRIM(RTRIM(ic.temp_fileno)) <> ''
  AND  dr.fileno <> ic.temp_fileno;

PRINT '=== VERIFY: DR rows whose IC has empty/null temp_fileno (manual review) ===';
SELECT dr.id, dr.fileno, dr.instrument_capture_id,
       ic.id AS ic_id, ic.temp_fileno AS ic_temp_fileno, ic.prop_id, ic.mlsFNo
FROM   deed_registrations dr
LEFT   JOIN instrument_capture ic ON ic.id = dr.instrument_capture_id
WHERE  dr.fileno IS NOT NULL
  AND  LTRIM(RTRIM(dr.fileno)) <> ''
  AND  UPPER(LTRIM(RTRIM(dr.fileno))) LIKE 'TEMP-%'
  AND  (ic.id IS NULL
        OR ic.temp_fileno IS NULL
        OR LTRIM(RTRIM(ic.temp_fileno)) = '');

/* If everything looks right: */
-- COMMIT;
/* If anything looks wrong: */
-- ROLLBACK;

end_of_script:
PRINT 'Done.';
