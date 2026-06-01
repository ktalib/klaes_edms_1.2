-- ============================================================================
-- probe_temp46133_state.sql
-- ----------------------------------------------------------------------------
-- Read-only diagnostic for the OP/TOT mismatch caused by the now-disabled
-- PraRecordController OP dedup guard. Surfaces:
--   * PRA rows attached to prop_id 85191 (the hijacked prop_id)
--   * PRA rows with file_no RES-2026-2127 or temp_fileno TEMP-46133
--   * IC rows with temp_fileno TEMP-46133 or op_serial_number 659
--   * Any IC/PRA row with party_2 like 'FANNY KOHLER%' (the user's lost data)
--   * deed_registrations rows touching the same identifiers
--
-- NO writes. Safe to run repeatedly. Use the output to decide whether to
-- (a) delete the TOT + recapture, or (b) patch the hijacked IC row in place.
-- ============================================================================

SET NOCOUNT ON;

PRINT '=== 1) PRA rows for prop_id 85191 (which OP/TOT live here today) ===';
SELECT
    id,
    prop_id,
    parent_prop_id,
    source_op_id,
    source_op_table,
    instrument_type,
    transaction_type,
    op_serial_number,
    fileno,
    temp_fileno,
    mlsFNo,
    party_1,
    party_2,
    plot_no,
    tp_no,
    location,
    created_at,
    updated_at
FROM pra
WHERE prop_id = 85191
ORDER BY id;
GO

PRINT '=== 2) PRA rows by file numbers (TEMP-46133 / RES-2026-2127) ===';
SELECT
    id,
    prop_id,
    parent_prop_id,
    source_op_id,
    instrument_type,
    op_serial_number,
    fileno,
    temp_fileno,
    mlsFNo,
    party_1,
    party_2,
    created_at
FROM pra
WHERE UPPER(LTRIM(RTRIM(ISNULL(fileno, ''))))      IN ('TEMP-46133', 'RES-2026-2127')
   OR UPPER(LTRIM(RTRIM(ISNULL(temp_fileno, '')))) IN ('TEMP-46133', 'RES-2026-2127')
   OR UPPER(LTRIM(RTRIM(ISNULL(mlsFNo, ''))))      IN ('TEMP-46133', 'RES-2026-2127')
ORDER BY id;
GO

PRINT '=== 3) IC rows that may have been hijacked (temp/serial match) ===';
SELECT
    id,
    instrument_type,
    op_serial_number,
    prop_id,
    temp_fileno,
    mlsFNo,
    kangisFileNo,
    NewKANGISFileno,
    party_1,
    party_2,
    plot_no,
    tp_no,
    is_deleted,
    created_at,
    updated_at
FROM instrument_capture
WHERE instrument_type = 'Occupancy Permit (OP)'
  AND (
        UPPER(LTRIM(RTRIM(ISNULL(temp_fileno, '')))) = 'TEMP-46133'
     OR UPPER(LTRIM(RTRIM(ISNULL(op_serial_number, '')))) = '659'
  )
ORDER BY id DESC;
GO

PRINT '=== 4) Any row containing FANNY KOHLER (was your OP saved anywhere?) ===';
SELECT
    'pra'                       AS source_table,
    id,
    prop_id,
    instrument_type,
    op_serial_number,
    temp_fileno,
    fileno,
    party_1,
    party_2,
    plot_no,
    created_at
FROM pra
WHERE UPPER(ISNULL(party_2, '')) LIKE 'FANNY KOHLER%'
   OR UPPER(ISNULL(party_1, '')) LIKE 'FANNY KOHLER%'

UNION ALL

SELECT
    'instrument_capture'        AS source_table,
    id,
    prop_id,
    instrument_type,
    op_serial_number,
    temp_fileno,
    NULL                        AS fileno,
    party_1,
    party_2,
    plot_no,
    created_at
FROM instrument_capture
WHERE (
        UPPER(ISNULL(party_2, '')) LIKE 'FANNY KOHLER%'
     OR UPPER(ISNULL(party_1, '')) LIKE 'FANNY KOHLER%'
      )
  AND (is_deleted IS NULL OR is_deleted = 0)
ORDER BY source_table, id;
GO

PRINT '=== 5) deed_registrations rows touching the same identifiers ===';
SELECT
    id,
    instrument_type,
    fileno,
    prop_id,
    party_1,
    party_2,
    created_at
FROM deed_registrations
WHERE UPPER(LTRIM(RTRIM(ISNULL(fileno, '')))) IN ('TEMP-46133', 'RES-2026-2127')
   OR UPPER(ISNULL(party_2, '')) LIKE 'FANNY KOHLER%'
ORDER BY id DESC;
GO

PRINT '=== 6) Lineage view: every PRA row pointing at this prop_id as parent ===';
SELECT
    id                   AS tot_pra_id,
    prop_id              AS tot_prop_id,
    parent_prop_id,
    source_op_id,
    source_op_table,
    instrument_type,
    fileno,
    temp_fileno,
    party_2,
    created_at
FROM pra
WHERE TRY_CAST(parent_prop_id AS BIGINT) = 85191
   OR TRY_CAST(source_op_id   AS BIGINT) IN (
        SELECT id FROM pra WHERE prop_id = 85191
   )
ORDER BY id;
GO
