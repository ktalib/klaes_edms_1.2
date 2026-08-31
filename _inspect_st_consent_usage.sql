-- ============================================================================
-- Why is an ST file's memo consent still selectable on the capture form?
--
-- Set @file to the unit ST file number and run the whole script. Each block is
-- deliberately UNFILTERED on instrument_type / status, so a near miss shows up
-- instead of vanishing the way it does inside the application's own lookups.
--
--   The memo is spent by the FIRST registration off the approval. The question
--   these queries answer is: which row is that, which table is it in, and what
--   exactly is its instrument_type and status?
-- ============================================================================

DECLARE @file VARCHAR(100) = 'ST-RES-2025-2-008';   -- <<< change this

-- 1. The unit itself, and how it reaches its mother application ---------------
SELECT '1. st_file_numbers' AS block,
       id, fileno, mls_fileno, np_fileno, file_no_type,
       mother_application_id, subapplication_id, buyer_list_id
FROM   st_file_numbers
WHERE  fileno = @file OR mls_fileno = @file;

-- 2. The mother application + the memo that becomes the "ST Assignment" consent
SELECT '2. mother + memo' AS block,
       ma.id AS mother_id, ma.fileno AS mother_fileno, ma.np_fileno,
       ma.application_status,
       m.id AS memo_id, m.memo_no, m.memo_type, m.memo_status, m.created_at
FROM   mother_applications ma
LEFT   JOIN memos m ON m.application_id = ma.id
WHERE  ma.id IN (
           SELECT mother_application_id FROM st_file_numbers
           WHERE (fileno = @file OR mls_fileno = @file) AND mother_application_id IS NOT NULL
           UNION
           SELECT s.main_application_id FROM st_file_numbers sfn
           JOIN subapplications s ON s.id = sfn.subapplication_id
           WHERE  sfn.fileno = @file OR sfn.mls_fileno = @file
           UNION
           SELECT b.application_id FROM st_file_numbers sfn
           JOIN buyer_list b ON b.id = sfn.buyer_list_id
           WHERE  sfn.fileno = @file OR sfn.mls_fileno = @file
       );

-- 3. deed_registrations — the table the ST tab READS -------------------------
--    (no instrument_type / status filter: show everything on this file)
SELECT '3. deed_registrations' AS block,
       id, fileno, parent_fileno, instrument_type, status,
       registration_number, is_deleted, grantor, grantee, instrument_date, created_at
FROM   deed_registrations
WHERE  fileno = @file OR parent_fileno = @file
ORDER  BY created_at DESC;

-- 4. registered_instruments — the table the registration flow WRITES ---------
SELECT '4. registered_instruments' AS block,
       id, StFileNo, fileno, MLSFileNo, parent_fileNo, instrument_type, status,
       particularsRegistrationNumber, Grantor, Grantee, instrumentDate, created_at
FROM   registered_instruments
WHERE  StFileNo = @file OR fileno = @file OR MLSFileNo = @file OR parent_fileNo = @file
ORDER  BY created_at DESC;

-- 5. pra — transaction history (this is where the CofO / RofO lands) ---------
SELECT '5. pra' AS block,
       id, fileno, mlsFNo, temp_fileno, instrument_type,
       party_1, party_2, regNo, serialNo, pageNo, volumeNo,
       reg_date, is_deleted, created_at
FROM   pra
WHERE  fileno = @file OR mlsFNo = @file OR temp_fileno = @file
ORDER  BY created_at DESC;

-- 6. instrument_capture — anything captured on this file ---------------------
SELECT '6. instrument_capture' AS block,
       id, mlsFNo, temp_fileno, kangisFileNo, NewKANGISFileno,
       instrument_type, party_1_name, party_2_name,
       consent_application_id, is_deleted, created_at
FROM   instrument_capture
WHERE  mlsFNo = @file OR temp_fileno = @file
       OR kangisFileNo = @file OR NewKANGISFileno = @file
ORDER  BY created_at DESC;

-- 7. The real consent applications on this file ------------------------------
SELECT '7. consent_applications' AS block,
       id, application_tracking_no, file_number, consent_type,
       applicant_name, party_name, print_count, status, created_at
FROM   consent_applications
WHERE  file_number = @file
ORDER  BY created_at DESC;

-- ============================================================================
-- WHAT TO LOOK FOR
--
-- Block 3/4/5 hold the candidate "first registration". For each row, note its
-- instrument_type and status EXACTLY as spelled.
--
-- Currently only these spend the memo (in blocks 3 and 4, status='registered'):
--     ST Assignment (Transfer of Title)
--     ST Assignment
--     ST Fragmentation
--
-- On ST-RES-2025-2-008 the only row found so far is in block 5:
--     pra | Right of Occupancy (ST) | KANO STATE GOVERNMENT -> MURTALA JEGA
-- i.e. the sectional CofO issued to the unit holder, which is a primary-owner
-- retention rather than a transfer to a third party. If that CofO IS the first
-- registration off the approval for this unit, then "Right of Occupancy (ST)"
-- in pra has to count as spending the memo too.
-- ============================================================================
