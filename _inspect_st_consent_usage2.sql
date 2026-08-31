-- ============================================================================
-- ROUND 2: find the ST Assignment registration for ST-RES-2025-2-008.
--
-- Round 1 proved there is no ST Assignment row keyed to the UNIT file number.
-- The Right of Occupancy (ST) row is timeline only and does not count.
-- So the deeds registration is either keyed to something else (the mother file
-- number, the batch np_fileno, the buyer), or it was never created.
--
-- Run the whole script. Block 3 is the important one.
-- ============================================================================

DECLARE @file   VARCHAR(100) = 'ST-RES-2025-2-008';   -- the unit
DECLARE @mother VARCHAR(100) = 'RES-RC-1988-40';      -- from round 1, block 2
DECLARE @batch  VARCHAR(100) = 'ST-RES-2025-2';       -- np_fileno, from round 1
DECLARE @name   VARCHAR(100) = '%MURTALA%';           -- the party on this unit

-- 1. Registrations keyed to the MOTHER or the BATCH, not the unit -------------
SELECT '1. deed_registrations (mother/batch)' AS block,
       id, fileno, parent_fileno, instrument_type, status,
       registration_number, grantor, grantee, instrument_date, created_at
FROM   deed_registrations
WHERE  fileno IN (@mother, @batch) OR parent_fileno IN (@mother, @batch)
ORDER  BY created_at DESC;

SELECT '2. registered_instruments (mother/batch)' AS block,
       id, StFileNo, fileno, MLSFileNo, parent_fileNo, instrument_type, status,
       particularsRegistrationNumber, Grantor, Grantee, created_at
FROM   registered_instruments
WHERE  StFileNo IN (@mother, @batch) OR fileno IN (@mother, @batch)
       OR MLSFileNo IN (@mother, @batch) OR parent_fileNo IN (@mother, @batch)
ORDER  BY created_at DESC;

-- 3. *** EVERY unit in this batch, and whether it has a deeds registration ***
--    If sibling units ARE registered, their fileno shows the key actually used.
--    If none are, then this batch was never registered as deeds at all.
SELECT '3. all units in batch' AS block,
       sfn.fileno            AS unit_fileno,
       sfn.file_no_type,
       sfn.buyer_list_id,
       sfn.subapplication_id,
       bl.buyer_name,
       dr.id                 AS deed_id,
       dr.instrument_type    AS deed_type,
       dr.status             AS deed_status,
       dr.created_at         AS deed_created,
       ri.id                 AS legacy_id,
       ri.instrument_type    AS legacy_type,
       ri.status             AS legacy_status
FROM   st_file_numbers sfn
LEFT   JOIN buyer_list bl          ON bl.id     = sfn.buyer_list_id
LEFT   JOIN deed_registrations dr  ON dr.fileno = sfn.fileno
LEFT   JOIN registered_instruments ri ON ri.StFileNo = sfn.fileno OR ri.fileno = sfn.fileno
WHERE  sfn.np_fileno = @batch OR sfn.fileno LIKE @batch + '%'
ORDER  BY sfn.fileno;

-- 4. Anything registered to this party, under ANY file number -----------------
SELECT '4. deed_registrations by party' AS block,
       id, fileno, parent_fileno, instrument_type, status,
       registration_number, grantor, grantee, created_at
FROM   deed_registrations
WHERE  grantor LIKE @name OR grantee LIKE @name
ORDER  BY created_at DESC;

SELECT '5. registered_instruments by party' AS block,
       id, StFileNo, fileno, instrument_type, status,
       particularsRegistrationNumber, Grantor, Grantee, created_at
FROM   registered_instruments
WHERE  Grantor LIKE @name OR Grantee LIKE @name
ORDER  BY created_at DESC;

-- 6. Every ST-typed row in pra for this party (timeline context only) ---------
SELECT '6. pra by party' AS block,
       id, fileno, mlsFNo, instrument_type, party_1, party_2,
       regNo, reg_date, created_at
FROM   pra
WHERE  (party_1 LIKE @name OR party_2 LIKE @name)
       AND (instrument_type LIKE 'ST %' OR instrument_type LIKE '%(ST)%'
            OR instrument_type LIKE '%Sectional%')
ORDER  BY created_at DESC;

-- ============================================================================
-- READING BLOCK 3
--
--   deed_type / legacy_type filled in on OTHER units  -> the registration key
--       is the unit fileno after all, and unit -008 simply was not registered.
--   deed_type NULL on every unit                      -> this whole batch has
--       no deeds registration; the memo was never spent by an ST Assignment,
--       and "already used" means something else that is not stored as a deed.
--   Rows appearing in block 1/2/4/5 under a different fileno -> that fileno is
--       the key the usage lookup has to match on.
-- ============================================================================
