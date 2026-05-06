/* ============================================================================
   Diagnose: RES-2026-2038..2042 / TEMP-00704..00708 / prop_id 57160..57164
   Run on PRODUCTION.  All read-only.

   IMPORTANT
   - Some columns in pra (e.g. prop_id, instrument_type) are stored as
     nvarchar but contain bad rows like the literal string 'Instrument Type'.
   - We therefore use STRING literals in every WHERE..IN (...) so SQL Server
     compares as nvarchar instead of trying to convert every row to int.
   - We also CAST the displayed columns to nvarchar for safety.
   - PIC is NOT queried (we are not using it).
============================================================================ */

USE klas;
SET NOCOUNT ON;

-- ---------------------------------------------------------------------------
-- 0) Schema sanity: actual data types of the relevant columns
-- ---------------------------------------------------------------------------
SELECT t.name AS table_name, c.name AS column_name,
       ty.name AS data_type, c.max_length, c.is_nullable
FROM   sys.columns c
JOIN   sys.tables  t ON t.object_id = c.object_id
JOIN   sys.types   ty ON ty.user_type_id = c.user_type_id
WHERE  t.name IN ('pra','instrument_capture')
  AND  c.name IN ('id','instrument_type','op_serial_number','party_1','party_2',
                  'party_1_name','party_2_name','fileno','mlsFNo',
                  'temp_fileno','prop_id','instrument_capture_id')
ORDER  BY t.name, c.name;

-- ---------------------------------------------------------------------------
-- 1) The 10 IC rows you listed
-- ---------------------------------------------------------------------------
SELECT 'IC' AS src,
       id,
       CAST(temp_fileno       AS nvarchar(50))  AS temp_fileno,
       CAST(mlsFNo            AS nvarchar(100)) AS mlsFNo,
       CAST(prop_id           AS nvarchar(50))  AS prop_id,
       CAST(op_serial_number  AS nvarchar(50))  AS op_serial_number,
       CAST(instrument_type   AS nvarchar(200)) AS instrument_type,
       CAST(party_1_name      AS nvarchar(500)) AS party_1_name,
       CAST(party_2_name      AS nvarchar(500)) AS party_2_name,
       created_at
FROM   instrument_capture
WHERE  id IN (711,2866,712,3988,713,5337,714,1751,715,3767)
ORDER  BY temp_fileno, id;

-- ---------------------------------------------------------------------------
-- 2) PRA by file number (this is where RES-2026-* lives)
-- ---------------------------------------------------------------------------
SELECT 'PRA-by-file' AS src,
       id,
       CAST(temp_fileno       AS nvarchar(50))  AS temp_fileno,
       CAST(fileno            AS nvarchar(100)) AS fileno,
       CAST(mlsFNo            AS nvarchar(100)) AS mlsFNo,
       CAST(prop_id           AS nvarchar(50))  AS prop_id,
       CAST(op_serial_number  AS nvarchar(50))  AS op_serial_number,
       CAST(instrument_type   AS nvarchar(200)) AS instrument_type,
       CAST(instrument_capture_id AS nvarchar(50)) AS instrument_capture_id,
       CAST(party_1           AS nvarchar(500)) AS party_1,
       CAST(party_2           AS nvarchar(500)) AS party_2,
       created_at
FROM   pra
WHERE  fileno IN ('RES-2026-2038','RES-2026-2039','RES-2026-2040',
                  'RES-2026-2041','RES-2026-2042')
   OR  mlsFNo IN ('RES-2026-2038','RES-2026-2039','RES-2026-2040',
                  'RES-2026-2041','RES-2026-2042')
ORDER  BY fileno, id;

-- ---------------------------------------------------------------------------
-- 3) PRA by prop_id (string literals — avoids implicit int conversion)
-- ---------------------------------------------------------------------------
SELECT 'PRA-by-prop' AS src,
       id,
       CAST(temp_fileno       AS nvarchar(50))  AS temp_fileno,
       CAST(fileno            AS nvarchar(100)) AS fileno,
       CAST(mlsFNo            AS nvarchar(100)) AS mlsFNo,
       CAST(prop_id           AS nvarchar(50))  AS prop_id,
       CAST(op_serial_number  AS nvarchar(50))  AS op_serial_number,
       CAST(instrument_type   AS nvarchar(200)) AS instrument_type,
       CAST(instrument_capture_id AS nvarchar(50)) AS instrument_capture_id,
       CAST(party_1           AS nvarchar(500)) AS party_1,
       CAST(party_2           AS nvarchar(500)) AS party_2,
       created_at
FROM   pra
WHERE  CAST(prop_id AS nvarchar(50))
       IN ('57160','57161','57162','57163','57164')
ORDER  BY prop_id, id;

-- ---------------------------------------------------------------------------
-- 4) PRA by the duplicated temp_filenos
-- ---------------------------------------------------------------------------
SELECT 'PRA-by-temp' AS src,
       id,
       CAST(temp_fileno       AS nvarchar(50))  AS temp_fileno,
       CAST(fileno            AS nvarchar(100)) AS fileno,
       CAST(mlsFNo            AS nvarchar(100)) AS mlsFNo,
       CAST(prop_id           AS nvarchar(50))  AS prop_id,
       CAST(op_serial_number  AS nvarchar(50))  AS op_serial_number,
       CAST(instrument_type   AS nvarchar(200)) AS instrument_type,
       CAST(instrument_capture_id AS nvarchar(50)) AS instrument_capture_id,
       CAST(party_1           AS nvarchar(500)) AS party_1,
       CAST(party_2           AS nvarchar(500)) AS party_2,
       created_at
FROM   pra
WHERE  temp_fileno IN ('TEMP-00704','TEMP-00705','TEMP-00706',
                       'TEMP-00707','TEMP-00708')
ORDER  BY temp_fileno, id;
