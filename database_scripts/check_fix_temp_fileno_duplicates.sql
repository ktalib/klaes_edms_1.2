/*
  Temp File Number Duplicate Toolkit (SQL Server)
  ------------------------------------------------
  1) Run with @ApplyFix = 0 to audit duplicates.
  2) Review result sets.
  3) Re-run with @ApplyFix = 1 to auto-fix duplicates (keeps first row per table/temp, reassigns others).

  Notes:
  - Targets tables that commonly store OP temp numbers: pra, pic, instrument_capture.
  - Updates temp_fileno and (when equal to old temp) fileno/mlsFNo.
  - Generates replacement values from temp_fileno_sequence.
*/

SET NOCOUNT ON;

DECLARE @ApplyFix BIT = 1;          -- 0 = audit only, 1 = apply auto-fix
DECLARE @SystemUserId INT = 1;      -- created_by for generated temp sequence rows

IF OBJECT_ID('tempdb..#TempRows') IS NOT NULL DROP TABLE #TempRows;
CREATE TABLE #TempRows (
    table_name SYSNAME NOT NULL,
    id BIGINT NOT NULL,
    prop_id NVARCHAR(100) NULL,
    temp_fileno NVARCHAR(100) NOT NULL,
    created_at DATETIME NULL
);

IF OBJECT_ID('dbo.pra', 'U') IS NOT NULL AND COL_LENGTH('dbo.pra', 'temp_fileno') IS NOT NULL
BEGIN
    INSERT INTO #TempRows (table_name, id, prop_id, temp_fileno, created_at)
    SELECT
        'pra',
        CAST(p.id AS BIGINT),
        CAST(p.prop_id AS NVARCHAR(100)),
        UPPER(LTRIM(RTRIM(p.temp_fileno))),
        TRY_CAST(p.created_at AS DATETIME)
    FROM dbo.pra p
    WHERE p.temp_fileno IS NOT NULL
      AND LTRIM(RTRIM(p.temp_fileno)) <> ''
      AND UPPER(LTRIM(RTRIM(p.temp_fileno))) LIKE 'TEMP-%';
END;

IF OBJECT_ID('dbo.pic', 'U') IS NOT NULL AND COL_LENGTH('dbo.pic', 'temp_fileno') IS NOT NULL
BEGIN
    INSERT INTO #TempRows (table_name, id, prop_id, temp_fileno, created_at)
    SELECT
        'pic',
        CAST(p.id AS BIGINT),
        CAST(p.prop_id AS NVARCHAR(100)),
        UPPER(LTRIM(RTRIM(p.temp_fileno))),
        TRY_CAST(p.created_at AS DATETIME)
    FROM dbo.pic p
    WHERE p.temp_fileno IS NOT NULL
      AND LTRIM(RTRIM(p.temp_fileno)) <> ''
      AND UPPER(LTRIM(RTRIM(p.temp_fileno))) LIKE 'TEMP-%';
END;

IF OBJECT_ID('dbo.instrument_capture', 'U') IS NOT NULL AND COL_LENGTH('dbo.instrument_capture', 'temp_fileno') IS NOT NULL
BEGIN
    INSERT INTO #TempRows (table_name, id, prop_id, temp_fileno, created_at)
    SELECT
        'instrument_capture',
        CAST(i.id AS BIGINT),
        CAST(i.prop_id AS NVARCHAR(100)),
        UPPER(LTRIM(RTRIM(i.temp_fileno))),
        TRY_CAST(i.created_at AS DATETIME)
    FROM dbo.instrument_capture i
    WHERE i.temp_fileno IS NOT NULL
      AND LTRIM(RTRIM(i.temp_fileno)) <> ''
      AND UPPER(LTRIM(RTRIM(i.temp_fileno))) LIKE 'TEMP-%';
END;

