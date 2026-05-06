-- ============================================================
-- PHASE A: IC prop_id reset + PropID_Master backfill ONLY
-- Run this as a standalone script (no dependency on pra inserts)
-- ============================================================

BEGIN TRANSACTION;

-- ============================================================
-- STEP 1: Get the current max prop_id across all tables
-- ============================================================
DECLARE @MaxPropId INT;
SELECT @MaxPropId = MAX(v) FROM (
    SELECT MAX(TRY_CAST(prop_id AS INT)) AS v FROM dbo.file_history_staging
    UNION ALL
    SELECT MAX(TRY_CAST(prop_id AS INT)) AS v FROM dbo.pra
    UNION ALL
    SELECT MAX(TRY_CAST(prop_id AS INT)) AS v FROM dbo.pic
    UNION ALL
    SELECT MAX(TRY_CAST(prop_id AS INT)) AS v FROM dbo.CofO_staging
) x;

PRINT 'Current max prop_id: ' + CAST(@MaxPropId AS VARCHAR);

-- ============================================================
-- STEP 2: Assign next prop_ids to the 18 wrong IC rows
-- ============================================================
-- Each IC row gets MaxPropId + N (one per row)

UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 1  WHERE id = 1250;  -- prop 57464 HAFIZU AHMAD
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 2  WHERE id = 1831;  -- prop 58044 A209 AND A210 STRUCTURE
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 3  WHERE id = 2147;  -- prop 58359 ALH RABIU MUHAMMAD
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 4  WHERE id = 2244;  -- prop 58456 DR AUWAL
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 5  WHERE id = 2250;  -- prop 58462 DR AUWAL
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 6  WHERE id = 2808;  -- prop 62597 SAADET ALI
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 7  WHERE id = 3070;  -- prop 70292 ATIKU AUWALU
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 8  WHERE id = 3079;  -- prop 70350 MUHAMMAD ALASAN
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 9  WHERE id = 3827;  -- prop 78928 ABDULQAHIR JIBRIN
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 10 WHERE id = 3931;  -- prop 79760 ABDURRAHMAN SALISU
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 11 WHERE id = 4058;  -- prop 81047 ALHAJI YAWALE MAIKIFI
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 12 WHERE id = 4131;  -- prop 82028 ISMAIL LAWAN
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 13 WHERE id = 4284;  -- prop 83100 MUAZZAM ADO IBRAHIM
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 14 WHERE id = 4362;  -- prop 83739 A245 & A240 STRUCTURE
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 15 WHERE id = 4522;  -- prop 85527 AMARYAWA
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 16 WHERE id = 4841;  -- prop 87769 TSAHARE YAHYA
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 17 WHERE id = 4929;  -- prop 88146 ADAMU MUHAMMAD S RUGA (correct match, still reset)

-- ============================================================
-- STEP 3: INSERT new correct OP rows into pra for each prop_id
-- (mlsFNo = NULL, new temp_fileno from sequence, Grantor = KANO STATE GOVT,
--  Grantee = ToT Grantor = correct OP holder)
-- ============================================================

-- Helper: insert one OP row and get new temp_fileno for each
DECLARE @tempId INT;

-- prop 57464 | ToT Grantor = RABIU MUAZU KWA | op_serial=494 | COM-2026-142
INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at) VALUES (NULL, 1, GETDATE(), GETDATE());
SET @tempId = SCOPE_IDENTITY();
INSERT INTO dbo.pra (prop_id, mlsFNo, temp_fileno, instrument_type, op_type, op_serial_number, Grantor, Grantee, system_source, created_at, updated_at)
SELECT 57464, NULL, 'TEMP-' + RIGHT('00000' + CAST(@tempId AS VARCHAR), 5), 'Occupancy Permit (OP)', op_type, op_serial_number, 'KANO STATE GOVERNMENT', Grantor, 'OSSOPCHANGEOFNAME', GETDATE(), GETDATE()
FROM dbo.pra WHERE prop_id = 57464 AND UPPER(ISNULL(instrument_type,'')) LIKE '%TRANSFER OF TITLE%' AND system_source = 'OSSOPCHANGEOFNAME';

