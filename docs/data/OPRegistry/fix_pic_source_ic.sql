-- ============================================================
-- Fix mls_file_no rows: source_pra_id wrongly holds IC ids
-- Replace with the correct pra OP row id (matched by full_file_number)
-- for the 17 affected commissioning rows
-- ============================================================

BEGIN TRANSACTION;

-- Preview: show current wrong state
SELECT m.id, m.full_file_number, m.source_instrument_capture_id, m.source_pra_id AS wrong_ic_id,
       pr.id AS correct_pra_op_id, pr.instrument_type, pr.Grantor, pr.temp_fileno
FROM dbo.mls_file_no m
LEFT JOIN dbo.pra pr ON pr.temp_fileno = m.full_file_number
    AND pr.instrument_type = 'Occupancy Permit (OP)'
    AND pr.system_source = 'OSSOPCHANGEOFNAME'
WHERE m.source_pra_id IN (1250,1831,2147,2244,2250,2808,3070,3079,
                           3827,3931,4058,4131,4284,4362,4522,4841,4929);

-- Apply the fix: replace wrong IC id in source_pra_id with the real pra OP row id
UPDATE m
SET m.source_pra_id = (
        SELECT TOP 1 pr.id
        FROM dbo.pra pr
        WHERE pr.temp_fileno = m.full_file_number
          AND pr.instrument_type = 'Occupancy Permit (OP)'
          AND pr.system_source = 'OSSOPCHANGEOFNAME'
        ORDER BY pr.id DESC
    )
FROM dbo.mls_file_no m
WHERE m.source_pra_id IN (1250,1831,2147,2244,2250,2808,3070,3079,
                           3827,3931,4058,4131,4284,4362,4522,4841,4929);

-- Verify: source_pra_id should now point to a real pra OP row
SELECT m.id, m.full_file_number, m.source_pra_id,
       pr.id AS pra_id, pr.instrument_type, pr.Grantor, pr.temp_fileno
FROM dbo.mls_file_no m
JOIN dbo.pra pr ON pr.id = m.source_pra_id
WHERE pr.system_source = 'OSSOPCHANGEOFNAME'
  AND pr.instrument_type = 'Occupancy Permit (OP)'
  AND m.full_file_number LIKE 'COM-2026-%'
ORDER BY m.id;

-- ROLLBACK TRANSACTION;
-- COMMIT TRANSACTION;

-- ROLLBACK TRANSACTION;
-- COMMIT TRANSACTION;