PRINT '=== DUPLICATES PER TABLE ===';
SELECT
    table_name,
    temp_fileno,
    COUNT(*) AS row_count,
    COUNT(DISTINCT ISNULL(prop_id, '#NULL#')) AS distinct_prop_ids,
    MIN(created_at) AS first_seen,
    MAX(created_at) AS last_seen
FROM #TempRows
GROUP BY table_name, temp_fileno
HAVING COUNT(*) > 1
ORDER BY table_name, temp_fileno;

PRINT '=== DUPLICATES ACROSS ALL TABLES ===';
SELECT
    temp_fileno,
    COUNT(*) AS total_rows,
    COUNT(DISTINCT table_name) AS table_count,
    COUNT(DISTINCT ISNULL(prop_id, '#NULL#')) AS distinct_prop_ids,
    STRING_AGG(table_name + ':' + CAST(id AS VARCHAR(20)), ', ') WITHIN GROUP (ORDER BY table_name, id) AS locations
FROM #TempRows
GROUP BY temp_fileno
HAVING COUNT(*) > 1
ORDER BY temp_fileno;

PRINT '=== DETAIL ROWS FOR DUPLICATES ===';
SELECT r.*
FROM #TempRows r
INNER JOIN (
    SELECT table_name, temp_fileno
    FROM #TempRows
    GROUP BY table_name, temp_fileno
    HAVING COUNT(*) > 1
) d
    ON d.table_name = r.table_name
   AND d.temp_fileno = r.temp_fileno
ORDER BY r.table_name, r.temp_fileno, r.created_at, r.id;

IF @ApplyFix = 0
BEGIN
    PRINT 'Audit mode only. Set @ApplyFix = 1 to apply fix.';
    RETURN;
END;

IF OBJECT_ID('dbo.temp_fileno_sequence', 'U') IS NULL
BEGIN
    RAISERROR('temp_fileno_sequence table was not found. Cannot auto-fix.', 16, 1);
    RETURN;
END;

IF OBJECT_ID('tempdb..#ToFix') IS NOT NULL DROP TABLE #ToFix;
CREATE TABLE #ToFix (
    row_id INT IDENTITY(1,1) PRIMARY KEY,
    table_name SYSNAME NOT NULL,
    id BIGINT NOT NULL,
    old_temp NVARCHAR(100) NOT NULL,
    prop_id NVARCHAR(100) NULL,
    created_at DATETIME NULL
);

;WITH ranked AS (
    SELECT
        table_name,
        id,
        temp_fileno,
        prop_id,
        created_at,
        ROW_NUMBER() OVER (
            PARTITION BY table_name, temp_fileno
            ORDER BY CASE WHEN created_at IS NULL THEN 1 ELSE 0 END, created_at, id
        ) AS rn
    FROM #TempRows
)
INSERT INTO #ToFix (table_name, id, old_temp, prop_id, created_at)
SELECT table_name, id, temp_fileno, prop_id, created_at
FROM ranked
WHERE rn > 1;

IF OBJECT_ID('tempdb..#FixAudit') IS NOT NULL DROP TABLE #FixAudit;
CREATE TABLE #FixAudit (
    table_name SYSNAME,
    id BIGINT,
    prop_id NVARCHAR(100) NULL,
    old_temp NVARCHAR(100),
    new_temp NVARCHAR(100) NULL,
    status NVARCHAR(20) NOT NULL,
    message NVARCHAR(1000) NULL,
    processed_at DATETIME NOT NULL DEFAULT GETDATE()
);

