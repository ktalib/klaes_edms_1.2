-- ============================================================================
-- INSPECT: Temporary File Number vs Main File Prop ID Alignment
-- Temporary file:  CON-RES-1983-104(T)
-- Expected match:  CON-RES-1983-104
-- Run each section independently. All sections are READ-ONLY (no writes).
-- ============================================================================

-- ============================================================================
-- SECTION 1: OVERALL SUMMARY
-- How many (T) files exist, how many matched a main file, how many mismatched
-- ============================================================================
; WITH FilePropIds AS (
    SELECT UPPER(LTRIM(RTRIM(file_number))) AS fileno, prop_id, 'file_indexings'      AS source_table, id AS record_id FROM file_indexings      WHERE file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo)))      AS fileno, prop_id, 'file_history_staging' AS source_table, id AS record_id FROM file_history_staging WHERE mlsFNo      IS NOT NULL AND LTRIM(RTRIM(mlsFNo))      <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo)))      AS fileno, prop_id, 'pra'                  AS source_table, id AS record_id FROM pra                  WHERE mlsFNo      IS NOT NULL AND LTRIM(RTRIM(mlsFNo))      <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(fileno)))      AS fileno, prop_id, 'deed_registrations'   AS source_table, id AS record_id FROM deed_registrations   WHERE fileno      IS NOT NULL AND LTRIM(RTRIM(fileno))      <> ''
),
TempFiles AS (
    SELECT
        fileno                                    AS temp_fileno,
        RTRIM(REPLACE(fileno, '(T)', ''))         AS base_fileno,
        prop_id                                   AS temp_prop_id,
        source_table                              AS temp_source,
        record_id                                 AS temp_id
    FROM FilePropIds
    WHERE fileno LIKE '%(T)'
),
MainFiles AS (
    SELECT fileno AS main_fileno, prop_id AS main_prop_id, source_table AS main_source, record_id AS main_id
    FROM FilePropIds
    WHERE fileno NOT LIKE '%(T)'
),
Matched AS (
    SELECT
        t.temp_fileno,
        t.temp_prop_id,
        t.temp_source,
        m.main_fileno,
        m.main_prop_id,
        m.main_source,
        CASE
            WHEN t.temp_prop_id = m.main_prop_id                             THEN 'OK'
            WHEN t.temp_prop_id IS NULL AND m.main_prop_id IS NOT NULL       THEN 'TEMP_NULL'
            WHEN t.temp_prop_id IS NOT NULL AND m.main_prop_id IS NULL       THEN 'MAIN_NULL'
            ELSE                                                                  'MISMATCH'
        END AS match_status
    FROM TempFiles t
    JOIN MainFiles m ON t.base_fileno = m.main_fileno
)
SELECT
    'SECTION 1 — OVERALL SUMMARY'                         AS [Section],
    COUNT(DISTINCT temp_fileno)                           AS [Total (T) Rows Matched to a Main File],
    SUM(CASE WHEN match_status = 'OK'        THEN 1 ELSE 0 END) AS [Prop ID Matches (OK)],
    SUM(CASE WHEN match_status = 'MISMATCH'  THEN 1 ELSE 0 END) AS [Prop ID Mismatches],
    SUM(CASE WHEN match_status = 'TEMP_NULL' THEN 1 ELSE 0 END) AS [(T) Prop ID is NULL, Main Has Value],
    SUM(CASE WHEN match_status = 'MAIN_NULL' THEN 1 ELSE 0 END) AS [Main Prop ID is NULL, (T) Has Value]
FROM Matched;
GO


-- ============================================================================
-- SECTION 2: ORPHANED (T) FILES — no corresponding main file found anywhere
-- ============================================================================
; WITH FilePropIds AS (
    SELECT UPPER(LTRIM(RTRIM(file_number))) AS fileno, prop_id, 'file_indexings'      AS source_table, id AS record_id FROM file_indexings      WHERE file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo)))      AS fileno, prop_id, 'file_history_staging' AS source_table, id AS record_id FROM file_history_staging WHERE mlsFNo      IS NOT NULL AND LTRIM(RTRIM(mlsFNo))      <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo)))      AS fileno, prop_id, 'pra'                  AS source_table, id AS record_id FROM pra                  WHERE mlsFNo      IS NOT NULL AND LTRIM(RTRIM(mlsFNo))      <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(fileno)))      AS fileno, prop_id, 'deed_registrations'   AS source_table, id AS record_id FROM deed_registrations   WHERE fileno      IS NOT NULL AND LTRIM(RTRIM(fileno))      <> ''
),
TempFiles AS (
    SELECT fileno AS temp_fileno, RTRIM(REPLACE(fileno, '(T)', '')) AS base_fileno, prop_id AS temp_prop_id, source_table AS temp_source, record_id AS temp_id
    FROM FilePropIds WHERE fileno LIKE '%(T)'
),
MainFilenos AS (
    SELECT DISTINCT fileno AS main_fileno FROM FilePropIds WHERE fileno NOT LIKE '%(T)'
)
SELECT DISTINCT
    'SECTION 2 — ORPHANED (T) — No Main File Found' AS [Section],
    t.temp_fileno   AS [(T) File Number],
    t.base_fileno   AS [Expected Main File],
    t.temp_prop_id  AS [(T) Prop ID],
    t.temp_source   AS [(T) Source Table],
    t.temp_id       AS [(T) Record ID]