-- prop 58044 | ToT Grantor = SIRAJO MOHAMMED | op_serial=41 | COM-2026-161
INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at) VALUES (NULL, 1, GETDATE(), GETDATE());
SET @tempId = SCOPE_IDENTITY();
INSERT INTO dbo.pra (prop_id, mlsFNo, temp_fileno, instrument_type, op_type, op_serial_number, Grantor, Grantee, system_source, created_at, updated_at)
SELECT 58044, NULL, 'TEMP-' + RIGHT('00000' + CAST(@tempId AS VARCHAR), 5), 'Occupancy Permit (OP)', op_type, op_serial_number, 'KANO STATE GOVERNMENT', Grantor, 'OSSOPCHANGEOFNAME', GETDATE(), GETDATE()
FROM dbo.pra WHERE prop_id = 58044 AND UPPER(ISNULL(instrument_type,'')) LIKE '%TRANSFER OF TITLE%' AND system_source = 'OSSOPCHANGEOFNAME';

-- prop 58359 | ToT Grantor = IMAM ZUBAIRU | op_serial=237 | COM-2026-151
INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at) VALUES (NULL, 1, GETDATE(), GETDATE());
SET @tempId = SCOPE_IDENTITY();
INSERT INTO dbo.pra (prop_id, mlsFNo, temp_fileno, instrument_type, op_type, op_serial_number, Grantor, Grantee, system_source, created_at, updated_at)
SELECT 58359, NULL, 'TEMP-' + RIGHT('00000' + CAST(@tempId AS VARCHAR), 5), 'Occupancy Permit (OP)', op_type, op_serial_number, 'KANO STATE GOVERNMENT', Grantor, 'OSSOPCHANGEOFNAME', GETDATE(), GETDATE()
FROM dbo.pra WHERE prop_id = 58359 AND UPPER(ISNULL(instrument_type,'')) LIKE '%TRANSFER OF TITLE%' AND system_source = 'OSSOPCHANGEOFNAME';

-- prop 58456 | ToT Grantor = TALLE MAI SHAYI | op_serial=152 | COM-2026-155
INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at) VALUES (NULL, 1, GETDATE(), GETDATE());
SET @tempId = SCOPE_IDENTITY();
INSERT INTO dbo.pra (prop_id, mlsFNo, temp_fileno, instrument_type, op_type, op_serial_number, Grantor, Grantee, system_source, created_at, updated_at)
SELECT 58456, NULL, 'TEMP-' + RIGHT('00000' + CAST(@tempId AS VARCHAR), 5), 'Occupancy Permit (OP)', op_type, op_serial_number, 'KANO STATE GOVERNMENT', Grantor, 'OSSOPCHANGEOFNAME', GETDATE(), GETDATE()
FROM dbo.pra WHERE prop_id = 58456 AND UPPER(ISNULL(instrument_type,'')) LIKE '%TRANSFER OF TITLE%' AND system_source = 'OSSOPCHANGEOFNAME';

-- prop 58462 | ToT Grantor = ADO MUHAMMAD INUWA | op_serial=153 | COM-2026-138
INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at) VALUES (NULL, 1, GETDATE(), GETDATE());
SET @tempId = SCOPE_IDENTITY();
INSERT INTO dbo.pra (prop_id, mlsFNo, temp_fileno, instrument_type, op_type, op_serial_number, Grantor, Grantee, system_source, created_at, updated_at)
SELECT 58462, NULL, 'TEMP-' + RIGHT('00000' + CAST(@tempId AS VARCHAR), 5), 'Occupancy Permit (OP)', op_type, op_serial_number, 'KANO STATE GOVERNMENT', Grantor, 'OSSOPCHANGEOFNAME', GETDATE(), GETDATE()
FROM dbo.pra WHERE prop_id = 58462 AND UPPER(ISNULL(instrument_type,'')) LIKE '%TRANSFER OF TITLE%' AND system_source = 'OSSOPCHANGEOFNAME';

