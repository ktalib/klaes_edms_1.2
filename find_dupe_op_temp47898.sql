/* Find all rows tied to TEMP-47898 / RES-2026-2540 to identify the duplicate OP. */

SELECT 'pra' AS src, id, prop_id, parent_prop_id, temp_fileno, fileno, mlsFNo,
       transaction_type, party_1, party_2, plot_no, op_serial_number,
       created_at, updated_at
FROM   pra
WHERE  temp_fileno = 'TEMP-47898' OR fileno = 'RES-2026-2540' OR mlsFNo = 'RES-2026-2540'
ORDER  BY id;

SELECT 'instrument_capture' AS src, id, prop_id, temp_fileno,
       instrument_type, party_1_name, party_2_name, plot_number, op_serial_number,
       created_at, updated_at
FROM   instrument_capture
WHERE  temp_fileno = 'TEMP-47898'
ORDER  BY id;
