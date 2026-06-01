-- ============================================================================
-- probe_pra_duplicate_ops.sql
-- ----------------------------------------------------------------------------
-- Read-only diagnostic: surface duplicate Occupancy Permit rows in PRA.
--
-- Definition of "duplicate OP":
--   * instrument_type / transaction_type contains 'OCCUPANCY PERMIT'
--   * source_op_id IS NULL  (excludes Transfer-of-Title rows — those are
--     children of OPs, not duplicates of them)
--   * UPPER(TRIM(op_serial_number)) + UPPER(TRIM(serialNo)) +
--     UPPER(TRIM(pageNo))           + UPPER(TRIM(volumeNo))  +
--     UPPER(TRIM(party_2))            -- key combo
--   * Same key appearing on 2+ distinct PRA rows = duplicate group
--   * NULL/empty values in the key are excluded so we don't conflate
--     "missing data" rows as duplicates of each other
--
-- NO writes. Safe to re-run.
-- Target: SQL Server (sqlsrv connection)
-- ============================================================================

SET NOCOUNT ON;

-- ---------------------------------------------------------------------------
-- 0) Total OP rows in PRA (denominator for the dup rate)
-- ---------------------------------------------------------------------------
PRINT '=== 0) Total OP rows in PRA (excluding TOTs) ===';
SELECT COUNT(*) AS total_op_rows
FROM pra
WHERE source_op_id IS NULL
  AND (
       UPPER(ISNULL(instrument_type, ''))  LIKE '%OCCUPANCY PERMIT%'
    OR UPPER(ISNULL(transaction_type, '')) LIKE '%OCCUPANCY PERMIT%'
  );
GO

-- ---------------------------------------------------------------------------
-- 1) Headline: how many duplicate groups exist, how many extra rows?
--    "extra rows" = total dup rows - dup group count (the rows above the
--    one keep-row per group; what you'd delete if collapsing).
-- ---------------------------------------------------------------------------
PRINT '=== 1) Duplicate-group headline (key = op_serial+serial+page+vol+party_2) ===';

;WITH OpKey AS (
    SELECT
        id,
        prop_id,
        UPPER(LTRIM(RTRIM(CAST(op_serial_number AS NVARCHAR(100))))) AS k_op_serial,
        UPPER(LTRIM(RTRIM(CAST(serialNo          AS NVARCHAR(100))))) AS k_serial,
        UPPER(LTRIM(RTRIM(CAST(pageNo            AS NVARCHAR(100))))) AS k_page,
        UPPER(LTRIM(RTRIM(CAST(volumeNo          AS NVARCHAR(100))))) AS k_vol,
        UPPER(LTRIM(RTRIM(CAST(party_2           AS NVARCHAR(200))))) AS k_party2
    FROM pra
    WHERE source_op_id IS NULL
      AND (
           UPPER(ISNULL(instrument_type, ''))  LIKE '%OCCUPANCY PERMIT%'
        OR UPPER(ISNULL(transaction_type, '')) LIKE '%OCCUPANCY PERMIT%'
      )
), Keyed AS (
    SELECT *
    FROM OpKey
    WHERE k_op_serial <> '' AND k_serial <> '' AND k_page <> '' AND k_vol <> '' AND k_party2 <> ''
), Groups AS (
    SELECT k_op_serial, k_serial, k_page, k_vol, k_party2, COUNT(*) AS row_count
    FROM Keyed
    GROUP BY k_op_serial, k_serial, k_page, k_vol, k_party2
    HAVING COUNT(*) > 1
)
SELECT
    COUNT(*)            AS duplicate_groups,
    SUM(row_count)      AS total_rows_in_dup_groups,
    SUM(row_count) - COUNT(*) AS extra_rows_to_collapse
FROM Groups;
GO

-- ---------------------------------------------------------------------------
-- 2) Top 25 duplicate groups by row count (worst offenders)
-- ---------------------------------------------------------------------------
PRINT '=== 2) Top 25 duplicate groups (most rows per key) ===';

;WITH OpKey AS (
    SELECT
        id,
        UPPER(LTRIM(RTRIM(CAST(op_serial_number AS NVARCHAR(100))))) AS k_op_serial,
        UPPER(LTRIM(RTRIM(CAST(serialNo          AS NVARCHAR(100))))) AS k_serial,
        UPPER(LTRIM(RTRIM(CAST(pageNo            AS NVARCHAR(100))))) AS k_page,
        UPPER(LTRIM(RTRIM(CAST(volumeNo          AS NVARCHAR(100))))) AS k_vol,
        UPPER(LTRIM(RTRIM(CAST(party_2           AS NVARCHAR(200))))) AS k_party2
    FROM pra
    WHERE source_op_id IS NULL
      AND (
           UPPER(ISNULL(instrument_type, ''))  LIKE '%OCCUPANCY PERMIT%'
        OR UPPER(ISNULL(transaction_type, '')) LIKE '%OCCUPANCY PERMIT%'
      )
), Keyed AS (
    SELECT *
    FROM OpKey
    WHERE k_op_serial <> '' AND k_serial <> '' AND k_page <> '' AND k_vol <> '' AND k_party2 <> ''
)
SELECT TOP 25
    k_op_serial AS op_serial_number,
    k_serial    AS serial_no,
    k_page      AS page_no,
    k_vol       AS volume_no,
    k_party2    AS party_2,
    COUNT(*)    AS row_count
