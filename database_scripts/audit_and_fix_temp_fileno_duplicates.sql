/* ============================================================================
   AUDIT + FIX duplicate temp_fileno values across instrument_capture and pra
   ----------------------------------------------------------------------------
   Background
   ----------
   * temp_fileno format is 'TEMP-' + 5-digit zero-padded id allocated atomically
     from dbo.temp_fileno_sequence.
   * One prop_id can legitimately have MULTIPLE rows in pra sharing the same
     temp_fileno (e.g. an OP row + a Transfer of Title row for the same
     property). That is NOT a duplicate.
   * A real duplicate is:
       - instrument_capture: same temp_fileno on 2+ ic.id rows
       - pra: same temp_fileno spanning 2+ DIFFERENT prop_ids
       - cross-table: ic.temp_fileno used by a pra row whose prop_id does NOT
         tie back to that ic row (orphan reuse)
   * Fix policy: keep the OLDEST record (lowest id / earliest created_at),
     regenerate a fresh TEMP- value for every newer duplicate via
     temp_fileno_sequence, and (for instrument_capture) clear prop_id so the
     normal allocation flow can rebind it cleanly.

   How to run
   ----------
   Step 1: Run the AUDIT section only and save the results.
   Step 2: Review the row counts. If they look right, run the FIX section
           inside a transaction. Inspect the verification queries at the end.
           If everything is correct: COMMIT.  If not: ROLLBACK.

   This script is idempotent: re-running it after a successful fix will
   report 0 duplicates and do nothing.
============================================================================ */

USE klas;        -- adjust if your production DB name differs
SET NOCOUNT ON;
SET XACT_ABORT ON;


/* ============================================================================
   ████  PART 1 — AUDIT (READ ONLY, safe to run anytime)  ████
============================================================================ */

PRINT '=== AUDIT 1: instrument_capture duplicate temp_fileno (any 2+ rows) ===';
;WITH ic_dups AS (
    SELECT temp_fileno, COUNT(*) AS row_count
    FROM   instrument_capture
    WHERE  temp_fileno IS NOT NULL
      AND  LTRIM(RTRIM(temp_fileno)) <> ''
      AND  UPPER(LTRIM(RTRIM(temp_fileno))) LIKE 'TEMP-%'
    GROUP  BY temp_fileno
    HAVING COUNT(*) > 1
)
SELECT ic.id,
       ic.temp_fileno,
       ic.mlsFNo,
       
       ic.prop_id,
       ic.instrument_type,
       ic.op_serial_number,
       ic.party_1_name,
       ic.party_2_name,
       ic.created_at,
       d.row_count
FROM   instrument_capture ic
JOIN   ic_dups d ON d.temp_fileno = ic.temp_fileno
ORDER  BY ic.temp_fileno, ic.id;


PRINT '=== AUDIT 2: pra duplicate temp_fileno across DIFFERENT prop_ids ===';
;WITH pra_dups AS (
    SELECT temp_fileno,
           COUNT(DISTINCT prop_id) AS distinct_prop_ids,
           COUNT(*)                AS row_count
    FROM   pra
    WHERE  temp_fileno IS NOT NULL
      AND  LTRIM(RTRIM(temp_fileno)) <> ''
      AND  UPPER(LTRIM(RTRIM(temp_fileno))) LIKE 'TEMP-%'
      AND  prop_id IS NOT NULL
      AND  LTRIM(RTRIM(prop_id)) <> ''
    GROUP  BY temp_fileno
    HAVING COUNT(DISTINCT prop_id) > 1   -- only real cross-prop_id duplicates
)
SELECT p.id,
       p.temp_fileno,
       p.prop_id,
       p.mlsFNo,
       p.fileno,
       p.instrument_type,
       p.op_serial_number,
       p.Grantor,
       p.Grantee,
       p.created_at,
       d.distinct_prop_ids,
       d.row_count
FROM   pra p
JOIN   pra_dups d ON d.temp_fileno = p.temp_fileno
ORDER  BY p.temp_fileno, p.prop_id, p.id;


