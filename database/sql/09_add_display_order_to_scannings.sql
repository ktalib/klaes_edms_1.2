/*
    Script: 09_add_display_order_to_scannings.sql
    Purpose: Mirrors Laravel migration 2025_09_28_121500_add_display_order_to_scannings_table.php
             by adding the display_order column to the scannings table and
             backfilling values based on capture order per file_indexing_id.
    Notes:
        - Designed for SQL Server (sqlsrv connection).
        - Safe to run multiple times.
*/

IF COL_LENGTH('dbo.scannings', 'display_order') IS NULL
BEGIN
    PRINT 'Adding display_order column to dbo.scannings...';

    ALTER TABLE dbo.scannings
    ADD display_order INT NOT NULL CONSTRAINT DF_scannings_display_order DEFAULT (0);

    PRINT 'Column added. Populating display_order values...';

    DECLARE @updateSql NVARCHAR(MAX) = N'
        WITH OrderedScans AS (
            SELECT
                id,
                ROW_NUMBER() OVER (PARTITION BY file_indexing_id ORDER BY created_at, id) AS rn
            FROM dbo.scannings
        )
        UPDATE s
            SET display_order = o.rn
        FROM dbo.scannings AS s
        INNER JOIN OrderedScans AS o ON o.id = s.id;';

    EXEC sp_executesql @updateSql;

    PRINT 'display_order values populated successfully.';
END
ELSE
BEGIN
    PRINT 'display_order column already exists. Backfilling values to ensure consistency...';

    DECLARE @refreshSql NVARCHAR(MAX) = N'
        WITH OrderedScans AS (
            SELECT
                id,
                ROW_NUMBER() OVER (PARTITION BY file_indexing_id ORDER BY created_at, id) AS rn
            FROM dbo.scannings
        )
        UPDATE s
            SET display_order = o.rn
        FROM dbo.scannings AS s
        INNER JOIN OrderedScans AS o ON o.id = s.id;';

    EXEC sp_executesql @refreshSql;

    PRINT 'display_order values refreshed successfully.';
END;
GO

/* Optional rollback helper: drop column if required */
-- IF COL_LENGTH('dbo.scannings', 'display_order') IS NOT NULL
-- BEGIN
--     ALTER TABLE dbo.scannings DROP CONSTRAINT DF_scannings_display_order;
--     ALTER TABLE dbo.scannings DROP COLUMN display_order;
--     PRINT 'display_order column removed from dbo.scannings.';
-- END;
-- GO