-- prop 62597 | ToT Grantor = BISBAHU ABBAS | op_serial=426 | COM-2026-140
INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at) VALUES (NULL, 1, GETDATE(), GETDATE());
SET @tempId = SCOPE_IDENTITY();
INSERT INTO dbo.pra (prop_id, mlsFNo, temp_fileno, instrument_type, op_type, op_serial_number, Grantor, Grantee, system_source, created_at, updated_at)
SELECT 62597, NULL, 'TEMP-' + RIGHT('00000' + CAST(@tempId AS VARCHAR), 5), 'Occupancy Permit (OP)', op_type, op_serial_number, 'KANO STATE GOVERNMENT', Grantor, 'OSSOPCHANGEOFNAME', GETDATE(), GETDATE()
FROM dbo.pra WHERE prop_id = 62597 AND UPPER(ISNULL(instrument_type,'')) LIKE '%TRANSFER OF TITLE%' AND system_source = 'OSSOPCHANGEOFNAME';

-- prop 70292 | ToT Grantor = SANI ISMAILA | op_serial=488 | COM-2026-143
INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at) VALUES (NULL, 1, GETDATE(), GETDATE());
SET @tempId = SCOPE_IDENTITY();
INSERT INTO dbo.pra (prop_id, mlsFNo, temp_fileno, instrument_type, op_type, op_serial_number, Grantor, Grantee, system_source, created_at, updated_at)
SELECT 70292, NULL, 'TEMP-' + RIGHT('00000' + CAST(@tempId AS VARCHAR), 5), 'Occupancy Permit (OP)', op_type, op_serial_number, 'KANO STATE GOVERNMENT', Grantor, 'OSSOPCHANGEOFNAME', GETDATE(), GETDATE()
FROM dbo.pra WHERE prop_id = 70292 AND UPPER(ISNULL(instrument_type,'')) LIKE '%TRANSFER OF TITLE%' AND system_source = 'OSSOPCHANGEOFNAME';

-- prop 70350 | ToT Grantor = ALHAJI BASHARI YOLA | op_serial=495 | COM-2026-141
INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at) VALUES (NULL, 1, GETDATE(), GETDATE());
SET @tempId = SCOPE_IDENTITY();
INSERT INTO dbo.pra (prop_id, mlsFNo, temp_fileno, instrument_type, op_type, op_serial_number, Grantor, Grantee, system_source, created_at, updated_at)
SELECT 70350, NULL, 'TEMP-' + RIGHT('00000' + CAST(@tempId AS VARCHAR), 5), 'Occupancy Permit (OP)', op_type, op_serial_number, 'KANO STATE GOVERNMENT', Grantor, 'OSSOPCHANGEOFNAME', GETDATE(), GETDATE()
FROM dbo.pra WHERE prop_id = 70350 AND UPPER(ISNULL(instrument_type,'')) LIKE '%TRANSFER OF TITLE%' AND system_source = 'OSSOPCHANGEOFNAME';

-- prop 78928 | ToT Grantor = JUMMAI YUSUFU | op_serial=218 | COM-2026-147
INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at) VALUES (NULL, 1, GETDATE(), GETDATE());
SET @tempId = SCOPE_IDENTITY();
INSERT INTO dbo.pra (prop_id, mlsFNo, temp_fileno, instrument_type, op_type, op_serial_number, Grantor, Grantee, system_source, created_at, updated_at)
SELECT 78928, NULL, 'TEMP-' + RIGHT('00000' + CAST(@tempId AS VARCHAR), 5), 'Occupancy Permit (OP)', op_type, op_serial_number, 'KANO STATE GOVERNMENT', Grantor, 'OSSOPCHANGEOFNAME', GETDATE(), GETDATE()
FROM dbo.pra WHERE prop_id = 78928 AND UPPER(ISNULL(instrument_type,'')) LIKE '%TRANSFER OF TITLE%' AND system_source = 'OSSOPCHANGEOFNAME';

-- prop 79760 | ToT Grantor = SHEHU MOHAMMAD | op_serial=234 | COM-2026-150
INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at) VALUES (NULL, 1, GETDATE(), GETDATE());
SET @tempId = SCOPE_IDENTITY();
INSERT INTO dbo.pra (prop_id, mlsFNo, temp_fileno, instrument_type, op_type, op_serial_number, Grantor, Grantee, system_source, created_at, updated_at)
SELECT 79760, NULL, 'TEMP-' + RIGHT('00000' + CAST(@tempId AS VARCHAR), 5), 'Occupancy Permit (OP)', op_type, op_serial_number, 'KANO STATE GOVERNMENT', Grantor, 'OSSOPCHANGEOFNAME', GETDATE(), GETDATE()
FROM dbo.pra WHERE prop_id = 79760 AND UPPER(ISNULL(instrument_type,'')) LIKE '%TRANSFER OF TITLE%' AND system_source = 'OSSOPCHANGEOFNAME';