PRINT '=== AUDIT 3: cross-table — ic.temp_fileno used by pra rows whose prop_id mismatches ===';
/* For each ic row with a temp_fileno, list any pra rows that use the same
   temp_fileno but whose prop_id does NOT match the ic.prop_id. These are the
   orphans where a temp_fileno was reused on a different property. */
SELECT ic.id              AS ic_id,
       ic.temp_fileno,
       ic.prop_id         AS ic_prop_id,
       p.id               AS pra_id,
       p.prop_id          AS pra_prop_id,
       ic.instrument_type AS ic_instrument_type,
       p.instrument_type  AS pra_instrument_type,
       ic.created_at      AS ic_created_at,
       p.created_at       AS pra_created_at
FROM   instrument_capture ic
JOIN   pra p ON p.temp_fileno = ic.temp_fileno
WHERE  ic.temp_fileno IS NOT NULL
  AND  LTRIM(RTRIM(ic.temp_fileno)) <> ''
  AND  ic.prop_id IS NOT NULL
  AND  p.prop_id IS NOT NULL
  AND  LTRIM(RTRIM(ic.prop_id)) <> LTRIM(RTRIM(p.prop_id))
ORDER  BY ic.temp_fileno, ic.id, p.id;


PRINT '=== AUDIT 4: counts summary ===';
SELECT 'IC duplicate temp_fileno groups' AS metric,
       COUNT(*) AS value
FROM   (SELECT temp_fileno
        FROM   instrument_capture
        WHERE  temp_fileno IS NOT NULL AND LTRIM(RTRIM(temp_fileno)) <> ''
          AND  UPPER(LTRIM(RTRIM(temp_fileno))) LIKE 'TEMP-%'
        GROUP  BY temp_fileno
        HAVING COUNT(*) > 1) x
UNION ALL
SELECT 'IC duplicate rows to fix (excluding the keep-oldest)',
       (SELECT ISNULL(SUM(cnt),0) - ISNULL(COUNT(*),0)
        FROM   (SELECT COUNT(*) AS cnt
                FROM   instrument_capture
                WHERE  temp_fileno IS NOT NULL AND LTRIM(RTRIM(temp_fileno)) <> ''
                  AND  UPPER(LTRIM(RTRIM(temp_fileno))) LIKE 'TEMP-%'
                GROUP  BY temp_fileno
                HAVING COUNT(*) > 1) y)
UNION ALL
SELECT 'PRA duplicate temp_fileno groups (across distinct prop_ids)',
       COUNT(*)
FROM   (SELECT temp_fileno
        FROM   pra
        WHERE  temp_fileno IS NOT NULL AND LTRIM(RTRIM(temp_fileno)) <> ''
          AND  UPPER(LTRIM(RTRIM(temp_fileno))) LIKE 'TEMP-%'
          AND  prop_id IS NOT NULL AND LTRIM(RTRIM(prop_id)) <> ''
        GROUP  BY temp_fileno
        HAVING COUNT(DISTINCT prop_id) > 1) z
UNION ALL
SELECT 'PRA distinct prop_ids that need a new temp_fileno',
       (SELECT COUNT(*)
        FROM   (SELECT temp_fileno, prop_id
                FROM   pra
                WHERE  temp_fileno IS NOT NULL AND LTRIM(RTRIM(temp_fileno)) <> ''
                  AND  UPPER(LTRIM(RTRIM(temp_fileno))) LIKE 'TEMP-%'
                  AND  prop_id IS NOT NULL AND LTRIM(RTRIM(prop_id)) <> ''
                GROUP  BY temp_fileno, prop_id) g
        WHERE  EXISTS (
                SELECT 1 FROM (SELECT temp_fileno
                               FROM   pra
                               WHERE  temp_fileno IS NOT NULL AND LTRIM(RTRIM(temp_fileno)) <> ''
                                 AND  prop_id IS NOT NULL AND LTRIM(RTRIM(prop_id)) <> ''
                               GROUP  BY temp_fileno
                               HAVING COUNT(DISTINCT prop_id) > 1) d
                WHERE d.temp_fileno = g.temp_fileno));


