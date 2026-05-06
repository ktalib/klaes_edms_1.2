-- ============================================================
-- Fix today's (2026-04-17) 13 IC OP prop_id collisions
-- IC ids: 3277,3722,3905,4059,4127,4205,4277,4325,4396,4422,4589,4663,4995
-- ============================================================

BEGIN TRANSACTION;

DECLARE @MaxPropId INT;
SELECT @MaxPropId = MAX(v) FROM (
    SELECT MAX(TRY_CAST(prop_id AS INT)) AS v FROM dbo.file_history_staging
    UNION ALL
    SELECT MAX(TRY_CAST(prop_id AS INT)) AS v FROM dbo.pra
    UNION ALL
    SELECT MAX(TRY_CAST(prop_id AS INT)) AS v FROM dbo.pic
    UNION ALL
    SELECT MAX(TRY_CAST(prop_id AS INT)) AS v FROM dbo.CofO_staging
    UNION ALL
    SELECT MAX(TRY_CAST(prop_id AS INT)) AS v FROM dbo.PropID_Master
    UNION ALL
    SELECT MAX(TRY_CAST(prop_id AS INT)) AS v FROM dbo.instrument_capture
) x;

PRINT 'Max prop_id: ' + CAST(@MaxPropId AS VARCHAR);

-- ── STEP 1: Reset IC prop_ids ────────────────────────────────
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 1  WHERE id = 3277;  -- was 73901  MUHAMMAD SANI ADO  TEMP-18393
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 2  WHERE id = 3722;  -- was 78011  KABIRU SULE        TEMP-18365
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 3  WHERE id = 3905;  -- was 79612  YAU INUWA          TEMP-18425
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 4  WHERE id = 4059;  -- was 81050  ALHAJI YAWALE      TEMP-18433
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 5  WHERE id = 4127;  -- was 82002  YASIR USMAN        TEMP-18396
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 6  WHERE id = 4205;  -- was 82387  ABUBAKAR SAIDU     TEMP-18412
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 7  WHERE id = 4277;  -- was 83044  USMAN AUWAL        TEMP-18384
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 8  WHERE id = 4325;  -- was 83316  MUHAMMAD SANI      TEMP-18380
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 9  WHERE id = 4396;  -- was 84316  SHETTIMA           TEMP-18373
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 10 WHERE id = 4422;  -- was 84689  DATTIJO MUKHTAR    TEMP-18419
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 11 WHERE id = 4589;  -- was 86003  AMIRU MUHAMMAD     TEMP-18369
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 12 WHERE id = 4663;  -- was 86527  BASHIR AUWALU      TEMP-18387
UPDATE dbo.instrument_capture SET prop_id = @MaxPropId + 13 WHERE id = 4995;  -- was 88556  INUWA HARUNA       TEMP-18273

-- ── STEP 2: Backfill PropID_Master (skip if temp_fileno already exists) ──
DECLARE @Now DATETIME2 = GETDATE();
DECLARE @rows TABLE (offset_n INT, tfn VARCHAR(50));
INSERT INTO @rows VALUES
(1,  'TEMP-18393'),
(2,  'TEMP-18365'),
(3,  'TEMP-18425'),
(4,  'TEMP-18433'),
(5,  'TEMP-18396'),
(6,  'TEMP-18412'),
(7,  'TEMP-18384'),
(8,  'TEMP-18380'),
(9,  'TEMP-18373'),
(10, 'TEMP-18419'),
(11, 'TEMP-18369'),
(12, 'TEMP-18387'),
(13, 'TEMP-18273');

-- Show which already exist
SELECT 'ALREADY_IN_MASTER' AS note, m.prop_id, m.primary_file_number, r.offset_n
FROM @rows r JOIN dbo.PropID_Master m ON m.primary_file_number = r.tfn;

INSERT INTO dbo.PropID_Master (prop_id, primary_file_number, mlsFNo, temp_fileno, status, created_at, updated_at)
SELECT @MaxPropId + r.offset_n, r.tfn, NULL, r.tfn, 'active', @Now, @Now
FROM @rows r
WHERE NOT EXISTS (SELECT 1 FROM dbo.PropID_Master m WHERE m.primary_file_number = r.tfn)
  AND NOT EXISTS (SELECT 1 FROM dbo.PropID_Master m WHERE m.prop_id = @MaxPropId + r.offset_n);

-- ── STEP 3: Fix mls_file_no source_pra_id (currently = wrong IC ids) ──
-- Point source_pra_id to the actual pra ToT row for each COM
UPDATE dbo.mls_file_no SET source_pra_id = 128450 WHERE full_file_number = 'COM-2026-171';  -- prop 73901 TEMP-18393
UPDATE dbo.mls_file_no SET source_pra_id = 128397 WHERE full_file_number = 'COM-2026-165';  -- prop 78011 TEMP-18365
UPDATE dbo.mls_file_no SET source_pra_id = 128505 WHERE full_file_number = 'COM-2026-175';  -- prop 79612 TEMP-18425
UPDATE dbo.mls_file_no SET source_pra_id = 128520 WHERE full_file_number = 'COM-2026-176';  -- prop 81050 TEMP-18433
UPDATE dbo.mls_file_no SET source_pra_id = 128461 WHERE full_file_number = 'COM-2026-172';  -- prop 82002 TEMP-18396
UPDATE dbo.mls_file_no SET source_pra_id = 128484 WHERE full_file_number = 'COM-2026-173';  -- prop 82387 TEMP-18412
UPDATE dbo.mls_file_no SET source_pra_id = 128432 WHERE full_file_number = 'COM-2026-169';  -- prop 83044 TEMP-18384
UPDATE dbo.mls_file_no SET source_pra_id = 128428 WHERE full_file_number = 'COM-2026-168';  -- prop 83316 TEMP-18380
UPDATE dbo.mls_file_no SET source_pra_id = 128419 WHERE full_file_number = 'COM-2026-167';  -- prop 84316 TEMP-18373
UPDATE dbo.mls_file_no SET source_pra_id = 128496 WHERE full_file_number = 'COM-2026-174';  -- prop 84689 TEMP-18419
UPDATE dbo.mls_file_no SET source_pra_id = 128406 WHERE full_file_number = 'COM-2026-166';  -- prop 86003 TEMP-18369
UPDATE dbo.mls_file_no SET source_pra_id = 128439 WHERE full_file_number = 'COM-2026-170';  -- prop 86527 TEMP-18387
UPDATE dbo.mls_file_no SET source_pra_id = 128391 WHERE full_file_number = 'COM-2026-164';  -- prop 88556 TEMP-18273

-- ── STEP 4: Verify ──────────────────────────────────────────
SELECT 'IC' AS tbl, id, prop_id, temp_fileno, party_2_name
FROM dbo.instrument_capture
WHERE id IN (3277,3722,3905,4059,4127,4205,4277,4325,4396,4422,4589,4663,4995)
ORDER BY id;

SELECT 'MLS' AS tbl, m.id, m.full_file_number, m.source_pra_id,
       pr.instrument_type, pr.Grantor, pr.Grantee
FROM dbo.mls_file_no m
LEFT JOIN dbo.pra pr ON pr.id = m.source_pra_id
WHERE m.full_file_number IN (
    'COM-2026-164','COM-2026-165','COM-2026-166','COM-2026-167','COM-2026-168',
    'COM-2026-169','COM-2026-170','COM-2026-171','COM-2026-172','COM-2026-173',
    'COM-2026-174','COM-2026-175','COM-2026-176'
)
ORDER BY m.full_file_number;

-- ROLLBACK TRANSACTION;
-- COMMIT TRANSACTION;
