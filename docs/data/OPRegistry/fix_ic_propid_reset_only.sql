-- ============================================================
-- MINIMAL IC prop_id reset — no table variables, no PropID_Master
-- Just 13 plain UPDATEs in one transaction.
-- IC ids: 3277,3722,3905,4059,4127,4205,4277,4325,4396,4422,4589,4663,4995
-- Run this AFTER fix_today_records.sql (mls_file_no already fixed).
-- ============================================================

-- First confirm current IC prop_ids (should still be original values):
SELECT id, prop_id, temp_fileno, party_2_name
FROM dbo.instrument_capture
WHERE id IN (3277,3722,3905,4059,4127,4205,4277,4325,4396,4422,4589,4663,4995)
ORDER BY id;

-- Confirm max prop_id across all tables:
SELECT MAX(v) AS max_prop_id FROM (
    SELECT MAX(TRY_CAST(prop_id AS INT)) AS v FROM dbo.pra
    UNION ALL
    SELECT MAX(TRY_CAST(prop_id AS INT)) AS v FROM dbo.instrument_capture
    UNION ALL
    SELECT MAX(TRY_CAST(prop_id AS INT)) AS v FROM dbo.PropID_Master
) x;

-- ── RUN THE FIX ─────────────────────────────────────────────
-- Using 69572841-69572853 (above current max of 69572840).
-- These are safe in instrument_capture regardless of PropID_Master contents.
-- The AND prop_id = <original> guard prevents double-run damage.

BEGIN TRANSACTION;

UPDATE dbo.instrument_capture SET prop_id = 69572841  WHERE id = 3277  AND prop_id = 73901;
UPDATE dbo.instrument_capture SET prop_id = 69572842  WHERE id = 3722  AND prop_id = 78011;
UPDATE dbo.instrument_capture SET prop_id = 69572843  WHERE id = 3905  AND prop_id = 79612;
UPDATE dbo.instrument_capture SET prop_id = 69572844  WHERE id = 4059  AND prop_id = 81050;
UPDATE dbo.instrument_capture SET prop_id = 69572845  WHERE id = 4127  AND prop_id = 82002;
UPDATE dbo.instrument_capture SET prop_id = 69572846  WHERE id = 4205  AND prop_id = 82387;
UPDATE dbo.instrument_capture SET prop_id = 69572847  WHERE id = 4277  AND prop_id = 83044;
UPDATE dbo.instrument_capture SET prop_id = 69572848  WHERE id = 4325  AND prop_id = 83316;
UPDATE dbo.instrument_capture SET prop_id = 69572849  WHERE id = 4396  AND prop_id = 84316;
UPDATE dbo.instrument_capture SET prop_id = 69572850  WHERE id = 4422  AND prop_id = 84689;
UPDATE dbo.instrument_capture SET prop_id = 69572851  WHERE id = 4589  AND prop_id = 86003;
UPDATE dbo.instrument_capture SET prop_id = 69572852  WHERE id = 4663  AND prop_id = 86527;
UPDATE dbo.instrument_capture SET prop_id = 69572853  WHERE id = 4995  AND prop_id = 88556;

-- Verify: should return 13 rows with new prop_ids
SELECT id, prop_id, temp_fileno, party_2_name
FROM dbo.instrument_capture
WHERE id IN (3277,3722,3905,4059,4127,4205,4277,4325,4396,4422,4589,4663,4995)
ORDER BY id;

-- Verify: collisions should now be 0
SELECT ic.id AS ic_id, ic.prop_id, ic.temp_fileno AS ic_temp,
       tot.id AS pra_tot_id, tot.temp_fileno AS tot_temp, tot.fileno
FROM dbo.instrument_capture ic
JOIN dbo.pra tot ON tot.prop_id = ic.prop_id
    AND tot.instrument_type = 'Transfer of Title (OP)'
    AND tot.system_source   = 'OSSOPCHANGEOFNAME'
WHERE ic.id IN (3277,3722,3905,4059,4127,4205,4277,4325,4396,4422,4589,4663,4995);

-- ROLLBACK TRANSACTION;
COMMIT TRANSACTION;