/* STOP HERE if you only wanted the audit.
   The FIX section below makes changes inside a transaction. */
GOTO end_of_script;


/* ============================================================================
   ████  PART 2 — FIX (TRANSACTIONAL — review then COMMIT or ROLLBACK)  ████
   ----------------------------------------------------------------------------
   Strategy:
     * IC: for every temp_fileno used by 2+ rows, keep the row with the
           lowest id; regenerate temp_fileno for every other row and clear
           its prop_id so the normal flow can rebind cleanly.
     * PRA: for every temp_fileno used by 2+ distinct prop_ids, keep the
           prop_id of the EARLIEST pra.id; for each OTHER prop_id, allocate
           ONE new TEMP-XXXXX from temp_fileno_sequence and apply it to
           ALL pra rows of that prop_id. This preserves the OP/ToT pairing
           inside each prop_id while breaking the cross-property collision.
============================================================================ */

BEGIN TRAN;

/* ──────────────────────────────────────────────────────────────────────────
   FIX A — instrument_capture row-level duplicates
────────────────────────────────────────────────────────────────────────── */
DECLARE @ic_to_fix TABLE (ic_id BIGINT PRIMARY KEY,
                          old_temp VARCHAR(50),
                          new_temp VARCHAR(50) NULL);

INSERT INTO @ic_to_fix (ic_id, old_temp)
SELECT  ic.id, ic.temp_fileno
FROM    instrument_capture ic
JOIN    (
    SELECT temp_fileno
    FROM   instrument_capture
    WHERE  temp_fileno IS NOT NULL
      AND  LTRIM(RTRIM(temp_fileno)) <> ''
      AND  UPPER(LTRIM(RTRIM(temp_fileno))) LIKE 'TEMP-%'
    GROUP  BY temp_fileno
    HAVING COUNT(*) > 1
) d ON d.temp_fileno = ic.temp_fileno
/* keep the oldest row for each duplicated temp_fileno; fix the rest */
WHERE   ic.id NOT IN (
            SELECT MIN(ic2.id)
            FROM   instrument_capture ic2
            WHERE  ic2.temp_fileno = ic.temp_fileno
        );

DECLARE @ic_id BIGINT, @new_id INT, @new_temp VARCHAR(50);
DECLARE ic_cur CURSOR LOCAL FAST_FORWARD FOR
    SELECT ic_id FROM @ic_to_fix ORDER BY ic_id;

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
   FIX B — pra cross-prop_id duplicates
   For each duplicated temp_fileno, keep the prop_id with the earliest pra.id;
   for every OTHER prop_id, allocate one new TEMP-XXXXX and apply it to all
   pra rows of that prop_id (preserving OP/ToT pairing inside the prop_id).
────────────────────────────────────────────────────────────────────────── */
DECLARE @pra_to_fix TABLE (
    temp_fileno  VARCHAR(50),
    prop_id      VARCHAR(50),
    new_temp     VARCHAR(50) NULL,
    PRIMARY KEY (temp_fileno, prop_id)
);

;WITH dup_temps AS (
    SELECT temp_fileno
    FROM   pra
    WHERE  temp_fileno IS NOT NULL
      AND  LTRIM(RTRIM(temp_fileno)) <> ''
      AND  UPPER(LTRIM(RTRIM(temp_fileno))) LIKE 'TEMP-%'
      AND  prop_id IS NOT NULL
      AND  LTRIM(RTRIM(prop_id)) <> ''
    GROUP  BY temp_fileno
    HAVING COUNT(DISTINCT prop_id) > 1
),
keepers AS (
    /* for each duplicated temp_fileno, the prop_id of the earliest pra row */
    SELECT p.temp_fileno,
           p.prop_id
    FROM   pra p
    JOIN   dup_temps d ON d.temp_fileno = p.temp_fileno
    WHERE  p.id = (
        SELECT MIN(p2.id)
        FROM   pra p2
        WHERE  p2.temp_fileno = p.temp_fileno
          AND  p2.prop_id IS NOT NULL
          AND  LTRIM(RTRIM(p2.prop_id)) <> ''
    )
)
INSERT INTO @pra_to_fix (temp_fileno, prop_id)
SELECT DISTINCT p.temp_fileno, p.prop_id
FROM   pra p
JOIN   dup_temps d ON d.temp_fileno = p.temp_fileno
WHERE  NOT EXISTS (
           SELECT 1 FROM keepers k
           WHERE  k.temp_fileno = p.temp_fileno
             AND  k.prop_id     = p.prop_id
       )
  AND  p.prop_id IS NOT NULL
  AND  LTRIM(RTRIM(p.prop_id)) <> '';