-- prop 81047 | ToT Grantor = ADO MUHAMMAD INUWA | op_serial=154 | COM-2026-139
INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at) VALUES (NULL, 1, GETDATE(), GETDATE());
SET @tempId = SCOPE_IDENTITY();
INSERT INTO dbo.pra (prop_id, mlsFNo, temp_fileno, instrument_type, op_type, op_serial_number, Grantor, Grantee, system_source, created_at, updated_at)
SELECT 81047, NULL, 'TEMP-' + RIGHT('00000' + CAST(@tempId AS VARCHAR), 5), 'Occupancy Permit (OP)', op_type, op_serial_number, 'KANO STATE GOVERNMENT', Grantor, 'OSSOPCHANGEOFNAME', GETDATE(), GETDATE()
FROM dbo.pra WHERE prop_id = 81047 AND UPPER(ISNULL(instrument_type,'')) LIKE '%TRANSFER OF TITLE%' AND system_source = 'OSSOPCHANGEOFNAME';

-- prop 82028 | ToT Grantor = TIJJANI ALIYU | op_serial=432 | COM-2026-154
INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at) VALUES (NULL, 1, GETDATE(), GETDATE());
SET @tempId = SCOPE_IDENTITY();
INSERT INTO dbo.pra (prop_id, mlsFNo, temp_fileno, instrument_type, op_type, op_serial_number, Grantor, Grantee, system_source, created_at, updated_at)
SELECT 82028, NULL, 'TEMP-' + RIGHT('00000' + CAST(@tempId AS VARCHAR), 5), 'Occupancy Permit (OP)', op_type, op_serial_number, 'KANO STATE GOVERNMENT', Grantor, 'OSSOPCHANGEOFNAME', GETDATE(), GETDATE()
FROM dbo.pra WHERE prop_id = 82028 AND UPPER(ISNULL(instrument_type,'')) LIKE '%TRANSFER OF TITLE%' AND system_source = 'OSSOPCHANGEOFNAME';

-- prop 83100 | ToT Grantor = HAMZA ADO | op_serial=163 | COM-2026-160
INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at) VALUES (NULL, 1, GETDATE(), GETDATE());
SET @tempId = SCOPE_IDENTITY();
INSERT INTO dbo.pra (prop_id, mlsFNo, temp_fileno, instrument_type, op_type, op_serial_number, Grantor, Grantee, system_source, created_at, updated_at)
SELECT 83100, NULL, 'TEMP-' + RIGHT('00000' + CAST(@tempId AS VARCHAR), 5), 'Occupancy Permit (OP)', op_type, op_serial_number, 'KANO STATE GOVERNMENT', Grantor, 'OSSOPCHANGEOFNAME', GETDATE(), GETDATE()
FROM dbo.pra WHERE prop_id = 83100 AND UPPER(ISNULL(instrument_type,'')) LIKE '%TRANSFER OF TITLE%' AND system_source = 'OSSOPCHANGEOFNAME';

-- prop 83739 | ToT Grantor = ALI DANLAMI | op_serial=63 | COM-2026-162
INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at) VALUES (NULL, 1, GETDATE(), GETDATE());
SET @tempId = SCOPE_IDENTITY();
INSERT INTO dbo.pra (prop_id, mlsFNo, temp_fileno, instrument_type, op_type, op_serial_number, Grantor, Grantee, system_source, created_at, updated_at)
SELECT 83739, NULL, 'TEMP-' + RIGHT('00000' + CAST(@tempId AS VARCHAR), 5), 'Occupancy Permit (OP)', op_type, op_serial_number, 'KANO STATE GOVERNMENT', Grantor, 'OSSOPCHANGEOFNAME', GETDATE(), GETDATE()
FROM dbo.pra WHERE prop_id = 83739 AND UPPER(ISNULL(instrument_type,'')) LIKE '%TRANSFER OF TITLE%' AND system_source = 'OSSOPCHANGEOFNAME';

