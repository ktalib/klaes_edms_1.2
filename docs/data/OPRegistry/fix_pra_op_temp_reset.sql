-- ============================================================
-- Reset temp_fileno on the 13 newly created pra OP rows.
-- Each row gets a fresh entry in temp_fileno_sequence.
-- ============================================================

-- ── PREVIEW: find the pra OP rows that need resetting ───────
SELECT p.id, p.prop_id, p.temp_fileno, p.Grantor, p.Grantee, p.fileno, p.created_at
FROM dbo.pra p
WHERE p.prop_id IN (
    SELECT tot.prop_id FROM dbo.pra tot
    WHERE tot.id IN (128391,128397,128406,128419,128428,128432,
                     128439,128450,128461,128484,128496,128505,128520)
)
  AND p.instrument_type = 'Occupancy Permit (OP)'
  AND p.system_source   = 'OSSOPCHANGEOFNAME'
ORDER BY p.id;

-- ── RESET via WHILE loop ─────────────────────────────────────
BEGIN TRANSACTION;

-- Collect the pra OP row ids into a table variable
DECLARE @ops TABLE (
    rn     INT IDENTITY(1,1),
    pra_id INT
);
INSERT INTO @ops (pra_id)
SELECT p.id
FROM dbo.pra p
WHERE p.prop_id IN (
    SELECT tot.prop_id FROM dbo.pra tot
    WHERE tot.id IN (128391,128397,128406,128419,128428,128432,
                     128439,128450,128461,128484,128496,128505,128520)
)
  AND p.instrument_type = 'Occupancy Permit (OP)'
  AND p.system_source   = 'OSSOPCHANGEOFNAME'
ORDER BY p.id;

DECLARE @total  INT = (SELECT COUNT(*) FROM @ops);
DECLARE @i      INT = 1;
DECLARE @pra_id INT;
DECLARE @newId  INT;
DECLARE @newTemp VARCHAR(20);

WHILE @i <= @total
BEGIN
    SELECT @pra_id = pra_id FROM @ops WHERE rn = @i;

    INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at)
    VALUES (NULL, 1, GETDATE(), GETDATE());

    SET @newId   = SCOPE_IDENTITY();
    SET @newTemp = 'TEMP-' + RIGHT('00000' + CAST(@newId AS VARCHAR), 5);

    UPDATE dbo.pra
    SET temp_fileno = @newTemp,
        updated_at  = GETDATE()
    WHERE id = @pra_id;

    -- Also sync the ToT row(s) for the same prop_id
    UPDATE dbo.pra
    SET temp_fileno = @newTemp,
        updated_at  = GETDATE()
    WHERE prop_id = (SELECT prop_id FROM dbo.pra WHERE id = @pra_id)
      AND instrument_type = 'Transfer of Title (OP)'
      AND system_source   = 'OSSOPCHANGEOFNAME';

    PRINT 'pra id ' + CAST(@pra_id AS VARCHAR) + ' → ' + @newTemp;

    SET @i = @i + 1;
END;

-- ── VERIFY: OP and ToT should now share the same temp_fileno ─
SELECT p.id, p.prop_id, p.instrument_type, p.temp_fileno, p.Grantor, p.Grantee, p.fileno
FROM dbo.pra p
WHERE p.prop_id IN (
    SELECT tot.prop_id FROM dbo.pra tot
    WHERE tot.id IN (128391,128397,128406,128419,128428,128432,
                     128439,128450,128461,128484,128496,128505,128520)
)
  AND p.system_source = 'OSSOPCHANGEOFNAME'
ORDER BY p.prop_id, p.instrument_type;

-- ROLLBACK TRANSACTION;
COMMIT TRANSACTION;
