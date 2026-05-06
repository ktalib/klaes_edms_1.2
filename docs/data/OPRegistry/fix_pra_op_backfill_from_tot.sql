-- ============================================================
-- Backfill pra OP rows with correct registration details from
-- their matching pra ToT rows (same prop_id, OSSOPCHANGEOFNAME).
-- Fields: regNo, serialNo, pageNo, volumeNo, tp_no, plot_no,
--         lgsaOrCity, location, property_description, land_use
-- ============================================================

-- ── PREVIEW: current OP vs ToT values ───────────────────────
SELECT
    op.id           AS op_id,
    tot.id          AS tot_id,
    op.prop_id,
    op.regNo        AS op_regNo,      tot.regNo        AS tot_regNo,
    op.serialNo     AS op_serialNo,   tot.serialNo     AS tot_serialNo,
    op.pageNo       AS op_pageNo,     tot.pageNo       AS tot_pageNo,
    op.volumeNo     AS op_volumeNo,   tot.volumeNo     AS tot_volumeNo,
    op.tp_no        AS op_tp_no,      tot.tp_no        AS tot_tp_no,
    op.plot_no      AS op_plot_no,    tot.plot_no      AS tot_plot_no,
    op.lgsaOrCity   AS op_lga,        tot.lgsaOrCity   AS tot_lga,
    op.location     AS op_location,   tot.location     AS tot_location,
    op.property_description AS op_desc, tot.property_description AS tot_desc,
    op.land_use     AS op_land_use,   tot.land_use     AS tot_land_use
FROM dbo.pra op
JOIN dbo.pra tot
    ON  tot.prop_id        = op.prop_id
    AND tot.instrument_type = 'Transfer of Title (OP)'
    AND tot.system_source   = 'OSSOPCHANGEOFNAME'
WHERE op.instrument_type = 'Occupancy Permit (OP)'
  AND op.system_source   = 'OSSOPCHANGEOFNAME'
  AND op.prop_id IN (
      SELECT prop_id FROM dbo.pra
      WHERE id IN (128391,128397,128406,128419,128428,128432,
                   128439,128450,128461,128484,128496,128505,128520)
  )
ORDER BY op.prop_id;

-- ── UPDATE ──────────────────────────────────────────────────
BEGIN TRANSACTION;

UPDATE op
SET
    op.regNo               = tot.regNo,
    op.serialNo            = tot.serialNo,
    op.pageNo              = tot.pageNo,
    op.volumeNo            = tot.volumeNo,
    op.tp_no               = tot.tp_no,
    op.plot_no             = tot.plot_no,
    op.lgsaOrCity          = tot.lgsaOrCity,
    op.location            = tot.location,
    op.property_description = tot.property_description,
    op.land_use            = tot.land_use,
    op.updated_at          = GETDATE()
FROM dbo.pra op
JOIN dbo.pra tot
    ON  tot.prop_id        = op.prop_id
    AND tot.instrument_type = 'Transfer of Title (OP)'
    AND tot.system_source   = 'OSSOPCHANGEOFNAME'
WHERE op.instrument_type = 'Occupancy Permit (OP)'
  AND op.system_source   = 'OSSOPCHANGEOFNAME'
  AND op.prop_id IN (
      SELECT prop_id FROM dbo.pra
      WHERE id IN (128391,128397,128406,128419,128428,128432,
                   128439,128450,128461,128484,128496,128505,128520)
  );

PRINT CAST(@@ROWCOUNT AS VARCHAR) + ' OP rows updated.';

-- ── VERIFY ──────────────────────────────────────────────────
SELECT
    op.id AS op_id, op.prop_id, op.temp_fileno,
    op.regNo, op.serialNo, op.pageNo, op.volumeNo,
    op.tp_no, op.plot_no, op.lgsaOrCity,
    op.location, op.property_description, op.land_use
FROM dbo.pra op
WHERE op.instrument_type = 'Occupancy Permit (OP)'
  AND op.system_source   = 'OSSOPCHANGEOFNAME'
  AND op.prop_id IN (
      SELECT prop_id FROM dbo.pra
      WHERE id IN (128391,128397,128406,128419,128428,128432,
                   128439,128450,128461,128484,128496,128505,128520)
  )
ORDER BY op.prop_id;

-- ROLLBACK TRANSACTION;
COMMIT TRANSACTION;
