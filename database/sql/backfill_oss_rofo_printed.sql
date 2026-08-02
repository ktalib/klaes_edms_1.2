/* =============================================================================
   Backfill: OSS RofO records that were physically printed before the print
   counter existed.

   Symptom
   -------
   340 OSS rows in land_recommendations carry a Security Paper Code
   (land_rofo_serial_no, e.g. '00269') but have rofo_print_count = 0, so the
   RofO page lists them under "Not Printed" and the Print Date column reads
   "Not printed".

   Where the date comes from
   -------------------------
   LandRofoController::index() resolves Print Date as:
       security_codes MIN(created_at) for the record,
       overridden by MAX(print_logs.created_at) matched on file number.
   These rows have neither, so PART 2 writes a print_logs row dated from
   rofo_generated_at (populated on 336 of the 340; created_at is the fallback).

   Notes
   -----
   * The paper codes on these rows are NOT drawn from global_security_paper_codes
     (0 of 340 match), so there is no assigned_at there to use as a date.
   * PART 3 is OPTIONAL and fabricates a Security Serial No. that is not on the
     paper already issued. Read its header before running it.
   * Every part is re-runnable; guards skip rows already done.
   * Run PART 1 first and eyeball the output. Nothing before PART 2 writes.
   ============================================================================= */

USE [klas];   -- DB_SQLSRV_DATABASE; adjust if your environment differs
GO

/* -----------------------------------------------------------------------------
   PART 1 — PREVIEW (read only)
   -------------------------------------------------------------------------- */

-- 1a. Headline counts for all OSS RofO rows.
SELECT
    COUNT(*)                                                                   AS oss_total,
    SUM(CASE WHEN ISNULL(rofo_print_count, 0) > 0 THEN 1 ELSE 0 END)           AS already_marked_printed,
    SUM(CASE WHEN NULLIF(LTRIM(RTRIM(ISNULL(land_rofo_serial_no, ''))), '') IS NOT NULL
             THEN 1 ELSE 0 END)                                                AS has_paper_code,
    SUM(CASE WHEN NULLIF(LTRIM(RTRIM(ISNULL(land_rofo_serial_no, ''))), '') IS NOT NULL
              AND ISNULL(rofo_print_count, 0) = 0
             THEN 1 ELSE 0 END)                                                AS to_backfill
FROM land_recommendations
WHERE UPPER(ISNULL(type, '')) = 'OSS';

-- 1b. The exact rows PART 2 will touch, with the date each will receive.
SELECT
    lr.id,
    lr.file_number,
    lr.land_rofo_serial_no                              AS paper_code,
    lr.rofo_status,
    ISNULL(lr.rofo_print_count, 0)                      AS print_count_now,
    COALESCE(lr.rofo_generated_at, lr.created_at)       AS print_date_to_set,
    CASE WHEN lr.rofo_generated_at IS NULL
         THEN 'created_at (no rofo_generated_at)' ELSE 'rofo_generated_at' END AS date_source,
    CASE WHEN sc.document_id IS NULL THEN 'MISSING' ELSE 'present' END         AS security_serial,
    CASE WHEN pl.reference_number IS NULL THEN 'none' ELSE 'already logged' END AS existing_print_log
FROM land_recommendations lr
LEFT JOIN (
        SELECT document_id, MIN(created_at) AS serial_date
        FROM security_codes
        WHERE document_type = 'Land ROFO'
        GROUP BY document_id
    ) sc ON sc.document_id = lr.id
LEFT JOIN (
        SELECT DISTINCT UPPER(LTRIM(RTRIM(reference_number))) AS reference_number
        FROM print_logs
        WHERE document_type = 'Land ROFO'
    ) pl ON pl.reference_number = UPPER(LTRIM(RTRIM(lr.file_number)))
WHERE UPPER(ISNULL(lr.type, '')) = 'OSS'
  AND NULLIF(LTRIM(RTRIM(ISNULL(lr.land_rofo_serial_no, ''))), '') IS NOT NULL
  AND ISNULL(lr.rofo_print_count, 0) = 0
ORDER BY lr.id DESC;
GO


/* -----------------------------------------------------------------------------
   PART 2 — BACKFILL print date + print count

   Writes one print_logs row per record (print_type 'Backfill' so it is
   distinguishable from real prints and does not interfere with the batch-print
   "unprinted" list, which keys off print_type = 'LandRofoBatch'), then sets
   rofo_print_count = 1. Count 1 (not 2) leaves one legitimate reprint available,
   since print() aborts at >= 2.
   -------------------------------------------------------------------------- */

BEGIN TRANSACTION;

-- 2a. Print log rows, dated from the record itself.
INSERT INTO print_logs (reference_number, document_type, print_type, status, user_id, created_at, updated_at)
SELECT
    lr.file_number,
    'Land ROFO',
    'Backfill',
    'Original',
    NULL,                                            -- no user: this was a pre-counter print
    COALESCE(lr.rofo_generated_at, lr.created_at),
    SYSDATETIME()
FROM land_recommendations lr
WHERE UPPER(ISNULL(lr.type, '')) = 'OSS'
  AND NULLIF(LTRIM(RTRIM(ISNULL(lr.land_rofo_serial_no, ''))), '') IS NOT NULL
  AND ISNULL(lr.rofo_print_count, 0) = 0
  AND NOT EXISTS (
        SELECT 1 FROM print_logs p
        WHERE p.document_type = 'Land ROFO'
          AND p.print_type    = 'Backfill'
          AND UPPER(LTRIM(RTRIM(p.reference_number))) = UPPER(LTRIM(RTRIM(lr.file_number)))
  );

