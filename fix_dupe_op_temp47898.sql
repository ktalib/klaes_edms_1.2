/*
  Remove the duplicate OP row for RES-2026-2540 / TEMP-47898 (PROD).
  Genuine OP: instrument_capture id=17339 (2026-05-19).
  Duplicate OP in pra: id=170060 (2026-06-23, fileno=TEMP-47898).
  ToT pra id=170071 + IC OP both share prop_id=100397. Run on 'klas' PROD.
*/

-- BEFORE
SELECT 'pra' AS src, id, prop_id, temp_fileno, fileno, transaction_type, party_1, party_2
FROM   pra WHERE prop_id = 100397 OR temp_fileno = 'TEMP-47898';

DELETE FROM pra
WHERE  id = 170060
  AND  temp_fileno = 'TEMP-47898'
  AND  transaction_type = 'Occupancy Permit (OP)';   -- guards: only the dup OP

PRINT 'Rows deleted: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

-- AFTER
SELECT 'pra' AS src, id, prop_id, temp_fileno, fileno, transaction_type, party_1, party_2
FROM   pra WHERE prop_id = 100397 OR temp_fileno = 'TEMP-47898';
