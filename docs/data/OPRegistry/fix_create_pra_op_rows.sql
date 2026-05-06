-- ============================================================
-- Create missing pra OP rows for today's 13 OSSOPCHANGEOFNAME cases.
-- Each new pra OP row gets a freshly allocated TEMP file number
-- from temp_fileno_sequence (SCOPE_IDENTITY pattern).
--
-- Grantor  = IC.party_1_name  (Kano State Government - original issuer)
-- Grantee  = pra ToT.Grantor  (current holder, e.g. MAGAJI INUWA)
-- prop_id  = pra ToT.prop_id  (shared with the ToT - correct)
--
-- Mapping: IC id → pra ToT id (known from diagnostics)
-- ============================================================

-- ── PREVIEW: what will be inserted ──────────────────────────
SELECT
    tot.id              AS pra_tot_id,
    tot.prop_id,
    ic.id               AS ic_id,
    ic.temp_fileno,
    tot.fileno          AS mls_fileno,
    ic.registration_number  AS regNo,
    ic.serial_no,
    ic.page_no,
    ic.volume_no,
    ic.op_serial_number,
    ic.op_type,
    ic.party_1_name     AS Grantor,
    tot.Grantor         AS Grantee,
    ic.land_use,
    ic.tp_no,
    ic.plot_number      AS plot_no,
    ic.lga              AS lgsaOrCity,
    ic.property_location AS location
FROM (VALUES
    (3277,  128450),
    (3722,  128397),
    (3905,  128505),
    (4059,  128520),
    (4127,  128461),
    (4205,  128484),
    (4277,  128432),
    (4325,  128428),
    (4396,  128419),
    (4422,  128496),
    (4589,  128406),
    (4663,  128439),
    (4995,  128391)
) AS m(ic_id, pra_tot_id)
JOIN dbo.instrument_capture ic  ON ic.id  = m.ic_id
JOIN dbo.pra tot                ON tot.id = m.pra_tot_id
ORDER BY ic.id;

-- ── GUARD: confirm no pra OP already exists for these prop_ids ──
SELECT 'EXISTING_PRA_OP' AS note, p.id, p.prop_id, p.instrument_type, p.temp_fileno
FROM dbo.pra p
WHERE p.prop_id IN (
    SELECT tot.prop_id FROM dbo.pra tot
    WHERE tot.id IN (128391,128397,128406,128419,128428,128432,
                     128439,128450,128461,128484,128496,128505,128520)
)
  AND p.instrument_type = 'Occupancy Permit (OP)'
  AND p.system_source   = 'OSSOPCHANGEOFNAME';

-- ── INSERT via WHILE loop — one new TEMP per pra OP row ─────
BEGIN TRANSACTION;

DECLARE @map TABLE (
    rn         INT IDENTITY(1,1),
    ic_id      INT,
    pra_tot_id INT
);
INSERT INTO @map (ic_id, pra_tot_id) VALUES
    (3277,  128450),
    (3722,  128397),
    (3905,  128505),
    (4059,  128520),
    (4127,  128461),
    (4205,  128484),
    (4277,  128432),
    (4325,  128428),
    (4396,  128419),
    (4422,  128496),
    (4589,  128406),
    (4663,  128439),
    (4995,  128391);

DECLARE @i          INT = 1;
DECLARE @newId      INT;
DECLARE @newTemp    VARCHAR(20);
DECLARE @ic_id      INT;
DECLARE @pra_tot_id INT;

WHILE @i <= 13
BEGIN
    SELECT @ic_id = ic_id, @pra_tot_id = pra_tot_id
    FROM @map WHERE rn = @i;

    -- Skip if pra OP already exists for this prop_id
    IF NOT EXISTS (
        SELECT 1 FROM dbo.pra p2
        JOIN dbo.pra tot2 ON tot2.id = @pra_tot_id
        WHERE p2.prop_id        = tot2.prop_id
          AND p2.instrument_type = 'Occupancy Permit (OP)'
          AND p2.system_source   = 'OSSOPCHANGEOFNAME'
    )
    BEGIN
        -- Allocate a new TEMP file number
        INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at)
        VALUES (NULL, 1, GETDATE(), GETDATE());

        SET @newId   = SCOPE_IDENTITY();
        SET @newTemp = 'TEMP-' + RIGHT('00000' + CAST(@newId AS VARCHAR), 5);

        -- Insert the pra OP row
        INSERT INTO dbo.pra (
            prop_id, temp_fileno, fileno, mlsFNo,
            instrument_type, transaction_type, system_source, source,
            op_type, op_serial_number,
            regNo, serialNo, pageNo, volumeNo,
            Grantor, Grantee, party_1, party_2,
            land_use, tp_no, plot_no, lgsaOrCity, location, property_description,
            created_by, created_at, updated_at
        )
        SELECT
            tot.prop_id,
            @newTemp,
            tot.fileno,
            tot.fileno,
            'Occupancy Permit (OP)',
            'Occupancy Permit',
            'OSSOPCHANGEOFNAME',
            'OP Change of Name',
            ic.op_type,
            ic.op_serial_number,
            ic.registration_number,
            ic.serial_no,
            ic.page_no,
            ic.volume_no,
            ic.party_1_name,   -- Grantor = Kano State Government
            tot.Grantor,       -- Grantee = current holder (e.g. MAGAJI INUWA)
            ic.party_1_name,
            tot.Grantor,
            ic.land_use,
            ic.tp_no,
            ic.plot_number,
            ic.lga,
            ic.property_location,
            ic.property_location,
            tot.created_by,
            GETDATE(),
            GETDATE()
        FROM dbo.instrument_capture ic
        JOIN dbo.pra tot ON tot.id = @pra_tot_id
        WHERE ic.id = @ic_id;

        PRINT 'Row ' + CAST(@i AS VARCHAR) + ': IC ' + CAST(@ic_id AS VARCHAR)
            + ' → new TEMP = ' + @newTemp;
    END
    ELSE
        PRINT 'Row ' + CAST(@i AS VARCHAR) + ': SKIPPED (pra OP already exists)';

    SET @i = @i + 1;
END;

-- ── VERIFY ──────────────────────────────────────────────────
SELECT p.id, p.prop_id, p.instrument_type, p.temp_fileno,
       p.Grantor, p.Grantee, p.regNo, p.op_serial_number, p.fileno
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