PRINT CONCAT('print_logs rows inserted: ', @@ROWCOUNT);

-- 2b. Mark the records printed. updated_at is deliberately left alone so the
--     audit timestamp still reflects the last real edit, not this backfill.
UPDATE lr
SET    lr.rofo_print_count = 1
FROM   land_recommendations lr
WHERE  UPPER(ISNULL(lr.type, '')) = 'OSS'
  AND  NULLIF(LTRIM(RTRIM(ISNULL(lr.land_rofo_serial_no, ''))), '') IS NOT NULL
  AND  ISNULL(lr.rofo_print_count, 0) = 0;

PRINT CONCAT('land_recommendations rows marked printed: ', @@ROWCOUNT);

-- Inspect the two counts above, then:
COMMIT TRANSACTION;      -- or: ROLLBACK TRANSACTION;
GO


/* -----------------------------------------------------------------------------
   PART 3 — OPTIONAL: generate a Security Serial No. for rows that have none

   *** READ BEFORE RUNNING ***
   The Security Serial No. is the green code printed on the certificate. These
   documents were already printed WITHOUT one, so any value generated here will
   not match the paper in the applicant's hand — it only makes the on-screen row
   look like a Land RofO row. Skip this part if the register must reflect what
   was actually printed.

   Format mirrors SecurityCodeService::generateCode(): 9 chars, 8 digits plus one
   A-Z letter at a random position, unique across security_codes.
   -------------------------------------------------------------------------- */

/*
BEGIN TRANSACTION;

DECLARE @id BIGINT, @file NVARCHAR(255), @when DATETIME, @code NVARCHAR(50), @pos INT, @made INT = 0;

DECLARE oss_cur CURSOR LOCAL FAST_FORWARD FOR
    SELECT lr.id, lr.file_number, COALESCE(lr.rofo_generated_at, lr.created_at)
    FROM land_recommendations lr
    WHERE UPPER(ISNULL(lr.type, '')) = 'OSS'
      AND NULLIF(LTRIM(RTRIM(ISNULL(lr.land_rofo_serial_no, ''))), '') IS NOT NULL
      AND NOT EXISTS (
            SELECT 1 FROM security_codes sc
            WHERE sc.document_id   = lr.id
              AND sc.document_type = 'Land ROFO'
      );

OPEN oss_cur;
FETCH NEXT FROM oss_cur INTO @id, @file, @when;

WHILE @@FETCH_STATUS = 0
BEGIN
    -- Retry until the generated code is unique.
    SET @code = NULL;
    WHILE @code IS NULL OR EXISTS (SELECT 1 FROM security_codes WHERE code = @code)
    BEGIN
        SET @pos  = ABS(CHECKSUM(NEWID())) % 9;              -- 0..8
        SET @code = RIGHT('00000000' + CAST(ABS(CHECKSUM(NEWID())) % 100000000 AS VARCHAR(8)), 8);
        SET @code = STUFF(@code, @pos + 1, 0, CHAR(65 + ABS(CHECKSUM(NEWID())) % 26));
    END

    INSERT INTO security_codes
        (code, is_used, file_number, document_id, document_type,
         security_paper_code, assigned_by, assigned_at, created_at, updated_at)
    SELECT
        @code, 0, @file, @id, 'Land ROFO',
        lr.land_rofo_serial_no, NULL, @when, @when, SYSDATETIME()
    FROM land_recommendations lr WHERE lr.id = @id;

    SET @made += 1;
    FETCH NEXT FROM oss_cur INTO @id, @file, @when;
END

CLOSE oss_cur;
DEALLOCATE oss_cur;
PRINT CONCAT('security_codes rows generated: ', @made);

COMMIT TRANSACTION;      -- or: ROLLBACK TRANSACTION;
*/
GO


/* -----------------------------------------------------------------------------
   PART 4 — VERIFY (read only). Should show 0 remaining and dates on every row.
   -------------------------------------------------------------------------- */

SELECT
    COUNT(*)                                                          AS oss_with_paper_code,
    SUM(CASE WHEN ISNULL(rofo_print_count, 0) = 0 THEN 1 ELSE 0 END)  AS still_not_printed
FROM land_recommendations
WHERE UPPER(ISNULL(type, '')) = 'OSS'
  AND NULLIF(LTRIM(RTRIM(ISNULL(land_rofo_serial_no, ''))), '') IS NOT NULL;

SELECT TOP 20
    lr.id, lr.file_number, lr.land_rofo_serial_no AS paper_code,
    lr.rofo_print_count,
    p.last_printed AS print_date_shown,
    sc.code        AS security_serial
FROM land_recommendations lr
LEFT JOIN (
        SELECT UPPER(LTRIM(RTRIM(reference_number))) AS fn, MAX(created_at) AS last_printed
        FROM print_logs
        WHERE document_type = 'Land ROFO'
        GROUP BY UPPER(LTRIM(RTRIM(reference_number)))
    ) p ON p.fn = UPPER(LTRIM(RTRIM(lr.file_number)))
LEFT JOIN security_codes sc
       ON sc.document_id = lr.id AND sc.document_type = 'Land ROFO' AND sc.is_used = 0
WHERE UPPER(ISNULL(lr.type, '')) = 'OSS'
  AND NULLIF(LTRIM(RTRIM(ISNULL(lr.land_rofo_serial_no, ''))), '') IS NOT NULL
ORDER BY lr.id DESC;
GO
