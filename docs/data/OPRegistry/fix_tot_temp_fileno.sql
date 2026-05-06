-- ============================================================
-- Fix pra ToT rows: set temp_fileno to match the pra OP row's temp_fileno
-- (ToT currently holds the IC's temp_fileno — e.g. TEMP-18118 instead of TEMP-18237)
-- Affects all 17 OSSOPCHANGEOFNAME ToT rows for the affected prop_ids
-- ============================================================

BEGIN TRANSACTION;

-- Preview: current ToT temp_fileno vs correct OP temp_fileno
SELECT tot.id          AS tot_pra_id,
       tot.prop_id,
       tot.instrument_type,
       tot.temp_fileno AS wrong_tot_temp_fileno,
       op.temp_fileno  AS correct_op_temp_fileno,
       tot.Grantor,
       tot.Grantee
FROM dbo.pra tot
JOIN dbo.pra op ON op.prop_id = tot.prop_id
    AND op.instrument_type = 'Occupancy Permit (OP)'
    AND op.system_source   = 'OSSOPCHANGEOFNAME'
WHERE tot.instrument_type = 'Transfer of Title (OP)'
  AND tot.system_source   = 'OSSOPCHANGEOFNAME'
  AND tot.prop_id IN (57464,58044,58359,58456,58462,62597,70292,70350,
                      78928,79760,81047,82028,83100,83739,85527,87769,88146)
  AND tot.temp_fileno <> op.temp_fileno;  -- only rows that are actually wrong

-- Apply fix
UPDATE tot
SET tot.temp_fileno = op.temp_fileno
FROM dbo.pra tot
JOIN dbo.pra op ON op.prop_id = tot.prop_id
    AND op.instrument_type = 'Occupancy Permit (OP)'
    AND op.system_source   = 'OSSOPCHANGEOFNAME'
WHERE tot.instrument_type = 'Transfer of Title (OP)'
  AND tot.system_source   = 'OSSOPCHANGEOFNAME'
  AND tot.prop_id IN (57464,58044,58359,58456,58462,62597,70292,70350,
                      78928,79760,81047,82028,83100,83739,85527,87769,88146)
  AND tot.temp_fileno <> op.temp_fileno;

-- Verify: temp_fileno on ToT should now match OP
SELECT tot.id, tot.prop_id, tot.instrument_type,
       tot.temp_fileno AS tot_temp_fileno,
       op.temp_fileno  AS op_temp_fileno,
       tot.Grantor, tot.Grantee
FROM dbo.pra tot
JOIN dbo.pra op ON op.prop_id = tot.prop_id
    AND op.instrument_type = 'Occupancy Permit (OP)'
    AND op.system_source   = 'OSSOPCHANGEOFNAME'
WHERE tot.instrument_type = 'Transfer of Title (OP)'
  AND tot.system_source   = 'OSSOPCHANGEOFNAME'
  AND tot.prop_id IN (57464,58044,58359,58456,58462,62597,70292,70350,
                      78928,79760,81047,82028,83100,83739,85527,87769,88146)
ORDER BY tot.prop_id;

-- ROLLBACK TRANSACTION;
-- COMMIT TRANSACTION;
