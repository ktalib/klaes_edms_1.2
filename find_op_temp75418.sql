/* Step A: show deed_registrations columns so we use the right file-number field. */
SELECT COLUMN_NAME
FROM   INFORMATION_SCHEMA.COLUMNS
WHERE  TABLE_NAME = 'deed_registrations'
ORDER  BY ORDINAL_POSITION;

/* Step B: pra + instrument_capture lookups (these tables are confirmed). */
SELECT 'pra' AS src, id, prop_id, temp_fileno, transaction_type,
       party_1 AS grantor, party_2 AS allottee
FROM   pra
WHERE  temp_fileno = 'TEMP-75418' OR mlsFNo = 'RES-2024-1906' OR fileno = 'RES-2024-1906';

SELECT 'instrument_capture' AS src, id, prop_id, temp_fileno, instrument_type,
       party_1_name AS grantor, party_2_name AS allottee
FROM   instrument_capture
WHERE  temp_fileno = 'TEMP-75418';