BEGIN TRY
    BEGIN TRANSACTION;

    DECLARE @RowId INT;
    DECLARE @TableName SYSNAME;
    DECLARE @Id BIGINT;
    DECLARE @OldTemp NVARCHAR(100);
    DECLARE @PropId NVARCHAR(100);
    DECLARE @NewSeqId BIGINT;
    DECLARE @NewTemp NVARCHAR(100);
    DECLARE @OldSeqId INT;
    DECLARE @sql NVARCHAR(MAX);

    DECLARE fix_cursor CURSOR LOCAL FAST_FORWARD FOR
        SELECT row_id, table_name, id, old_temp, prop_id
        FROM #ToFix
        ORDER BY row_id;

    OPEN fix_cursor;
    FETCH NEXT FROM fix_cursor INTO @RowId, @TableName, @Id, @OldTemp, @PropId;

    WHILE @@FETCH_STATUS = 0
    BEGIN
        INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at)
        VALUES (@SystemUserId, 1, GETDATE(), GETDATE());

        SET @NewSeqId = SCOPE_IDENTITY();
        SET @NewTemp = 'TEMP-' + RIGHT(REPLICATE('0', 5) + CAST(@NewSeqId AS VARCHAR(20)), 5);

        SET @sql = N'UPDATE t
            SET
                t.temp_fileno = @NewTemp'
            + CASE WHEN COL_LENGTH('dbo.' + @TableName, 'fileno') IS NOT NULL
                THEN N', t.fileno = CASE WHEN UPPER(LTRIM(RTRIM(CAST(t.fileno AS NVARCHAR(100))))) = @OldTemp THEN @NewTemp ELSE t.fileno END'
                ELSE N'' END
            + CASE WHEN COL_LENGTH('dbo.' + @TableName, 'mlsFNo') IS NOT NULL
                THEN N', t.mlsFNo = CASE WHEN UPPER(LTRIM(RTRIM(CAST(t.mlsFNo AS NVARCHAR(100))))) = @OldTemp THEN @NewTemp ELSE t.mlsFNo END'
                ELSE N'' END
            + CASE WHEN COL_LENGTH('dbo.' + @TableName, 'updated_at') IS NOT NULL
                THEN N', t.updated_at = GETDATE()'
                ELSE N'' END
            + N' FROM dbo.' + QUOTENAME(@TableName) + N' t
               WHERE t.id = @Id;';

        EXEC sp_executesql
            @sql,
            N'@Id BIGINT, @OldTemp NVARCHAR(100), @NewTemp NVARCHAR(100)',
            @Id = @Id,
            @OldTemp = @OldTemp,
            @NewTemp = @NewTemp;

        SET @OldSeqId = TRY_CONVERT(INT, REPLACE(@OldTemp, 'TEMP-', ''));
        IF @OldSeqId IS NOT NULL
        BEGIN
            UPDATE dbo.temp_fileno_sequence
            SET is_used = 1,
                updated_at = GETDATE()
            WHERE id = @OldSeqId;
        END;

        INSERT INTO #FixAudit (table_name, id, prop_id, old_temp, new_temp, status, message)
        VALUES (@TableName, @Id, @PropId, @OldTemp, @NewTemp, 'UPDATED', 'Duplicate temp fileno reassigned');

        FETCH NEXT FROM fix_cursor INTO @RowId, @TableName, @Id, @OldTemp, @PropId;
    END;

    CLOSE fix_cursor;
    DEALLOCATE fix_cursor;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF CURSOR_STATUS('local', 'fix_cursor') >= -1
    BEGIN
        CLOSE fix_cursor;
        DEALLOCATE fix_cursor;
    END;

    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;

    INSERT INTO #FixAudit (table_name, id, prop_id, old_temp, new_temp, status, message)
    VALUES ('#SYSTEM', -1, NULL, '', NULL, 'ERROR', ERROR_MESSAGE());

    THROW;
END CATCH;

PRINT '=== FIX AUDIT ===';
SELECT *
FROM #FixAudit
ORDER BY processed_at, table_name, id;

PRINT '=== POST-FIX DUPLICATES (SHOULD BE EMPTY OR REDUCED) ===';
IF OBJECT_ID('tempdb..#PostRows') IS NOT NULL DROP TABLE #PostRows;
CREATE TABLE #PostRows (
        table_name SYSNAME NOT NULL,
        id BIGINT NOT NULL,
        prop_id NVARCHAR(100) NULL,
        temp_fileno NVARCHAR(100) NOT NULL
);

