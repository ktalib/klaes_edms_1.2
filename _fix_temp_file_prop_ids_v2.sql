-- ============================================================================
-- KLAES: Fix deed_registrations prop_id for temporary file numbers
-- Based on inspection results from _inspect_temp_file_prop_ids.sql
--
-- Records to fix (6 unique deed_registrations rows):
--   id=26931  CON-RES-1983-104(T)  → prop_id = 17135
--   id=27100  RES-1985-763(T)      → prop_id = 8745
--   id=26526  RES-2000-5260(T)     → prop_id = 69578160
--   id=26514  RES-2002-3531(T)     → prop_id = 69578150
--   id=26519  RES-2002-3532(T)     → prop_id = 69578156
--   id=26272  RES-2003-2063(T)     → prop_id = 11758
--
-- Strategy: deduplicate multiple source-table matches with ROW_NUMBER(),
--   preferring non-NULL prop_ids and the highest (most recent) value.
-- ============================================================================

-- ============================================================================
-- STEP 1 — BEFORE SNAPSHOT  (read-only, run first)
-- ============================================================================
SELECT
    id,
    fileno,
    prop_id         AS [Current prop_id],
    'BEFORE'        AS [State]
FROM deed_registrations
WHERE id IN (26931, 27100, 26526, 26514, 26519, 26272)
ORDER BY id;
GO


-- ============================================================================
-- STEP 2 — DRY RUN  (read-only — shows exactly what will be written)
-- Deduplicates via ROW_NUMBER so each (T) record maps to one prop_id.
-- ============================================================================
; WITH FilePropIds AS (
    SELECT UPPER(LTRIM(RTRIM(file_number))) AS fileno, prop_id, 'file_indexings'      AS src FROM file_indexings      WHERE file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo)))      AS fileno, prop_id, 'file_history_staging' AS src FROM file_history_staging WHERE mlsFNo      IS NOT NULL AND LTRIM(RTRIM(mlsFNo))      <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo)))      AS fileno, prop_id, 'pra'                  AS src FROM pra                  WHERE mlsFNo      IS NOT NULL AND LTRIM(RTRIM(mlsFNo))      <> ''
),
-- One best prop_id per base file number (NULL-safe: NULLs ranked last)
BestMain AS (
    SELECT
        fileno AS main_fileno,
        prop_id AS main_prop_id,
        src     AS main_src,
        ROW_NUMBER() OVER (
            PARTITION BY fileno
            ORDER BY CASE WHEN prop_id IS NULL THEN 1 ELSE 0 END,
                     prop_id DESC
        ) AS rn
    FROM FilePropIds
    WHERE fileno NOT LIKE '%(T)'
),
Targets AS (
    SELECT
        dr.id               AS record_id,
        dr.fileno           AS temp_fileno,
        dr.prop_id          AS current_prop_id,
        b.main_fileno,
        b.main_prop_id      AS new_prop_id,
        b.main_src
    FROM deed_registrations dr
    CROSS APPLY (
        SELECT TOP 1 main_fileno, main_prop_id, main_src
        FROM BestMain
        WHERE main_fileno = RTRIM(REPLACE(UPPER(LTRIM(RTRIM(dr.fileno))), '(T)', ''))
          AND rn = 1
    ) b
    WHERE dr.fileno LIKE '%(T)'
      AND (dr.prop_id IS NULL OR dr.prop_id <> b.main_prop_id)
)
SELECT
    record_id       AS [deed_registrations.id],
    temp_fileno     AS [(T) File Number],
    current_prop_id AS [Current prop_id],
    main_fileno     AS [Matched Main File],
    new_prop_id     AS [New prop_id (will write)],
    main_src        AS [Source of new prop_id]
FROM Targets
ORDER BY record_id;
GO


-- ============================================================================
-- STEP 3 — ACTUAL FIX  (wrapped in a transaction — review dry-run first)
-- Uncomment BEGIN/COMMIT or remove ROLLBACK when ready to commit permanently.
-- ============================================================================
BEGIN TRANSACTION;

; WITH FilePropIds AS (
    SELECT UPPER(LTRIM(RTRIM(file_number))) AS fileno, prop_id FROM file_indexings      WHERE file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo)))      AS fileno, prop_id FROM file_history_staging WHERE mlsFNo      IS NOT NULL AND LTRIM(RTRIM(mlsFNo))      <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo)))      AS fileno, prop_id FROM pra                  WHERE mlsFNo      IS NOT NULL AND LTRIM(RTRIM(mlsFNo))      <> ''
),
BestMain AS (
    SELECT
        fileno AS main_fileno,
        prop_id AS main_prop_id,
        ROW_NUMBER() OVER (
            PARTITION BY fileno
            ORDER BY CASE WHEN prop_id IS NULL THEN 1 ELSE 0 END,
                     prop_id DESC
        ) AS rn
    FROM FilePropIds
    WHERE fileno NOT LIKE '%(T)'
),
ToFix AS (
    SELECT dr.id AS record_id, b.main_prop_id AS new_prop_id
    FROM deed_registrations dr
    CROSS APPLY (
        SELECT TOP 1 main_prop_id
        FROM BestMain
        WHERE main_fileno = RTRIM(REPLACE(UPPER(LTRIM(RTRIM(dr.fileno))), '(T)', ''))
          AND rn = 1
          AND main_prop_id IS NOT NULL
    ) b
    WHERE dr.fileno LIKE '%(T)'
      AND (dr.prop_id IS NULL OR dr.prop_id <> b.main_prop_id)
      AND dr.id IN (26931, 27100, 26526, 26514, 26519, 26272)   -- safety guard
)
UPDATE dr
SET    dr.prop_id = tf.new_prop_id
FROM   deed_registrations dr
JOIN   ToFix tf ON dr.id = tf.record_id;

PRINT 'Rows updated: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

-- Inspect before committing — swap for COMMIT when satisfied
ROLLBACK;
-- COMMIT;
GO


-- ============================================================================
-- STEP 4 — VERIFICATION  (run after COMMIT to confirm all 6 are correct)
-- ============================================================================
SELECT
    dr.id,
    dr.fileno               AS [(T) File Number],
    dr.prop_id              AS [prop_id After Fix],
    COALESCE(fi.prop_id, fh.prop_id, p.prop_id) AS [Expected prop_id],
    CASE
        WHEN dr.prop_id = COALESCE(fi.prop_id, fh.prop_id, p.prop_id) THEN 'OK'
        ELSE 'STILL WRONG'
    END                     AS [Status]
FROM deed_registrations dr
-- Look up the main file's prop_id from the three authoritative tables
LEFT JOIN file_indexings fi
    ON UPPER(LTRIM(RTRIM(fi.file_number))) = RTRIM(REPLACE(UPPER(LTRIM(RTRIM(dr.fileno))), '(T)', ''))
LEFT JOIN file_history_staging fh
    ON UPPER(LTRIM(RTRIM(fh.mlsFNo))) = RTRIM(REPLACE(UPPER(LTRIM(RTRIM(dr.fileno))), '(T)', ''))
LEFT JOIN pra p
    ON UPPER(LTRIM(RTRIM(p.mlsFNo))) = RTRIM(REPLACE(UPPER(LTRIM(RTRIM(dr.fileno))), '(T)', ''))
WHERE dr.id IN (26931, 27100, 26526, 26514, 26519, 26272)
ORDER BY dr.id;
GO