-- prop 85527 | ToT Grantor = ABDULKARIM SHUAIBU | op_serial=5 | COM-2026-157
INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at) VALUES (NULL, 1, GETDATE(), GETDATE());
SET @tempId = SCOPE_IDENTITY();
INSERT INTO dbo.pra (prop_id, mlsFNo, temp_fileno, instrument_type, op_type, op_serial_number, Grantor, Grantee, system_source, created_at, updated_at)
SELECT 85527, NULL, 'TEMP-' + RIGHT('00000' + CAST(@tempId AS VARCHAR), 5), 'Occupancy Permit (OP)', op_type, op_serial_number, 'KANO STATE GOVERNMENT', Grantor, 'OSSOPCHANGEOFNAME', GETDATE(), GETDATE()
FROM dbo.pra WHERE prop_id = 85527 AND UPPER(ISNULL(instrument_type,'')) LIKE '%TRANSFER OF TITLE%' AND system_source = 'OSSOPCHANGEOFNAME';

-- prop 87769 | ToT Grantor = ALHAJI YAHAYA | op_serial=157 | COM-2026-148
INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at) VALUES (NULL, 1, GETDATE(), GETDATE());
SET @tempId = SCOPE_IDENTITY();
INSERT INTO dbo.pra (prop_id, mlsFNo, temp_fileno, instrument_type, op_type, op_serial_number, Grantor, Grantee, system_source, created_at, updated_at)
SELECT 87769, NULL, 'TEMP-' + RIGHT('00000' + CAST(@tempId AS VARCHAR), 5), 'Occupancy Permit (OP)', op_type, op_serial_number, 'KANO STATE GOVERNMENT', Grantor, 'OSSOPCHANGEOFNAME', GETDATE(), GETDATE()
FROM dbo.pra WHERE prop_id = 87769 AND UPPER(ISNULL(instrument_type,'')) LIKE '%TRANSFER OF TITLE%' AND system_source = 'OSSOPCHANGEOFNAME';

-- prop 88146 | ToT Grantor = ADAMU MUHAMMAD S RUGA | op_serial=399 | COM-2026-149
INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at) VALUES (NULL, 1, GETDATE(), GETDATE());
SET @tempId = SCOPE_IDENTITY();
INSERT INTO dbo.pra (prop_id, mlsFNo, temp_fileno, instrument_type, op_type, op_serial_number, Grantor, Grantee, system_source, created_at, updated_at)
SELECT 88146, NULL, 'TEMP-' + RIGHT('00000' + CAST(@tempId AS VARCHAR), 5), 'Occupancy Permit (OP)', op_type, op_serial_number, 'KANO STATE GOVERNMENT', Grantor, 'OSSOPCHANGEOFNAME', GETDATE(), GETDATE()
FROM dbo.pra WHERE prop_id = 88146 AND UPPER(ISNULL(instrument_type,'')) LIKE '%TRANSFER OF TITLE%' AND system_source = 'OSSOPCHANGEOFNAME';

-- ============================================================
-- STEP 4: Verify before committing
-- ============================================================
SELECT 'NEW_OP_IN_PRA' AS check_type, id, prop_id, mlsFNo, temp_fileno, instrument_type, Grantor, Grantee, system_source, created_at
FROM dbo.pra
WHERE prop_id IN (57464,58044,58359,58456,58462,62597,70292,70350,78928,79760,81047,82028,83100,83739,85527,87769,88146)
  AND UPPER(ISNULL(instrument_type,'')) LIKE '%OCCUPANCY PERMIT%'
  AND system_source = 'OSSOPCHANGEOFNAME'
ORDER BY prop_id;

SELECT 'RESET_IC_ROWS' AS check_type, id, prop_id, instrument_type, party_1_name, party_2_name
FROM dbo.instrument_capture
WHERE id IN (1250,1831,2147,2244,2250,2808,3070,3079,3827,3931,4058,4131,4284,4362,4522,4841,4929)
ORDER BY id;

-- ROLLBACK TRANSACTION;  -- uncomment to undo
-- COMMIT TRANSACTION;    -- uncomment after verifying both SELECTs look correct