/* ============================================================================
   Fix the 5 rogue duplicate IC rows for RES-2026-2038..2043
   ----------------------------------------------------------------------------
   Rogue IC ids (each shares a temp_fileno with an earlier legitimate row):
       2866  shares TEMP-00704 with 711  (prop 57160)
       3988  shares TEMP-00705 with 712  (prop 57161)
       5337  shares TEMP-00706 with 713  (prop 57162)
       1751  shares TEMP-00707 with 714  (prop 57163)
       3767  shares TEMP-00708 with 715  (prop 57164)

   For each rogue row we:
     1) allocate a fresh id from temp_fileno_sequence (atomic)
     2) replace temp_fileno with TEMP-<new_id> and clear prop_id
   The pra ToT rows are NOT touched — they stay linked to the legitimate IC.

   Run, inspect verification, then COMMIT or ROLLBACK.
============================================================================ */

USE klas;
SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRAN;

DECLARE @rogues TABLE (ic_id BIGINT PRIMARY KEY, old_temp VARCHAR(50));

INSERT INTO @rogues (ic_id) VALUES (2866),(3988),(5337),(1751),(3767);

UPDATE r
SET    r.old_temp = ic.temp_fileno
FROM   @rogues r
JOIN   instrument_capture ic ON ic.id = r.ic_id;

-- For each rogue, allocate a new sequence id and apply it
DECLARE @ic_id BIGINT, @new_id INT, @new_temp VARCHAR(50);
DECLARE cur CURSOR LOCAL FAST_FORWARD FOR
    SELECT ic_id FROM @rogues ORDER BY ic_id;

OPEN cur;
FETCH NEXT FROM cur INTO @ic_id;
WHILE @@FETCH_STATUS = 0
BEGIN
    INSERT INTO temp_fileno_sequence (created_by, is_used, created_at, updated_at)
    VALUES (NULL, 1, GETDATE(), GETDATE());

    SET @new_id   = SCOPE_IDENTITY();
    SET @new_temp = 'TEMP-' + RIGHT('00000' + CAST(@new_id AS VARCHAR(10)), 5);

    UPDATE instrument_capture
    SET    temp_fileno = @new_temp,
           prop_id     = NULL,
           updated_at  = GETDATE()
    WHERE  id = @ic_id;

    FETCH NEXT FROM cur INTO @ic_id;
END
CLOSE cur;
DEALLOCATE cur;

-- Verify: the 10 rows (5 originals + 5 fixed rogues) should now have 10 distinct temp_filenos
SELECT id, temp_fileno, mlsFNo, prop_id, op_serial_number,
       CAST(instrument_type AS nvarchar(200)) AS instrument_type,
       CAST(party_1_name    AS nvarchar(500)) AS party_1_name,
       CAST(party_2_name    AS nvarchar(500)) AS party_2_name,
       created_at, updated_at
FROM   instrument_capture
WHERE  id IN (711,2866,712,3988,713,5337,714,1751,715,3767)
ORDER  BY id;

-- Confirm no IC duplicates remain for these temp_filenos
SELECT temp_fileno, COUNT(*) AS cnt
FROM   instrument_capture
WHERE  temp_fileno IN ('TEMP-00704','TEMP-00705','TEMP-00706','TEMP-00707','TEMP-00708')
GROUP  BY temp_fileno;

/* If everything looks right: */
-- COMMIT;
/* If anything is wrong: */
-- ROLLBACK;
