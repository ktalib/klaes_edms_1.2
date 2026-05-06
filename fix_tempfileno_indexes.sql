-- =============================================================================
-- fix_tempfileno_indexes.sql
-- Generated: 2026-04-13 from check_tempfileno_fix.php diagnostic
--
-- Problem:
--   dbo.pra.temp_fileno is NVARCHAR(MAX) which blocks:
--     (a) ALTER COLUMN (existing indexes depend on it)
--     (b) any CREATE INDEX that uses it as a KEY column (Msg 1919)
--
-- Actual max data length in pra.temp_fileno  : 14 chars  (safe to shrink to 450)
-- Actual max data length in ic.temp_fileno   : 10 chars  (already NVARCHAR(450))
--
-- Fix:
--   STEP 1 – Drop all indexes that reference temp_fileno on dbo.pra
--   STEP 2 – ALTER dbo.pra.temp_fileno to NVARCHAR(450)
--   STEP 3 – Rebuild those pra indexes (with temp_fileno as INCLUDE, not key)
--   STEP 4 – Create the new performance indexes for dbo.instrument_capture
--            (no ALTER needed; ic.temp_fileno is already NVARCHAR(450))
-- =============================================================================

-- ============================================================
-- STEP 1: Drop all existing pra indexes that block the ALTER
-- ============================================================
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.pra') AND name = 'IX_pra_prop_id_temp_fileno')
    DROP INDEX [IX_pra_prop_id_temp_fileno] ON dbo.pra;

IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.pra') AND name = 'IX_pra_system_prop_instr_id')
    DROP INDEX [IX_pra_system_prop_instr_id] ON dbo.pra;

IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.pra') AND name = 'IX_pra_system_mlsFNo_id')
    DROP INDEX [IX_pra_system_mlsFNo_id] ON dbo.pra;

IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.pra') AND name = 'IX_pra_system_fileno_id')
    DROP INDEX [IX_pra_system_fileno_id] ON dbo.pra;

-- Also drop the new index that failed last time (may not exist yet, safe to try)
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.pra') AND name = 'IX_pra_system_tempfileno_id')
    DROP INDEX [IX_pra_system_tempfileno_id] ON dbo.pra;

GO

-- ============================================================
-- STEP 2: Fix the column type on dbo.pra
-- ============================================================
ALTER TABLE dbo.pra
    ALTER COLUMN temp_fileno NVARCHAR(450) NULL;
GO

-- ============================================================
-- STEP 3: Rebuild all pra indexes exactly as they were,
--         with temp_fileno correctly placed as INCLUDE column
-- ============================================================

-- Covers: prop_id lookups needing temp_fileno
CREATE INDEX [IX_pra_prop_id_temp_fileno]
    ON dbo.pra ([prop_id])
    INCLUDE ([temp_fileno]);

-- Covers: system_source + fileno + id queries (change-of-name / OSSOPCHANGEOFNAME)
CREATE INDEX [IX_pra_system_fileno_id]
    ON dbo.pra ([system_source], [fileno], [id])
    INCLUDE ([prop_id], [mlsFNo], [temp_fileno], [instrument_type], [transaction_type], [created_at]);

-- Covers: system_source + mlsFNo + id queries
CREATE INDEX [IX_pra_system_mlsFNo_id]
    ON dbo.pra ([system_source], [mlsFNo], [id])
    INCLUDE ([prop_id], [fileno], [temp_fileno], [instrument_type], [transaction_type], [created_at]);

-- Covers: system_source + prop_id + instrument_type + id queries (main UNION ALL query)
CREATE INDEX [IX_pra_system_prop_instr_id]
    ON dbo.pra ([system_source], [prop_id], [instrument_type], [id])
    INCLUDE ([mlsFNo], [fileno], [temp_fileno], [Grantor], [Grantee],
             [party_1], [party_2], [regNo], [op_type], [op_serial_number],
             [property_description], [location], [created_by], [created_at],
             [plot_no], [tp_no], [lgsaOrCity], [land_use], [transaction_type]);

-- NEW: temp_fileno as key column (now valid since column is NVARCHAR(450))
-- Covers: temp_fileno-based searches on pra
CREATE INDEX [IX_pra_system_tempfileno_id]
    ON dbo.pra ([system_source], [temp_fileno], [id])
    INCLUDE ([prop_id], [mlsFNo], [fileno], [instrument_type], [transaction_type], [created_at]);

GO

-- ============================================================
-- STEP 4: New performance indexes on dbo.instrument_capture
--         (temp_fileno is already NVARCHAR(450) here — no ALTER needed)
-- ============================================================

-- Drop first if they exist from a previous partial run
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.instrument_capture') AND name = 'IX_ic_instr_isdel_prop_id')
    DROP INDEX [IX_ic_instr_isdel_prop_id] ON dbo.instrument_capture;

IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.instrument_capture') AND name = 'IX_ic_mlsFNo_instr_isdel_id')
    DROP INDEX [IX_ic_mlsFNo_instr_isdel_id] ON dbo.instrument_capture;

IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.instrument_capture') AND name = 'IX_ic_tempfileno_instr_isdel_id')
    DROP INDEX [IX_ic_tempfileno_instr_isdel_id] ON dbo.instrument_capture;

GO

-- Covers: instrument_type + is_deleted + prop_id + id  (main UNION ALL join)
CREATE INDEX [IX_ic_instr_isdel_prop_id]
    ON dbo.instrument_capture ([instrument_type], [is_deleted], [prop_id], [id])
    INCLUDE ([mlsFNo], [kangisFileNo], [NewKANGISFileno], [temp_fileno],
             [op_type], [op_serial_number], [property_description],
             [property_location], [plot_number], [tp_no], [lga],
             [party_1_name], [party_2_name], [registration_number],
             [created_by], [created_at], [land_use]);

-- Covers: mlsFNo + instrument_type + is_deleted + id  (mls_file_no join)
CREATE INDEX [IX_ic_mlsFNo_instr_isdel_id]
    ON dbo.instrument_capture ([mlsFNo], [instrument_type], [is_deleted], [id])
    INCLUDE ([prop_id], [temp_fileno], [op_type], [op_serial_number], [created_at]);

-- Covers: temp_fileno + instrument_type + is_deleted + id  (temp file searches)
CREATE INDEX [IX_ic_tempfileno_instr_isdel_id]
    ON dbo.instrument_capture ([temp_fileno], [instrument_type], [is_deleted], [id])
    INCLUDE ([prop_id], [mlsFNo], [created_at]);

GO

-- ============================================================
-- Verify: confirm column type after fix
-- ============================================================
SELECT
    o.name       AS table_name,
    c.name       AS column_name,
    t.name       AS data_type,
    c.max_length AS max_length_bytes,
    c.max_length / 2 AS max_chars
FROM sys.columns c
JOIN sys.types   t ON c.user_type_id = t.user_type_id
JOIN sys.objects o ON c.object_id    = o.object_id
WHERE o.type = 'U'
  AND o.name IN ('pra', 'instrument_capture')
  AND c.name = 'temp_fileno';
GO