IF OBJECT_ID('dbo.pra', 'U') IS NOT NULL AND COL_LENGTH('dbo.pra', 'temp_fileno') IS NOT NULL
BEGIN
        INSERT INTO #PostRows (table_name, id, prop_id, temp_fileno)
        SELECT 'pra', CAST(id AS BIGINT), CAST(prop_id AS NVARCHAR(100)), UPPER(LTRIM(RTRIM(temp_fileno)))
        FROM dbo.pra
        WHERE temp_fileno IS NOT NULL
            AND LTRIM(RTRIM(temp_fileno)) <> ''
            AND UPPER(LTRIM(RTRIM(temp_fileno))) LIKE 'TEMP-%';
END;

IF OBJECT_ID('dbo.pic', 'U') IS NOT NULL AND COL_LENGTH('dbo.pic', 'temp_fileno') IS NOT NULL
BEGIN
        INSERT INTO #PostRows (table_name, id, prop_id, temp_fileno)
        SELECT 'pic', CAST(id AS BIGINT), CAST(prop_id AS NVARCHAR(100)), UPPER(LTRIM(RTRIM(temp_fileno)))
        FROM dbo.pic
        WHERE temp_fileno IS NOT NULL
            AND LTRIM(RTRIM(temp_fileno)) <> ''
            AND UPPER(LTRIM(RTRIM(temp_fileno))) LIKE 'TEMP-%';
END;

IF OBJECT_ID('dbo.instrument_capture', 'U') IS NOT NULL AND COL_LENGTH('dbo.instrument_capture', 'temp_fileno') IS NOT NULL
BEGIN
        INSERT INTO #PostRows (table_name, id, prop_id, temp_fileno)
        SELECT 'instrument_capture', CAST(id AS BIGINT), CAST(prop_id AS NVARCHAR(100)), UPPER(LTRIM(RTRIM(temp_fileno)))
        FROM dbo.instrument_capture
        WHERE temp_fileno IS NOT NULL
            AND LTRIM(RTRIM(temp_fileno)) <> ''
            AND UPPER(LTRIM(RTRIM(temp_fileno))) LIKE 'TEMP-%';
END;

SELECT
        table_name,
        temp_fileno,
        COUNT(*) AS row_count,
        COUNT(DISTINCT ISNULL(prop_id, '#NULL#')) AS distinct_prop_ids
FROM #PostRows
GROUP BY table_name, temp_fileno
HAVING COUNT(*) > 1
ORDER BY table_name, temp_fileno;



------------------------------------------------------------

-------------------------------------------------------------


