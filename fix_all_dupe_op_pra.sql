/*
  BULK FIX: remove all duplicate OP rows in pra (PROD).

  Deletes pra rows that are OP duplicates of a genuine instrument_capture OP:
  same prop_id + temp_fileno, transaction_type = OP, TEMP- file number, created
  2026-06-23. The JOIN to instrument_capture guarantees we only delete a pra OP
  that ALSO exists properly in IC -- a pra-only OP would never match and is left
  untouched. Transfer-of-Title rows are excluded by transaction_type.

  Run AFTER reviewing find_all_dupe_op_pra.sql. Run on the 'klas' PROD database.
*/

BEGIN TRANSACTION;

-- BEFORE: list exactly what will go.
SELECT  p.id AS pra_op_id, p.prop_id, p.temp_fileno, p.fileno, p.party_2, p.created_at
FROM    pra p
JOIN    instrument_capture ic
        ON  ic.temp_fileno = p.temp_fileno
        AND ic.prop_id     = p.prop_id
        AND ic.instrument_type = 'Occupancy Permit (OP)'
WHERE   p.transaction_type = 'Occupancy Permit (OP)'
  AND   p.fileno LIKE 'TEMP-%'
  AND   CAST(p.created_at AS DATE) = '2026-06-23'
ORDER  BY p.id;

DELETE  p
FROM    pra p
JOIN    instrument_capture ic
        ON  ic.temp_fileno = p.temp_fileno
        AND ic.prop_id     = p.prop_id
        AND ic.instrument_type = 'Occupancy Permit (OP)'
WHERE   p.transaction_type = 'Occupancy Permit (OP)'
  AND   p.fileno LIKE 'TEMP-%'
  AND   CAST(p.created_at AS DATE) = '2026-06-23';

PRINT 'Rows deleted: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

-- AFTER: should return 0 rows.
SELECT  p.id AS remaining_pra_op_id
FROM    pra p
JOIN    instrument_capture ic
        ON  ic.temp_fileno = p.temp_fileno
        AND ic.prop_id     = p.prop_id
        AND ic.instrument_type = 'Occupancy Permit (OP)'
WHERE   p.transaction_type = 'Occupancy Permit (OP)'
  AND   p.fileno LIKE 'TEMP-%'
  AND   CAST(p.created_at AS DATE) = '2026-06-23';

-- Review the output above, then run:  COMMIT;   (or ROLLBACK; to undo)
