-- ========================================================
-- Reseed Temp File Number Sequence
-- ========================================================
-- This script calculates the maximum existing 'TEMP-XXXXX' number
-- across all relevant tables and reseeds the 'temp_fileno_sequence'
-- table to ensure the next generated ID is unique.

BEGIN TRANSACTION;

DECLARE @MaxTempId INT = 0;

-- 1. Calculate MAX ID from all tables (pra, pic, instrument_capture)
SELECT @MaxTempId = MAX(Val)
FROM (
    -- Check PRA table
    SELECT CAST(SUBSTRING(temp_fileno, 6, LEN(temp_fileno)) AS INT) AS Val
    FROM pra
    WHERE temp_fileno LIKE 'TEMP-%' 
      AND ISNUMERIC(SUBSTRING(temp_fileno, 6, LEN(temp_fileno))) = 1

    UNION ALL

    -- Check PIC table
    SELECT CAST(SUBSTRING(temp_fileno, 6, LEN(temp_fileno)) AS INT) AS Val
    FROM pic
    WHERE temp_fileno LIKE 'TEMP-%' 
      AND ISNUMERIC(SUBSTRING(temp_fileno, 6, LEN(temp_fileno))) = 1

    UNION ALL

    -- Check Instrument Capture table
    SELECT CAST(SUBSTRING(temp_fileno, 6, LEN(temp_fileno)) AS INT) AS Val
    FROM instrument_capture
    WHERE temp_fileno LIKE 'TEMP-%' 
      AND ISNUMERIC(SUBSTRING(temp_fileno, 6, LEN(temp_fileno))) = 1
) AS AllTemps;

-- 2. Handle case where no records exist (default to 0)
IF @MaxTempId IS NULL 
    SET @MaxTempId = 0;

PRINT 'Found Max Temp ID across all tables: ' + CAST(@MaxTempId AS VARCHAR(20));

-- 3. Reseed the sequence table
-- If Max ID is 1855, this sets the current identity value to 1855.
-- The NEXT insert will generate 1856.
DBCC CHECKIDENT ('temp_fileno_sequence', RESEED, @MaxTempId);

PRINT 'Successfully reseeded temp_fileno_sequence to: ' + CAST(@MaxTempId AS VARCHAR(20));
PRINT 'The next generated temp file number will be: TEMP-' + RIGHT('00000' + CAST(@MaxTempId + 1 AS VARCHAR(10)), 5);

COMMIT TRANSACTION;
