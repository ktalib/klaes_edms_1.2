-- Fix COM-2026-162 (prop_id 83739):
-- 1. Null out source_instrument_capture_id on the mls_file_no commissioning row
-- 2. Point source_pra_id to the correct pra OP row (TEMP-18237, pra id=128126)

BEGIN TRANSACTION;

-- Preview
SELECT id, full_file_number, source_instrument_capture_id, source_pra_id
FROM dbo.mls_file_no
WHERE full_file_number = 'COM-2026-162';

-- Fix
UPDATE dbo.mls_file_no
SET source_instrument_capture_id = NULL,
    source_pra_id = 128126   -- pra OP row: prop_id=83739, TEMP-18237, Kano State Gov -> ALI DANLAMI
WHERE full_file_number = 'COM-2026-162';

-- Verify
SELECT m.id, m.full_file_number, m.source_instrument_capture_id, m.source_pra_id,
       pr.instrument_type, pr.Grantor, pr.Grantee, pr.temp_fileno
FROM dbo.mls_file_no m
JOIN dbo.pra pr ON pr.id = m.source_pra_id
WHERE m.full_file_number = 'COM-2026-162';

-- ROLLBACK TRANSACTION;
COMMIT TRANSACTION;
