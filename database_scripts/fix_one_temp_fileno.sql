/* ============================================================================
   Simple per-row temp_fileno reassignment (instrument_capture)
   ----------------------------------------------------------------------------
   NOTE: deed_registrations has NO temp_fileno column.  It links back to
         instrument_capture via instrument_capture_id, so fixing the IC row
         is enough — DR rows are not affected.

   USAGE
   -----
   1. Run STEP 0 to find duplicate IDs.
   2. For each row to fix, set @target_id in STEP 1 and run the block.
   3. Inspect verification, then COMMIT or ROLLBACK.
============================================================================ */

USE klas;
SET NOCOUNT ON;
SET XACT_ABORT ON;


/* ============================================================================
   STEP 0 — find duplicates in instrument_capture (read-only)
============================================================================ */
SELECT  ic.id, ic.temp_fileno, ic.mlsFNo, ic.prop_id,
        ic.op_serial_number, ic.instrument_type, ic.created_at
FROM    instrument_capture ic
JOIN   (SELECT temp_fileno
        FROM   instrument_capture
        WHERE  temp_fileno LIKE 'TEMP-%'
        GROUP  BY temp_fileno
        HAVING COUNT(*) > 1) d ON d.temp_fileno = ic.temp_fileno
ORDER BY ic.temp_fileno, ic.id;


/* ============================================================================
   STEP 1 — reassign ONE row.
   Set @target_id, run the block, inspect verification, then COMMIT/ROLLBACK.
============================================================================ */

DECLARE @target_id BIGINT = 0;     -- <<< put the IC row id here

BEGIN TRAN;

DECLARE @old_temp VARCHAR(50);
DECLARE @file_no  VARCHAR(100);
DECLARE @new_id   INT;
DECLARE @new_temp VARCHAR(50);

SELECT @old_temp = temp_fileno,
       @file_no  = mlsFNo
FROM   instrument_capture WITH (UPDLOCK, HOLDLOCK)
WHERE  id = @target_id;

IF @old_temp IS NULL
BEGIN
    RAISERROR('Row not found or has no temp_fileno.', 16, 1);
    ROLLBACK; RETURN;
END;

-- 1) Allocate fresh sequence id (atomic)
INSERT INTO temp_fileno_sequence (created_by, is_used, created_at, updated_at)
VALUES (NULL, 1, GETDATE(), GETDATE());

SET @new_id   = SCOPE_IDENTITY();
SET @new_temp = 'TEMP-' + RIGHT('00000' + CAST(@new_id AS VARCHAR(10)), 5);

-- 2) Apply to the IC row
UPDATE instrument_capture
SET    temp_fileno = @new_temp,
       prop_id     = NULL,
       updated_at  = GETDATE()
WHERE  id = @target_id;

-- 3) Mirror to pra / pic for SAME file number (only if file_no exists)
IF @file_no IS NOT NULL AND LEN(LTRIM(RTRIM(@file_no))) > 0
BEGIN
    UPDATE pra
    SET    temp_fileno = @new_temp,
           prop_id     = NULL,
           updated_at  = GETDATE()
    WHERE  temp_fileno = @old_temp
      AND  (mlsFNo = @file_no OR fileno = @file_no);

    UPDATE pic
    SET    temp_fileno = @new_temp,
           prop_id     = NULL,
           updated_at  = GETDATE()
    WHERE  temp_fileno = @old_temp
      AND  (mlsFNo = @file_no OR fileno = @file_no);
END;

-- 4) Verify
SELECT @target_id AS target_id,
       @old_temp  AS old_temp,
       @new_temp  AS new_temp,
       @file_no   AS file_no;

SELECT 'instrument_capture' AS source, id, temp_fileno, mlsFNo, prop_id
FROM   instrument_capture WHERE id = @target_id;

SELECT 'pra' AS source, id, temp_fileno, fileno, mlsFNo, prop_id
FROM   pra
WHERE  temp_fileno IN (@old_temp, @new_temp)
ORDER  BY temp_fileno, id;

SELECT 'pic' AS source, id, temp_fileno, fileno, mlsFNo, prop_id
FROM   pic
WHERE  temp_fileno IN (@old_temp, @new_temp)
ORDER  BY temp_fileno, id;

/* If everything looks right: */
-- COMMIT;
/* If anything is wrong: */
-- ROLLBACK;