DECLARE @ptemp VARCHAR(50), @pprop VARCHAR(50);
DECLARE pra_cur CURSOR LOCAL FAST_FORWARD FOR
    SELECT temp_fileno, prop_id FROM @pra_to_fix ORDER BY temp_fileno, prop_id;

OPEN pra_cur;
FETCH NEXT FROM pra_cur INTO @ptemp, @pprop;
WHILE @@FETCH_STATUS = 0
BEGIN
    INSERT INTO temp_fileno_sequence (created_by, is_used, created_at, updated_at)
    VALUES (NULL, 1, GETDATE(), GETDATE());

    SET @new_id   = SCOPE_IDENTITY();
    SET @new_temp = 'TEMP-' + RIGHT('00000' + CAST(@new_id AS VARCHAR(10)), 5);

    /* update every pra row with this (temp_fileno, prop_id) pair */
    UPDATE pra
    SET    temp_fileno = @new_temp,
           updated_at  = GETDATE()
    WHERE  temp_fileno = @ptemp
      AND  prop_id     = @pprop;

    /* also propagate to any instrument_capture row tied to the SAME prop_id
       that still carries the old temp_fileno (to keep the IC/PRA pairing
       consistent for this prop_id). */
    UPDATE instrument_capture
    SET    temp_fileno = @new_temp,
           updated_at  = GETDATE()
    WHERE  temp_fileno = @ptemp
      AND  prop_id     = @pprop;

    UPDATE @pra_to_fix
    SET    new_temp = @new_temp
    WHERE  temp_fileno = @ptemp AND prop_id = @pprop;

    FETCH NEXT FROM pra_cur INTO @ptemp, @pprop;
END
CLOSE pra_cur;
DEALLOCATE pra_cur;


/* ──────────────────────────────────────────────────────────────────────────
   VERIFICATION
────────────────────────────────────────────────────────────────────────── */
PRINT '=== VERIFY: IC rows changed ===';
SELECT * FROM @ic_to_fix ORDER BY ic_id;

PRINT '=== VERIFY: PRA prop_ids reassigned ===';
SELECT * FROM @pra_to_fix ORDER BY temp_fileno, prop_id;

PRINT '=== VERIFY: remaining IC duplicates (should be 0 rows) ===';
SELECT temp_fileno, COUNT(*) AS row_count
FROM   instrument_capture
WHERE  temp_fileno IS NOT NULL AND LTRIM(RTRIM(temp_fileno)) <> ''
  AND  UPPER(LTRIM(RTRIM(temp_fileno))) LIKE 'TEMP-%'
GROUP  BY temp_fileno
HAVING COUNT(*) > 1;

PRINT '=== VERIFY: remaining PRA cross-prop_id duplicates (should be 0 rows) ===';
SELECT temp_fileno, COUNT(DISTINCT prop_id) AS distinct_prop_ids
FROM   pra
WHERE  temp_fileno IS NOT NULL AND LTRIM(RTRIM(temp_fileno)) <> ''
  AND  UPPER(LTRIM(RTRIM(temp_fileno))) LIKE 'TEMP-%'
  AND  prop_id IS NOT NULL AND LTRIM(RTRIM(prop_id)) <> ''
GROUP  BY temp_fileno
HAVING COUNT(DISTINCT prop_id) > 1;

/* If everything looks right: */
-- COMMIT;
/* If anything looks wrong: */
-- ROLLBACK;

end_of_script:
PRINT 'Done.';
