-- Check TEMP-18237 and TEMP-18118 across pra, instrument_capture, PropID_Master

-- pra rows
SELECT 'PRA' AS tbl, id, prop_id, instrument_type, Grantor, Grantee, temp_fileno, fileno, system_source
FROM dbo.pra
WHERE temp_fileno IN ('TEMP-18237', 'TEMP-18118')
ORDER BY temp_fileno, id;

-- instrument_capture rows
SELECT 'IC' AS tbl, id, prop_id, instrument_type, party_1_name, party_2_name, temp_fileno, op_type
FROM dbo.instrument_capture
WHERE temp_fileno IN ('TEMP-18237', 'TEMP-18118')
ORDER BY temp_fileno, id;

-- PropID_Master rows
SELECT 'DR' AS tbl, prop_id, primary_file_number, mlsFNo, temp_fileno, status, created_at
FROM dbo.PropID_Master
WHERE temp_fileno IN ('TEMP-18237', 'TEMP-18118')
   OR primary_file_number IN ('TEMP-18237', 'TEMP-18118')
ORDER BY temp_fileno;

-- mls_file_no rows (commissioning)
SELECT 'MLS' AS tbl, id, full_file_number, source_instrument_capture_id, source_pra_id, sub_source, source
FROM dbo.mls_file_no
WHERE full_file_number IN ('TEMP-18237', 'TEMP-18118')
   OR source_instrument_capture_id IN (
       SELECT id FROM dbo.instrument_capture WHERE temp_fileno IN ('TEMP-18237', 'TEMP-18118')
   )
ORDER BY full_file_number;
