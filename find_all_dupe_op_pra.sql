/*
  REVIEW-ONLY: find every duplicate OP row in pra (PROD).

  Pattern (seen on RES-2024-1906*, COM-2026-240, RES-2026-2541, RES-2026-2540):
  an OP was duplicated into pra on 2026-06-23 with a TEMP- file number, while
  the genuine OP already exists in instrument_capture (same prop_id + temp_fileno).
  These pra OP rows should not exist (OP belongs in instrument_capture / DR).

  Run this FIRST and eyeball the list. Nothing is deleted here.
*/

SELECT  p.id              AS pra_op_id,
        p.prop_id,
        p.temp_fileno,
        p.fileno,
        p.party_2,
        p.op_serial_number,
        p.created_at,
        ic.id             AS ic_op_id,         -- genuine OP in instrument_capture
        ic.created_at     AS ic_created_at
FROM    pra p
JOIN    instrument_capture ic
        ON  ic.temp_fileno = p.temp_fileno
        AND ic.prop_id     = p.prop_id
        AND ic.instrument_type = 'Occupancy Permit (OP)'
WHERE   p.transaction_type = 'Occupancy Permit (OP)'
  AND   p.fileno LIKE 'TEMP-%'
  AND   CAST(p.created_at AS DATE) = '2026-06-23'
ORDER  BY p.created_at, p.id;

-- Count summary
SELECT COUNT(*) AS dupe_pra_op_count
FROM    pra p
JOIN    instrument_capture ic
        ON  ic.temp_fileno = p.temp_fileno
        AND ic.prop_id     = p.prop_id
        AND ic.instrument_type = 'Occupancy Permit (OP)'
WHERE   p.transaction_type = 'Occupancy Permit (OP)'
  AND   p.fileno LIKE 'TEMP-%'
  AND   CAST(p.created_at AS DATE) = '2026-06-23';
