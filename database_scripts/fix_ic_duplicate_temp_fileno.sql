/* ============================================================================
   Fix duplicate temp_fileno / shared prop_id rows in instrument_capture
   ----------------------------------------------------------------------------
   Symptom (production):
     Several distinct OP/ToT records (RES-2026-2038 ... RES-2026-2042) ended
     up sharing the SAME temp_fileno (TEMP-00704 ... TEMP-00708 reused twice)
     and the SAME prop_id in `instrument_capture`.

   Strategy:
     1. DIAGNOSE  -> list every duplicate group so you can eyeball it.
     2. BACKUP    -> snapshot affected rows into instrument_capture_dup_bak.
     3. FIX       -> keep ONE canonical row per logical OP (oldest id) and
                     reassign fresh temp_fileno + prop_id to each sibling that
                     belongs to a different real file_no.
     4. VERIFY    -> re-run the diagnostic; should return 0 rows.

   IMPORTANT
   ---------
   * Run inside a transaction.  Review STEP 1 + STEP 2 output.  Only when the
     groups look correct do you COMMIT.
   * Adjust @StartTempSeq if your `temp_fileno_sequence` is at a different
     point – the script reads MAX(id) automatically, this is a manual override
     guard only.
   * The script targets the five known temps but is generic: it will repair
     ANY duplicate temp_fileno in instrument_capture, not just the listed five.
============================================================================ */

USE klas;          -- adjust if needed
SET NOCOUNT ON;
SET XACT_ABORT ON;

-- ===== STEP 1 : DIAGNOSTIC ===================================================
PRINT '----- STEP 1: duplicate temp_fileno groups in instrument_capture -----';

;WITH dup AS (
    SELECT temp_fileno
    FROM instrument_capture
    WHERE temp_fileno IS NOT NULL
      AND temp_fileno LIKE 'TEMP-%'
    GROUP BY temp_fileno
    HAVING COUNT(*) > 1
)
SELECT  ic.id,
        ic.temp_fileno,
        ic.prop_id,
        ic.mlsFNo            AS file_no,
        ic.op_serial_number,
        ic.op_type,
        ic.party_1_name,
        ic.party_2_name,
        ic.instrument_type,
        ic.created_at
FROM    instrument_capture ic
JOIN    dup d ON d.temp_fileno = ic.temp_fileno
ORDER BY ic.temp_fileno, ic.id;

PRINT '----- STEP 1b: rows sharing prop_id but different file_no -----';
;WITH propDup AS (
    SELECT prop_id
    FROM instrument_capture
    WHERE prop_id IS NOT NULL
    GROUP BY prop_id
    HAVING COUNT(DISTINCT mlsFNo) > 1
)
SELECT  ic.id, ic.prop_id, ic.temp_fileno, ic.mlsFNo,
        ic.op_serial_number, ic.instrument_type, ic.created_at
FROM    instrument_capture ic
JOIN    propDup p ON p.prop_id = ic.prop_id
ORDER BY ic.prop_id, ic.id;


-- ===== STEP 2 : BACKUP ======================================================
IF OBJECT_ID('dbo.instrument_capture_dup_bak', 'U') IS NULL
BEGIN
    PRINT '----- STEP 2: creating backup table instrument_capture_dup_bak -----';
    SELECT TOP 0 *, CAST(SYSDATETIME() AS datetime2) AS backup_at
    INTO   dbo.instrument_capture_dup_bak
    FROM   dbo.instrument_capture;
END;

PRINT '----- STEP 2: copying duplicate rows into backup -----';
;WITH dup AS (
    SELECT temp_fileno
    FROM instrument_capture
    WHERE temp_fileno IS NOT NULL
      AND temp_fileno LIKE 'TEMP-%'
    GROUP BY temp_fileno
    HAVING COUNT(*) > 1
)
INSERT INTO dbo.instrument_capture_dup_bak
SELECT ic.*, SYSDATETIME()
FROM   instrument_capture ic
JOIN   dup d ON d.temp_fileno = ic.temp_fileno
WHERE  NOT EXISTS (
        SELECT 1 FROM dbo.instrument_capture_dup_bak b
        WHERE  b.id = ic.id
);


-- ===== STEP 3 : FIX (run inside a transaction) ==============================
BEGIN TRAN;

DECLARE @NextSeq INT = (SELECT ISNULL(MAX(id),0) + 1 FROM temp_fileno_sequence);
PRINT 'Next available temp sequence id: ' + CAST(@NextSeq AS VARCHAR(20));

/* --------------------------------------------------------------------------
   3a.  Build the dedup plan.
        For every duplicate temp_fileno group, keep the row with the SMALLEST
        id as the canonical owner of that temp.  All siblings get a fresh
        temp_fileno (and matching prop_id allocation) below.
-------------------------------------------------------------------------- */
IF OBJECT_ID('tempdb..#plan') IS NOT NULL DROP TABLE #plan;
CREATE TABLE #plan (
    id              BIGINT       PRIMARY KEY,
    old_temp        VARCHAR(50)  NOT NULL,
    old_prop_id     VARCHAR(50)  NULL,
    file_no         VARCHAR(100) NULL,
    new_temp        VARCHAR(50)  NULL,
    new_seq_id      INT          NULL,
    is_canonical    BIT          NOT NULL DEFAULT 0
);