FROM TempFiles t
LEFT JOIN MainFilenos m ON t.base_fileno = m.main_fileno
WHERE m.main_fileno IS NULL
ORDER BY t.temp_fileno, t.temp_source;
GO


-- ============================================================================
-- SECTION 3: ALL MISMATCHES — across every table (no deed_registrations filter)
-- Shows every (T) row whose prop_id differs from its main file counterpart
-- ============================================================================
; WITH FilePropIds AS (
    SELECT UPPER(LTRIM(RTRIM(file_number))) AS fileno, prop_id, 'file_indexings'      AS source_table, id AS record_id FROM file_indexings      WHERE file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo)))      AS fileno, prop_id, 'file_history_staging' AS source_table, id AS record_id FROM file_history_staging WHERE mlsFNo      IS NOT NULL AND LTRIM(RTRIM(mlsFNo))      <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo)))      AS fileno, prop_id, 'pra'                  AS source_table, id AS record_id FROM pra                  WHERE mlsFNo      IS NOT NULL AND LTRIM(RTRIM(mlsFNo))      <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(fileno)))      AS fileno, prop_id, 'deed_registrations'   AS source_table, id AS record_id FROM deed_registrations   WHERE fileno      IS NOT NULL AND LTRIM(RTRIM(fileno))      <> ''
),
TempFiles AS (
    SELECT fileno AS temp_fileno, RTRIM(REPLACE(fileno, '(T)', '')) AS base_fileno, prop_id AS temp_prop_id, source_table AS temp_source, record_id AS temp_id
    FROM FilePropIds WHERE fileno LIKE '%(T)'
),
MainFiles AS (
    SELECT fileno AS main_fileno, prop_id AS main_prop_id, source_table AS main_source, record_id AS main_id
    FROM FilePropIds WHERE fileno NOT LIKE '%(T)'
)
SELECT DISTINCT
    'SECTION 3 — ALL MISMATCHES'  AS [Section],
    t.temp_fileno                 AS [(T) File Number],
    t.temp_prop_id                AS [(T) Prop ID],
    t.temp_source                 AS [(T) Source Table],
    t.temp_id                     AS [(T) Record ID],
    m.main_fileno                 AS [Main File Number],
    m.main_prop_id                AS [Main Prop ID],
    m.main_source                 AS [Main Source Table],
    m.main_id                     AS [Main Record ID],
    CASE
        WHEN t.temp_prop_id IS NULL AND m.main_prop_id IS NOT NULL THEN 'TEMP_NULL  → needs ' + CAST(m.main_prop_id AS VARCHAR(20))
        WHEN t.temp_prop_id IS NOT NULL AND m.main_prop_id IS NULL THEN 'MAIN_NULL  (no prop_id to copy from)'
        ELSE                                                             'MISMATCH   → should be ' + CAST(m.main_prop_id AS VARCHAR(20))
    END AS [Issue / Suggested Fix]
FROM TempFiles t
JOIN MainFiles m ON t.base_fileno = m.main_fileno
WHERE (
        t.temp_prop_id <> m.main_prop_id
        OR (t.temp_prop_id IS NULL     AND m.main_prop_id IS NOT NULL)
        OR (t.temp_prop_id IS NOT NULL AND m.main_prop_id IS NULL)
)
ORDER BY t.temp_fileno, t.temp_source, m.main_source;
GO