FROM Keyed
GROUP BY k_op_serial, k_serial, k_page, k_vol, k_party2
HAVING COUNT(*) > 1
ORDER BY COUNT(*) DESC, k_op_serial;
GO

-- ---------------------------------------------------------------------------
-- 3) Per-row detail for the top 10 dup groups — shows you which actual rows
--    to merge/delete. Includes prop_id so you can see if dup rows already
--    collapsed to one prop_id (no action needed) or have distinct prop_ids
--    (real divergence to resolve).
-- ---------------------------------------------------------------------------
PRINT '=== 3) Detail of top 10 dup groups (per-row breakdown) ===';

;WITH OpKey AS (
    SELECT
        id, prop_id, parent_prop_id, source_op_id, source_op_table,
        instrument_type, transaction_type, fileno, temp_fileno, mlsFNo,
        op_serial_number, serialNo, pageNo, volumeNo, party_2,
        plot_no, tp_no, location, created_at, updated_at,
        UPPER(LTRIM(RTRIM(CAST(op_serial_number AS NVARCHAR(100))))) AS k_op_serial,
        UPPER(LTRIM(RTRIM(CAST(serialNo          AS NVARCHAR(100))))) AS k_serial,
        UPPER(LTRIM(RTRIM(CAST(pageNo            AS NVARCHAR(100))))) AS k_page,
        UPPER(LTRIM(RTRIM(CAST(volumeNo          AS NVARCHAR(100))))) AS k_vol,
        UPPER(LTRIM(RTRIM(CAST(party_2           AS NVARCHAR(200))))) AS k_party2
    FROM pra
    WHERE source_op_id IS NULL
      AND (
           UPPER(ISNULL(instrument_type, ''))  LIKE '%OCCUPANCY PERMIT%'
        OR UPPER(ISNULL(transaction_type, '')) LIKE '%OCCUPANCY PERMIT%'
      )
), Keyed AS (
    SELECT * FROM OpKey
    WHERE k_op_serial <> '' AND k_serial <> '' AND k_page <> '' AND k_vol <> '' AND k_party2 <> ''
), TopDupGroups AS (
    SELECT TOP 10
        k_op_serial, k_serial, k_page, k_vol, k_party2, COUNT(*) AS row_count
    FROM Keyed
    GROUP BY k_op_serial, k_serial, k_page, k_vol, k_party2
    HAVING COUNT(*) > 1
    ORDER BY COUNT(*) DESC
)
SELECT
    k.k_op_serial      AS dup_key_op_serial,
    k.k_party2         AS dup_key_party2,
    k.id               AS pra_id,
    k.prop_id,
    k.fileno,
    k.temp_fileno,
    k.mlsFNo,
    k.plot_no,
    k.created_at,
    k.updated_at
FROM Keyed k
JOIN TopDupGroups g
  ON g.k_op_serial = k.k_op_serial
 AND g.k_serial    = k.k_serial
 AND g.k_page      = k.k_page
 AND g.k_vol       = k.k_vol
 AND g.k_party2    = k.k_party2
ORDER BY k.k_op_serial, k.k_party2, k.id;
GO

-- ---------------------------------------------------------------------------
-- 4) Looser definition: dups by op_serial_number + party_2 only.
--    Useful for catching dups where one row has Reg Particulars and the
--    other doesn't (so the strict 5-field key misses them).
-- ---------------------------------------------------------------------------
PRINT '=== 4) Looser dup count: same op_serial + same party_2 (regardless of reg particulars) ===';

;WITH OpKey AS (
    SELECT
        id,
        UPPER(LTRIM(RTRIM(CAST(op_serial_number AS NVARCHAR(100))))) AS k_op_serial,
        UPPER(LTRIM(RTRIM(CAST(party_2          AS NVARCHAR(200))))) AS k_party2
    FROM pra
    WHERE source_op_id IS NULL
      AND (
           UPPER(ISNULL(instrument_type, ''))  LIKE '%OCCUPANCY PERMIT%'
        OR UPPER(ISNULL(transaction_type, '')) LIKE '%OCCUPANCY PERMIT%'
      )
), Keyed AS (
    SELECT * FROM OpKey
    WHERE k_op_serial <> '' AND k_party2 <> ''
)
SELECT
    COUNT(*)            AS duplicate_groups_loose,
    SUM(row_count)      AS total_rows_in_dup_groups_loose,
    SUM(row_count) - COUNT(*) AS extra_rows_to_collapse_loose
FROM (
    SELECT k_op_serial, k_party2, COUNT(*) AS row_count
    FROM Keyed
    GROUP BY k_op_serial, k_party2
    HAVING COUNT(*) > 1
) d;
GO

-- ---------------------------------------------------------------------------
-- 5) Dups WITHOUT party_2 (the FANNY KOHLER1 hijack pattern — multiple OPs
--    sharing op_serial but with different/missing party_2). Useful for
--    identifying records that got created by the old serial-only dedup
--    guard and never got their party_2 backfilled.
-- ---------------------------------------------------------------------------
PRINT '=== 5) OP rows with missing/empty party_2 ===';
SELECT COUNT(*) AS op_rows_missing_party_2
FROM pra
WHERE source_op_id IS NULL
  AND (
       UPPER(ISNULL(instrument_type, ''))  LIKE '%OCCUPANCY PERMIT%'
    OR UPPER(ISNULL(transaction_type, '')) LIKE '%OCCUPANCY PERMIT%'
  )
  AND (party_2 IS NULL OR LTRIM(RTRIM(CAST(party_2 AS NVARCHAR(200)))) = '');
GO