;WITH dup AS (
    SELECT temp_fileno
    FROM instrument_capture
    WHERE temp_fileno IS NOT NULL
      AND temp_fileno LIKE 'TEMP-%'
    GROUP BY temp_fileno
    HAVING COUNT(*) > 1
), ranked AS (
    SELECT  ic.id, ic.temp_fileno, ic.prop_id, ic.mlsFNo,
            ROW_NUMBER() OVER (PARTITION BY ic.temp_fileno ORDER BY ic.id) AS rn
    FROM    instrument_capture ic
    JOIN    dup d ON d.temp_fileno = ic.temp_fileno
)
INSERT INTO #plan (id, old_temp, old_prop_id, file_no, is_canonical)
SELECT id, temp_fileno, CAST(prop_id AS VARCHAR(50)), NULLIF(LTRIM(RTRIM(mlsFNo)),''),
       CASE WHEN rn = 1 THEN 1 ELSE 0 END
FROM   ranked;

/* --------------------------------------------------------------------------
   3b.  Allocate a NEW temp_fileno_sequence row for each non-canonical sibling.
        We do this one-by-one so each row gets its own sequence id (atomic).
-------------------------------------------------------------------------- */
DECLARE @id BIGINT, @newSeq INT;
DECLARE cur CURSOR LOCAL FAST_FORWARD FOR
    SELECT id FROM #plan WHERE is_canonical = 0 ORDER BY id;
OPEN cur;
FETCH NEXT FROM cur INTO @id;
WHILE @@FETCH_STATUS = 0
BEGIN
    INSERT INTO temp_fileno_sequence (created_by, is_used, created_at, updated_at)
    VALUES (NULL, 1, SYSDATETIME(), SYSDATETIME());
    SET @newSeq = SCOPE_IDENTITY();

    UPDATE #plan
    SET    new_seq_id = @newSeq,
           new_temp   = 'TEMP-' + RIGHT('00000' + CAST(@newSeq AS VARCHAR(10)), 5)
    WHERE  id = @id;

    FETCH NEXT FROM cur INTO @id;
END;
CLOSE cur; DEALLOCATE cur;

/* --------------------------------------------------------------------------
   3c.  Apply new temp_fileno to the non-canonical IC rows.
        prop_id: leave NULL on the IC row so PropertyIdAllocationService can
        re-allocate cleanly on next touch (safer than reusing the old one).
-------------------------------------------------------------------------- */
UPDATE ic
SET    ic.temp_fileno = p.new_temp,
       ic.prop_id     = NULL,
       ic.updated_at  = SYSDATETIME()
FROM   instrument_capture ic
JOIN   #plan p ON p.id = ic.id
WHERE  p.is_canonical = 0;

/* --------------------------------------------------------------------------
   3d.  Mirror the temp_fileno change into related tables that reference it.
        Adjust the list if your schema has more.
-------------------------------------------------------------------------- */
-- pra
IF OBJECT_ID('dbo.pra','U') IS NOT NULL
UPDATE pra
SET    temp_fileno = p.new_temp,
       prop_id     = NULL,
       updated_at  = SYSDATETIME()
FROM   pra
JOIN   #plan p ON p.old_temp = pra.temp_fileno
            AND  ( (p.file_no IS NOT NULL AND
                    (pra.mlsFNo = p.file_no OR pra.fileno = p.file_no))
                   OR p.file_no IS NULL )
WHERE  p.is_canonical = 0;

-- pic
IF OBJECT_ID('dbo.pic','U') IS NOT NULL
UPDATE pic
SET    temp_fileno = p.new_temp,
       prop_id     = NULL,
       updated_at  = SYSDATETIME()
FROM   pic
JOIN   #plan p ON p.old_temp = pic.temp_fileno
            AND  ( (p.file_no IS NOT NULL AND
                    (pic.mlsFNo = p.file_no OR pic.fileno = p.file_no))
                   OR p.file_no IS NULL )
WHERE  p.is_canonical = 0;


-- ===== STEP 4 : VERIFY ======================================================
PRINT '----- STEP 4: post-fix duplicate check (should be empty) -----';
SELECT  ic.temp_fileno, COUNT(*) AS cnt
FROM    instrument_capture ic
WHERE   ic.temp_fileno LIKE 'TEMP-%'
GROUP BY ic.temp_fileno
HAVING  COUNT(*) > 1;

PRINT '----- STEP 4: rows that were rewritten -----';
SELECT id, old_temp, new_temp, file_no, is_canonical FROM #plan ORDER BY old_temp, id;

/* --------------------------------------------------------------------------
   Review the output above.
   If the result of the post-fix duplicate check is empty AND the rewrite
   list looks correct, COMMIT.  Otherwise, ROLLBACK.
-------------------------------------------------------------------------- */
-- COMMIT;
-- ROLLBACK;
