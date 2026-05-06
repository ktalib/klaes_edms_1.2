-- ============================================================
-- Fix today's (2026-04-17) OSSOPCHANGEOFNAME records:
-- 1. Null out wrong source_instrument_capture_id in mls_file_no
-- 2. Set source_pra_id to the correct pra OP row
-- 3. Fix pra ToT temp_fileno to match the pra OP temp_fileno
-- ============================================================

BEGIN TRANSACTION;

-- ── PREVIEW ─────────────────────────────────────────────────

-- 1. mls_file_no rows created today with a wrong source_instrument_capture_id
SELECT m.id, m.full_file_number, m.source_instrument_capture_id, m.source_pra_id,
       m.created_at,
       ic.temp_fileno AS ic_temp_fileno, ic.party_2_name
FROM dbo.mls_file_no m
JOIN dbo.instrument_capture ic ON ic.id = m.source_instrument_capture_id
WHERE CAST(m.created_at AS DATE) = '2026-04-17'
  AND m.source_instrument_capture_id IS NOT NULL;

-- 2. pra ToT rows created today where temp_fileno != pra OP temp_fileno
SELECT tot.id, tot.prop_id, tot.instrument_type,
       tot.temp_fileno AS wrong_tot_temp, op.temp_fileno AS correct_op_temp,
       tot.Grantor, tot.Grantee, tot.system_source
FROM dbo.pra tot
JOIN dbo.pra op ON op.prop_id = tot.prop_id
    AND op.instrument_type = 'Occupancy Permit (OP)'
    AND op.system_source   = 'OSSOPCHANGEOFNAME'
WHERE tot.instrument_type = 'Transfer of Title (OP)'
  AND tot.system_source   = 'OSSOPCHANGEOFNAME'
  AND CAST(tot.created_at AS DATE) = '2026-04-17'
  AND tot.temp_fileno <> op.temp_fileno;

-- ── FIX ─────────────────────────────────────────────────────

-- Fix 1: mls_file_no — null source_instrument_capture_id, set correct source_pra_id
UPDATE m
SET m.source_instrument_capture_id = NULL,
    m.source_pra_id = (
        SELECT TOP 1 pr.id
        FROM dbo.pra pr
        WHERE pr.prop_id = ic.prop_id
          AND pr.instrument_type = 'Occupancy Permit (OP)'
          AND pr.system_source   = 'OSSOPCHANGEOFNAME'
        ORDER BY pr.id DESC
    )
FROM dbo.mls_file_no m
JOIN dbo.instrument_capture ic ON ic.id = m.source_instrument_capture_id
WHERE CAST(m.created_at AS DATE) = '2026-04-17'
  AND m.source_instrument_capture_id IS NOT NULL;

-- Fix 2: pra ToT — align temp_fileno to match the OP row for same prop_id
UPDATE tot
SET tot.temp_fileno = op.temp_fileno
FROM dbo.pra tot
JOIN dbo.pra op ON op.prop_id = tot.prop_id
    AND op.instrument_type = 'Occupancy Permit (OP)'
    AND op.system_source   = 'OSSOPCHANGEOFNAME'
WHERE tot.instrument_type = 'Transfer of Title (OP)'
  AND tot.system_source   = 'OSSOPCHANGEOFNAME'
  AND CAST(tot.created_at AS DATE) = '2026-04-17'
  AND tot.temp_fileno <> op.temp_fileno;

-- ── VERIFY ──────────────────────────────────────────────────

-- mls_file_no should have source_instrument_capture_id = NULL and valid source_pra_id
SELECT m.id, m.full_file_number, m.source_instrument_capture_id, m.source_pra_id,
       pr.instrument_type, pr.Grantor, pr.temp_fileno
FROM dbo.mls_file_no m
LEFT JOIN dbo.pra pr ON pr.id = m.source_pra_id
WHERE CAST(m.created_at AS DATE) = '2026-04-17'
  AND pr.system_source = 'OSSOPCHANGEOFNAME'
ORDER BY m.id;

-- pra ToT temp_fileno should now match OP
SELECT tot.id, tot.prop_id, tot.temp_fileno AS tot_temp, op.temp_fileno AS op_temp,
       tot.Grantor, tot.Grantee
FROM dbo.pra tot
JOIN dbo.pra op ON op.prop_id = tot.prop_id
    AND op.instrument_type = 'Occupancy Permit (OP)'
    AND op.system_source   = 'OSSOPCHANGEOFNAME'
WHERE tot.instrument_type = 'Transfer of Title (OP)'
  AND tot.system_source   = 'OSSOPCHANGEOFNAME'
  AND CAST(tot.created_at AS DATE) = '2026-04-17'
ORDER BY tot.prop_id;

-- ROLLBACK TRANSACTION;
-- COMMIT TRANSACTION;