BEGIN TRY
    BEGIN TRANSACTION;

    DECLARE @SystemUserId INT = 1;

    IF OBJECT_ID('tempdb..#AllRows') IS NOT NULL DROP TABLE #AllRows;
    CREATE TABLE #AllRows (
        table_name SYSNAME NOT NULL,
        id BIGINT NOT NULL,
        prop_id NVARCHAR(100) NOT NULL,
        temp_fileno NVARCHAR(100) NOT NULL,
        created_at DATETIME NULL
    );

    INSERT INTO #AllRows (table_name, id, prop_id, temp_fileno, created_at)
    SELECT 'pra', CAST(id AS BIGINT), CAST(prop_id AS NVARCHAR(100)),
           UPPER(LTRIM(RTRIM(temp_fileno))), TRY_CAST(created_at AS DATETIME)
    FROM dbo.pra
    WHERE temp_fileno IS NOT NULL
      AND LTRIM(RTRIM(temp_fileno)) <> ''
      AND UPPER(LTRIM(RTRIM(temp_fileno))) LIKE 'TEMP-%'
      AND prop_id IS NOT NULL;

    INSERT INTO #AllRows (table_name, id, prop_id, temp_fileno, created_at)
    SELECT 'pic', CAST(id AS BIGINT), CAST(prop_id AS NVARCHAR(100)),
           UPPER(LTRIM(RTRIM(temp_fileno))), TRY_CAST(created_at AS DATETIME)
    FROM dbo.pic
    WHERE temp_fileno IS NOT NULL
      AND LTRIM(RTRIM(temp_fileno)) <> ''
      AND UPPER(LTRIM(RTRIM(temp_fileno))) LIKE 'TEMP-%'
      AND prop_id IS NOT NULL;

    INSERT INTO #AllRows (table_name, id, prop_id, temp_fileno, created_at)
    SELECT 'instrument_capture', CAST(id AS BIGINT), CAST(prop_id AS NVARCHAR(100)),
           UPPER(LTRIM(RTRIM(temp_fileno))), TRY_CAST(created_at AS DATETIME)
    FROM dbo.instrument_capture
    WHERE temp_fileno IS NOT NULL
      AND LTRIM(RTRIM(temp_fileno)) <> ''
      AND UPPER(LTRIM(RTRIM(temp_fileno))) LIKE 'TEMP-%'
      AND prop_id IS NOT NULL;

    -- TEMP values that are attached to multiple prop_ids
    IF OBJECT_ID('tempdb..#CollisionTemp') IS NOT NULL DROP TABLE #CollisionTemp;
    SELECT temp_fileno
    INTO #CollisionTemp
    FROM #AllRows
    GROUP BY temp_fileno
    HAVING COUNT(DISTINCT prop_id) > 1;

    -- Rank prop_ids per TEMP: keep strongest candidate (most rows, latest activity)
    IF OBJECT_ID('tempdb..#PropRank') IS NOT NULL DROP TABLE #PropRank;
    ;WITH grp AS (
        SELECT
            r.temp_fileno,
            r.prop_id,
            COUNT(*) AS row_count,
            MAX(r.created_at) AS last_seen
        FROM #AllRows r
        INNER JOIN #CollisionTemp c ON c.temp_fileno = r.temp_fileno
        GROUP BY r.temp_fileno, r.prop_id
    )
    SELECT
        g.*,
        ROW_NUMBER() OVER (
            PARTITION BY g.temp_fileno
            ORDER BY g.row_count DESC, g.last_seen DESC, g.prop_id ASC
        ) AS rn
    INTO #PropRank
    FROM grp g;

    -- Losers are prop_ids to be reassigned
    IF OBJECT_ID('tempdb..#Losers') IS NOT NULL DROP TABLE #Losers;
    SELECT temp_fileno AS old_temp, prop_id
    INTO #Losers
    FROM #PropRank
    WHERE rn > 1;

    IF OBJECT_ID('tempdb..#FixMap') IS NOT NULL DROP TABLE #FixMap;
    CREATE TABLE #FixMap (
        old_temp NVARCHAR(100) NOT NULL,
        prop_id NVARCHAR(100) NOT NULL,
        new_temp NVARCHAR(100) NOT NULL
    );

    DECLARE @OldTemp NVARCHAR(100), @PropId NVARCHAR(100), @NewSeq BIGINT, @NewTemp NVARCHAR(100);

    DECLARE cur CURSOR LOCAL FAST_FORWARD FOR
        SELECT old_temp, prop_id FROM #Losers ORDER BY old_temp, prop_id;

    OPEN cur;
    FETCH NEXT FROM cur INTO @OldTemp, @PropId;

    WHILE @@FETCH_STATUS = 0
    BEGIN
        INSERT INTO dbo.temp_fileno_sequence (created_by, is_used, created_at, updated_at)
        VALUES (@SystemUserId, 1, GETDATE(), GETDATE());

        SET @NewSeq = SCOPE_IDENTITY();
        SET @NewTemp = 'TEMP-' + RIGHT(REPLICATE('0', 5) + CAST(@NewSeq AS VARCHAR(20)), 5);

        INSERT INTO #FixMap (old_temp, prop_id, new_temp)
        VALUES (@OldTemp, @PropId, @NewTemp);

        FETCH NEXT FROM cur INTO @OldTemp, @PropId;
    END

    CLOSE cur;
    DEALLOCATE cur;

    -- Apply remap on all tables for that prop_id + old_temp pair
    UPDATE p
    SET
        p.temp_fileno = m.new_temp,
        p.fileno = CASE WHEN UPPER(LTRIM(RTRIM(ISNULL(CAST(p.fileno AS NVARCHAR(100)), '')))) = m.old_temp THEN m.new_temp ELSE p.fileno END,
        p.mlsFNo = CASE WHEN UPPER(LTRIM(RTRIM(ISNULL(CAST(p.mlsFNo AS NVARCHAR(100)), '')))) = m.old_temp THEN m.new_temp ELSE p.mlsFNo END,
        p.updated_at = GETDATE()
    FROM dbo.pra p
    INNER JOIN #FixMap m
      ON UPPER(LTRIM(RTRIM(p.temp_fileno))) = m.old_temp
     AND CAST(p.prop_id AS NVARCHAR(100)) = m.prop_id;

    UPDATE p
    SET
        p.temp_fileno = m.new_temp,
        p.fileno = CASE WHEN UPPER(LTRIM(RTRIM(ISNULL(CAST(p.fileno AS NVARCHAR(100)), '')))) = m.old_temp THEN m.new_temp ELSE p.fileno END,
        p.mlsFNo = CASE WHEN UPPER(LTRIM(RTRIM(ISNULL(CAST(p.mlsFNo AS NVARCHAR(100)), '')))) = m.old_temp THEN m.new_temp ELSE p.mlsFNo END,
        p.updated_at = GETDATE()
    FROM dbo.pic p
    INNER JOIN #FixMap m
      ON UPPER(LTRIM(RTRIM(p.temp_fileno))) = m.old_temp
     AND CAST(p.prop_id AS NVARCHAR(100)) = m.prop_id;

    UPDATE i
    SET
        i.temp_fileno = m.new_temp,
       
        i.mlsFNo = CASE WHEN UPPER(LTRIM(RTRIM(ISNULL(CAST(i.mlsFNo AS NVARCHAR(100)), '')))) = m.old_temp THEN m.new_temp ELSE i.mlsFNo END,
        i.updated_at = GETDATE()
    FROM dbo.instrument_capture i
    INNER JOIN #FixMap m
      ON UPPER(LTRIM(RTRIM(i.temp_fileno))) = m.old_temp
     AND CAST(i.prop_id AS NVARCHAR(100)) = m.prop_id;

    -- Audit
    SELECT * FROM #FixMap ORDER BY old_temp, prop_id;

    -- Verify collisions are gone
    SELECT temp_fileno, COUNT(DISTINCT prop_id) AS distinct_prop_ids
    FROM (
        SELECT UPPER(LTRIM(RTRIM(temp_fileno))) AS temp_fileno, CAST(prop_id AS NVARCHAR(100)) AS prop_id FROM dbo.pra WHERE temp_fileno LIKE 'TEMP-%' AND prop_id IS NOT NULL
        UNION ALL
        SELECT UPPER(LTRIM(RTRIM(temp_fileno))), CAST(prop_id AS NVARCHAR(100)) FROM dbo.pic WHERE temp_fileno LIKE 'TEMP-%' AND prop_id IS NOT NULL
        UNION ALL
        SELECT UPPER(LTRIM(RTRIM(temp_fileno))), CAST(prop_id AS NVARCHAR(100)) FROM dbo.instrument_capture WHERE temp_fileno LIKE 'TEMP-%' AND prop_id IS NOT NULL
    ) x
    GROUP BY temp_fileno
    HAVING COUNT(DISTINCT prop_id) > 1
    ORDER BY temp_fileno;

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;