-- ============================================================================
-- SECTION 4: MISMATCH BREAKDOWN BY SOURCE TABLE PAIR
-- Shows which table combinations are causing the most mismatches
-- ============================================================================
; WITH FilePropIds AS (
    SELECT UPPER(LTRIM(RTRIM(file_number))) AS fileno, prop_id, 'file_indexings'      AS source_table FROM file_indexings      WHERE file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo)))      AS fileno, prop_id, 'file_history_staging' AS source_table FROM file_history_staging WHERE mlsFNo      IS NOT NULL AND LTRIM(RTRIM(mlsFNo))      <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo)))      AS fileno, prop_id, 'pra'                  AS source_table FROM pra                  WHERE mlsFNo      IS NOT NULL AND LTRIM(RTRIM(mlsFNo))      <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(fileno)))      AS fileno, prop_id, 'deed_registrations'   AS source_table FROM deed_registrations   WHERE fileno      IS NOT NULL AND LTRIM(RTRIM(fileno))      <> ''
),
TempFiles AS (
    SELECT fileno AS temp_fileno, RTRIM(REPLACE(fileno, '(T)', '')) AS base_fileno, prop_id AS temp_prop_id, source_table AS temp_source
    FROM FilePropIds WHERE fileno LIKE '%(T)'
),
MainFiles AS (
    SELECT fileno AS main_fileno, prop_id AS main_prop_id, source_table AS main_source
    FROM FilePropIds WHERE fileno NOT LIKE '%(T)'
)
SELECT
    'SECTION 4 — BREAKDOWN BY TABLE PAIR'               AS [Section],
    t.temp_source                                        AS [(T) Table],
    m.main_source                                        AS [Main Table],
    COUNT(DISTINCT t.temp_fileno)                        AS [Mismatched (T) File Count],
    SUM(CASE WHEN t.temp_prop_id IS NULL AND m.main_prop_id IS NOT NULL THEN 1 ELSE 0 END) AS [(T) Null Count],
    SUM(CASE WHEN t.temp_prop_id IS NOT NULL AND m.main_prop_id IS NOT NULL
                  AND t.temp_prop_id <> m.main_prop_id  THEN 1 ELSE 0 END)                AS [Wrong Value Count]
FROM TempFiles t
JOIN MainFiles m ON t.base_fileno = m.main_fileno
WHERE (
        t.temp_prop_id <> m.main_prop_id
        OR (t.temp_prop_id IS NULL     AND m.main_prop_id IS NOT NULL)
        OR (t.temp_prop_id IS NOT NULL AND m.main_prop_id IS NULL)
)
GROUP BY t.temp_source, m.main_source
ORDER BY [Mismatched (T) File Count] DESC;
GO


-- ============================================================================
-- SECTION 5: DEED REGISTRATIONS FOCUS
-- Matches the filter used in _fix_temp_file_prop_ids.sql so you can verify
-- exactly what the fix script will touch before running it
-- ============================================================================
; WITH FilePropIds AS (
    SELECT UPPER(LTRIM(RTRIM(file_number))) AS fileno, prop_id, 'file_indexings'      AS source_table, id AS record_id FROM file_indexings      WHERE file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo)))      AS fileno, prop_id, 'file_history_staging' AS source_table, id AS record_id FROM file_history_staging WHERE mlsFNo      IS NOT NULL AND LTRIM(RTRIM(mlsFNo))      <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(mlsFNo)))      AS fileno, prop_id, 'pra'                  AS source_table, id AS record_id FROM pra                  WHERE mlsFNo      IS NOT NULL AND LTRIM(RTRIM(mlsFNo))      <> ''
    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(fileno)))      AS fileno, prop_id, 'deed_registrations'   AS source_table, id AS record_id FROM deed_registrations   WHERE fileno      IS NOT NULL AND LTRIM(RTRIM(fileno))      <> ''
),
TempFiles AS (
    SELECT fileno AS temp_fileno, RTRIM(REPLACE(fileno, '(T)', '')) AS base_fileno, prop_id AS temp_prop_id, source_table AS temp_source, record_id AS temp_id
    FROM FilePropIds WHERE fileno LIKE '%(T)'
),
MainFiles AS (
    SELECT fileno AS main_fileno, prop_id AS main_prop_id, source_table AS main_source, record_id AS main_id
    FROM FilePropIds WHERE fileno NOT LIKE '%(T)'
)
SELECT DISTINCT
    'SECTION 5 — DEED_REGISTRATIONS FOCUS'  AS [Section],
    t.temp_fileno                            AS [(T) File Number],
    t.temp_prop_id                           AS [(T) Current Prop ID],
    t.temp_source                            AS [(T) Source Table],
    t.temp_id                                AS [(T) Record ID],
    m.main_fileno                            AS [Main File Number],
    m.main_prop_id                           AS [Main Prop ID],
    m.main_source                            AS [Main Source Table],
    'UPDATE deed_registrations SET prop_id = '
        + CAST(m.main_prop_id AS VARCHAR(20))
        + ' WHERE id = '
        + CAST(t.temp_id AS VARCHAR(20))
        + ';'                                AS [Suggested UPDATE Statement]
FROM TempFiles t
JOIN MainFiles m ON t.base_fileno = m.main_fileno
WHERE (
        t.temp_prop_id <> m.main_prop_id
        OR (t.temp_prop_id IS NULL     AND m.main_prop_id IS NOT NULL)
        OR (t.temp_prop_id IS NOT NULL AND m.main_prop_id IS NULL)
)
  AND (t.temp_source = 'deed_registrations' OR m.main_source = 'deed_registrations')
ORDER BY t.temp_fileno, t.temp_source;
GO
