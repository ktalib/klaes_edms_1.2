-- Diagnose today's OSSOPCHANGEOFNAME records

-- All pra rows created today for this source
SELECT id, prop_id, instrument_type, Grantor, Grantee, temp_fileno, fileno, system_source, created_at
FROM dbo.pra
WHERE system_source = 'OSSOPCHANGEOFNAME'
  AND CAST(created_at AS DATE) = '2026-04-17'
ORDER BY prop_id, id;

-- All mls_file_no rows created today from OSS OP Change of Name
SELECT id, full_file_number, source_instrument_capture_id, source_pra_id, sub_source, source, created_at
FROM dbo.mls_file_no
WHERE source = 'OP Change of Name'
   OR sub_source = 'OP Change of Name'
   OR source_pra_id IN (
       SELECT id FROM dbo.pra
       WHERE system_source = 'OSSOPCHANGEOFNAME'
         AND CAST(created_at AS DATE) = '2026-04-17'
   )
ORDER BY id;

-- Check for prop_id collisions: prop_ids that have both an IC OP row AND a pra ToT row today
SELECT ic.id AS ic_id, ic.prop_id, ic.temp_fileno AS ic_temp, ic.party_2_name,
       tot.id AS tot_pra_id, tot.temp_fileno AS tot_temp, tot.Grantor, tot.Grantee
FROM dbo.instrument_capture ic
JOIN dbo.pra tot ON tot.prop_id = ic.prop_id
    AND tot.instrument_type = 'Transfer of Title (OP)'
    AND tot.system_source = 'OSSOPCHANGEOFNAME'
WHERE ic.instrument_type = 'Occupancy Permit (OP)'
  AND (CAST(ic.updated_at AS DATE) = '2026-04-17' OR CAST(ic.created_at AS DATE) = '2026-04-17')
ORDER BY ic.prop_id;
