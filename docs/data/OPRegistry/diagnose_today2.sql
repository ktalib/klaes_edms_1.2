-- Diagnose today's NEW commissioning transactions
-- Checks if today's ToT prop_ids have IC OP rows sharing the same prop_id

-- Today's commissioning prop_ids
DECLARE @todayPropIds TABLE (prop_id INT);
INSERT INTO @todayPropIds VALUES
(73901),(78011),(79612),(81050),(82002),(82387),
(83044),(83316),(84316),(84689),(86003),(86527),(88556);

-- 1. IC OP rows for today's commissioning prop_ids
SELECT 'IC_OP' AS tbl, ic.id, ic.prop_id, ic.instrument_type,
       ic.party_1_name, ic.party_2_name, ic.temp_fileno, ic.op_type
FROM dbo.instrument_capture ic
JOIN @todayPropIds t ON t.prop_id = ic.prop_id
WHERE ic.instrument_type LIKE '%Occupancy Permit%'
ORDER BY ic.prop_id;

-- 2. mls_file_no rows for today's COMs
SELECT 'MLS' AS tbl, m.id, m.full_file_number,
       m.source_instrument_capture_id, m.source_pra_id,
       m.source, m.sub_source, m.created_at
FROM dbo.mls_file_no m
WHERE m.full_file_number IN (
    'COM-2026-164','COM-2026-165','COM-2026-166','COM-2026-167',
    'COM-2026-168','COM-2026-169','COM-2026-170','COM-2026-171',
    'COM-2026-172','COM-2026-173','COM-2026-174','COM-2026-175','COM-2026-176'
)
ORDER BY m.full_file_number;

-- 3. Pair: IC OP vs pra ToT for same prop_id — shows the collision
SELECT ic.id AS ic_id, ic.prop_id, ic.temp_fileno AS ic_temp,
       ic.party_1_name, ic.party_2_name,
       tot.id AS pra_tot_id, tot.temp_fileno AS tot_temp,
       tot.Grantor, tot.Grantee, tot.fileno AS tot_com_fileno
FROM dbo.instrument_capture ic
JOIN dbo.pra tot ON tot.prop_id = ic.prop_id
    AND tot.instrument_type = 'Transfer of Title (OP)'
    AND tot.system_source = 'OSSOPCHANGEOFNAME'
JOIN @todayPropIds t ON t.prop_id = ic.prop_id
WHERE ic.instrument_type LIKE '%Occupancy Permit%'
ORDER BY ic.prop_id;
