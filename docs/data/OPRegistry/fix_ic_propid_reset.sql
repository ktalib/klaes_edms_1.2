-- ============================================================
-- IC prop_id reset + PropID_Master backfill
-- For 17 wrong IC OP rows whose prop_id collided with ToT rows
-- Run AFTER backups are confirmed
-- ============================================================

BEGIN TRANSACTION;

-- ============================================================
-- STEP 1: Safe max prop_id across all tables
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
-- STEP 2: Assign new unique prop_ids to the 17 wrong IC rows
-- ============================================================
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 1  WHERE id = 1250;  -- was 57464  HAFIZU AHMAD           TEMP-17983
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 2  WHERE id = 1831;  -- was 58044  A209 AND A210 STRUCTURE TEMP-18115
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 3  WHERE id = 2147;  -- was 58359  ALH RABIU MUHAMMAD      TEMP-18066
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 4  WHERE id = 2244;  -- was 58456  DR AUWAL                TEMP-18100
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 5  WHERE id = 2250;  -- was 58462  DR AUWAL                TEMP-17946
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 6  WHERE id = 2808;  -- was 62597  SAADET ALI              TEMP-17969
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 7  WHERE id = 3070;  -- was 70292  ATIKU AUWALU            TEMP-17991
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 8  WHERE id = 3079;  -- was 70350  MUHAMMAD ALASAN         TEMP-17973
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 9  WHERE id = 3827;  -- was 78928  ABDULQAHIR JIBRIN       TEMP-18002
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 10 WHERE id = 3931;  -- was 79760  ABDURRAHMAN SALISU      TEMP-18062
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 11 WHERE id = 4058;  -- was 81047  ALHAJI YAWALE MAIKIFI   TEMP-17963
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 12 WHERE id = 4131;  -- was 82028  ISMAIL LAWAN            TEMP-18094
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 13 WHERE id = 4284;  -- was 83100  MUAZZAM ADO IBRAHIM     TEMP-18111
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 14 WHERE id = 4362;  -- was 83739  A245 & A240 STRUCTURE   TEMP-18118
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 15 WHERE id = 4522;  -- was 85527  AMARYAWA                TEMP-18102
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 16 WHERE id = 4841;  -- was 87769  TSAHARE YAHYA           TEMP-18042
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 17 WHERE id = 4929;  -- was 88146  ADAMU MUHAMMAD S RUGA   TEMP-17888

-- ============================================================
-- STEP 3: Backfill PropID_Master for each new prop_id
-- primary_file_number = temp_fileno (mlsFNo is null for these OP rows)
-- ============================================================
DECLARE @Now DATETIME2 = GETDATE();

-- Map: new prop_id offset -> temp_fileno
DECLARE @rows TABLE (offset_n INT, tfn VARCHAR(50));
INSERT INTO @rows VALUES
(1,  'TEMP-17983'),
(2,  'TEMP-18115'),
(3,  'TEMP-18066'),
(4,  'TEMP-18100'),
(5,  'TEMP-17946'),
(6,  'TEMP-17969'),
(7,  'TEMP-17991'),
(8,  'TEMP-17973'),
(9,  'TEMP-18002'),
(10, 'TEMP-18062'),
(11, 'TEMP-17963'),
(12, 'TEMP-18094'),
(13, 'TEMP-18111'),
(14, 'TEMP-18118'),
(15, 'TEMP-18102'),
(16, 'TEMP-18042'),
(17, 'TEMP-17888');

-- Show which temp_filenos already exist in PropID_Master (will be skipped)
SELECT 'ALREADY_EXISTS_IN_MASTER' AS note, m.prop_id AS existing_prop_id, m.primary_file_number, r.offset_n
FROM @rows r
JOIN dbo.PropID_Master m ON m.primary_file_number = r.tfn;

-- Insert only rows where this temp_fileno is not already in PropID_Master
INSERT INTO dbo.PropID_Master (prop_id, primary_file_number, mlsFNo, temp_fileno, status, created_at, updated_at)
SELECT @MaxPropId + r.offset_n, r.tfn, NULL, r.tfn, 'active', @Now, @Now
FROM @rows r
WHERE NOT EXISTS (
    SELECT 1 FROM dbo.PropID_Master m WHERE m.primary_file_number = r.tfn
);

-- ============================================================
-- STEP 4: Verify both tables
-- ============================================================
SELECT 'IC_RESET' AS check_type, id, prop_id, instrument_type, temp_fileno, party_1_name, party_2_name
FROM dbo.instrument_capture
WHERE id IN (1250,1831,2147,2244,2250,2808,3070,3079,3827,3931,4058,4131,4284,4362,4522,4841,4929)
ORDER BY id;

SELECT 'PROPID_MASTER' AS check_type, prop_id, primary_file_number, mlsFNo, temp_fileno, status, created_at
FROM dbo.PropID_Master
WHERE prop_id BETWEEN @MaxPropId + 1 AND @MaxPropId + 17
ORDER BY prop_id;

-- ROLLBACK TRANSACTION;  -- uncomment to undo
-- COMMIT TRANSACTION;    -- uncomment after verifying both SELECTs look correct
