/*
  Remove the duplicate OP row for RES-2026-2541 / TEMP-47907 (PROD).

  Genuine OP: instrument_capture id=17340 (captured 2026-05-19).
  Duplicate OP in pra: id=170081 (created 2026-06-23, fileno=TEMP-47907).
  ToT pra id=170087 and the IC OP both share prop_id=100398, so grouping is
  unaffected.  Run on the 'klas' PROD database.
*/

-- BEFORE
SELECT 'pra' AS src, id, prop_id, temp_fileno, fileno, transaction_type, party_1, party_2
FROM   pra WHERE prop_id = 100398 OR temp_fileno = 'TEMP-47907';

DELETE FROM pra
WHERE  id = 170081
  AND  temp_fileno = 'TEMP-47907'
  AND  transaction_type = 'Occupancy Permit (OP)';   -- guards: only the dup OP

PRINT 'Rows deleted: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

-- AFTER
SELECT 'pra' AS src, id, prop_id, temp_fileno, fileno, transaction_type, party_1, party_2
FROM   pra WHERE prop_id = 100398 OR temp_fileno = 'TEMP-47907';
