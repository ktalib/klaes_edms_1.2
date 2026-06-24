/*
  Remove the duplicate OP row for COM-2026-240 / TEMP-47235 (PROD).

  The genuine OP lives in instrument_capture (id=17266, captured 2026-05-19).
  A duplicate OP was written into pra (id=169767, created 2026-06-23) with no
  real file number (fileno=TEMP-47235). OP rows should not live in pra, so we
  delete that duplicate. The ToT (pra id=169774) and the IC OP both share
  prop_id=100326, so the property grouping is unaffected.

  Run on the 'klas' PROD database.
*/

-- BEFORE: all OP/ToT rows for this property.
SELECT 'pra' AS src, id, prop_id, temp_fileno, fileno, transaction_type, party_1, party_2
FROM   pra WHERE prop_id = 100326 OR temp_fileno = 'TEMP-47235';

DELETE FROM pra
WHERE  id = 169767
  AND  temp_fileno = 'TEMP-47235'
  AND  transaction_type = 'Occupancy Permit (OP)';   -- guards: only the dup OP

PRINT 'Rows deleted: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

-- AFTER
SELECT 'pra' AS src, id, prop_id, temp_fileno, fileno, transaction_type, party_1, party_2
FROM   pra WHERE prop_id = 100326 OR temp_fileno = 'TEMP-47235';
