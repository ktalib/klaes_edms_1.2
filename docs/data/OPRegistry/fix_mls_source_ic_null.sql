-- ============================================================
-- Fix mls_file_no: null out source_instrument_capture_id
-- for the 17 wrong IC ids — that column is what the UI uses
-- to render the extra wrong OP card in the modal
-- ============================================================

BEGIN TRANSACTION;

-- Preview
SELECT id, full_file_number, source_instrument_capture_id, source_pra_id
FROM dbo.mls_file_no
WHERE source_pra_id IN (1250,1831,2147,2244,2250,2808,3070,3079,
                                        3827,3931,4058,4131,4284,4362,4522,4841,4929);

-- Fix
UPDATE dbo.mls_file_no
SET sub_source = NULL
WHERE  id  ='13136'
-- Verify: should return 0 rows
SELECT id, full_file_number, source_instrument_capture_id
FROM dbo.mls_file_no
WHERE source_pra_id IN (1250,1831,2147,2244,2250,2808,3070,3079,
                                        3827,3931,4058,4131,4284,4362,4522,4841,4929);

-- ROLLBACK TRANSACTION;
COMMIT TRANSACTION;
