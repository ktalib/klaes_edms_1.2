/*
    Seed CMD-COM awaiting file numbers into the [grouping] (Lands Registry) table.

    Produces CMD-COM-2018-1 .. CMD-COM-2025-5000  => 8 years x 5,000 = 40,000 rows.
    Serial resets to 1 each year; [number] is a continuous counter across all rows,
    and group / sys_batch_no / registry_batch_no follow the 100-file block convention
    (see App\Console\Commands\AssignGroupingMetadataCommand::GROUP_SIZE).

    Conventions mirrored from the populated sibling tables (sit_grouping, dciv_grouping):
        created_by  = 'Generated'
        mapping     = 0            (unmapped until linked to a real MLS file number)
        tracking_id = TRK-{8 hex}-{5 hex}, matching GroupingFileNumberService

    Safe to re-run: aborts if CMD-COM rows already exist. Runs in a single transaction.

    REVIEW BEFORE RUNNING:
      - @Registry below. Sibling tables store the short code ('SIT', 'DCIV', 'KANGIS').
        [grouping] is empty here, so there is no Lands row to copy. 'Lands Registry' is
        the string the application itself passes for this table (GroupingFileNumberService
        and FileIndexing::detectRegistryFromFileNumber); change to 'LANDS' if the short
        code is what production uses.
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

DECLARE @Prefix     varchar(20)  = 'CMD-COM';
DECLARE @Landuse    varchar(150) = 'CMD-COM';
DECLARE @Registry   varchar(100) = 'Lands Registry';
DECLARE @YearFrom   int = 2018;
DECLARE @YearTo     int = 2025;
DECLARE @PerYear    int = 5000;
DECLARE @GroupSize  int = 100;
DECLARE @Expected   int = (@YearTo - @YearFrom + 1) * @PerYear;

------------------------------------------------------------------------
-- Guard: never double-seed
------------------------------------------------------------------------
IF EXISTS (SELECT 1 FROM dbo.grouping WHERE awaiting_fileno LIKE @Prefix + '-%')
BEGIN
    DECLARE @existing int = (SELECT COUNT(*) FROM dbo.grouping WHERE awaiting_fileno LIKE @Prefix + '-%');
    RAISERROR('Aborted: %d %s rows already exist in [grouping]. Delete them first if you intend to reseed.', 16, 1, @existing, @Prefix);
    RETURN;
END

------------------------------------------------------------------------
-- 1. Build the (year, serial) grid from a tally, and assign the running
--    [number] continuing after whatever is already in the table.
------------------------------------------------------------------------
DECLARE @BaseNumber int = ISNULL((SELECT MAX(TRY_CONVERT(int, number)) FROM dbo.grouping), 0);

;WITH n(i) AS (
    SELECT TOP (@PerYear) ROW_NUMBER() OVER (ORDER BY (SELECT NULL))
    FROM sys.all_objects a CROSS JOIN sys.all_objects b
),
y(yr) AS (
    SELECT TOP (@YearTo - @YearFrom + 1) @YearFrom + ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) - 1
    FROM sys.all_objects
)
SELECT
    y.yr                                        AS [year],
    n.i                                         AS serial,
    @BaseNumber + ROW_NUMBER() OVER (ORDER BY y.yr, n.i) AS [number],
    CONVERT(varchar(150), NULL)                 AS tracking_id
INTO #cmd
FROM y CROSS JOIN n;

CREATE UNIQUE CLUSTERED INDEX IX_cmd ON #cmd ([year], serial);

------------------------------------------------------------------------
-- 2. Tracking IDs: TRK-{8 hex}-{5 hex} from NEWID(). Regenerate any that
--    collide with each other or with existing grouping rows, until clean.
--    (The table has UNIQUE indexes on both awaiting_fileno and tracking_id.)
------------------------------------------------------------------------
UPDATE #cmd
SET tracking_id = 'TRK-'
    + LEFT(REPLACE(CONVERT(varchar(36), NEWID()), '-', ''), 8) + '-'
    + LEFT(REPLACE(CONVERT(varchar(36), NEWID()), '-', ''), 5);

DECLARE @dupes int = 1, @pass int = 0;

WHILE @dupes > 0
BEGIN
    SET @pass += 1;

    IF @pass > 20
    BEGIN
        RAISERROR('Aborted: could not settle unique tracking IDs after 20 passes.', 16, 1);
        RETURN;
    END

    ;WITH bad AS (
        SELECT c.[year], c.serial
        FROM #cmd c
        WHERE EXISTS (SELECT 1 FROM dbo.grouping g WHERE g.tracking_id = c.tracking_id)
           OR EXISTS (SELECT 1 FROM #cmd d
                      WHERE d.tracking_id = c.tracking_id
                        AND (d.[year] < c.[year] OR (d.[year] = c.[year] AND d.serial < c.serial)))
    )
    UPDATE c
    SET tracking_id = 'TRK-'
        + LEFT(REPLACE(CONVERT(varchar(36), NEWID()), '-', ''), 8) + '-'
        + LEFT(REPLACE(CONVERT(varchar(36), NEWID()), '-', ''), 5)
    FROM #cmd c
    JOIN bad ON bad.[year] = c.[year] AND bad.serial = c.serial;

    SET @dupes = @@ROWCOUNT;
    IF @dupes > 0 PRINT 'Pass ' + CONVERT(varchar(10), @pass) + ': regenerated ' + CONVERT(varchar(10), @dupes) + ' colliding tracking ID(s).';
END

------------------------------------------------------------------------
-- 3. Insert
------------------------------------------------------------------------
BEGIN TRANSACTION;

INSERT INTO dbo.grouping
(
    awaiting_fileno, [number], [year], landuse, registry,
    mapping, [group], sys_batch_no, registry_batch_no,
    tracking_id, created_by, created_at, updated_at
)
SELECT
    @Prefix + '-' + CONVERT(varchar(4), c.[year]) + '-' + CONVERT(varchar(10), c.serial),
    CONVERT(varchar(100), c.[number]),
    c.[year],
    @Landuse,
    @Registry,
    '0',
    CONVERT(varchar(150), CEILING(c.[number] / (@GroupSize * 1.0))),
    CONVERT(varchar(100), CEILING(c.[number] / (@GroupSize * 1.0))),
    CONVERT(varchar(100), CEILING(c.[number] / (@GroupSize * 1.0))),
    c.tracking_id,
    'Generated',
    SYSDATETIME(),
    SYSDATETIME()
FROM #cmd c
ORDER BY c.[year], c.serial;

DECLARE @inserted int = @@ROWCOUNT;

IF @inserted <> @Expected
BEGIN
    ROLLBACK TRANSACTION;
    RAISERROR('Aborted: inserted %d rows, expected %d. Nothing committed.', 16, 1, @inserted, @Expected);
    RETURN;
END

COMMIT TRANSACTION;

PRINT 'Inserted ' + CONVERT(varchar(10), @inserted) + ' ' + @Prefix + ' rows.';

------------------------------------------------------------------------
-- 4. Verification
------------------------------------------------------------------------
-- first/last are ordered by the numeric [number], not the string, so that
-- serial 5000 reports after serial 999 rather than before it.
SELECT
    [year],
    COUNT(*)                        AS rows_per_year,
    MIN(TRY_CONVERT(int, [number])) AS first_number,
    MAX(TRY_CONVERT(int, [number])) AS last_number,
    COUNT(DISTINCT tracking_id)     AS distinct_tracking_ids,
    MIN(CASE WHEN TRY_CONVERT(int, [number]) = mn THEN awaiting_fileno END) AS first_fileno,
    MIN(CASE WHEN TRY_CONVERT(int, [number]) = mx THEN awaiting_fileno END) AS last_fileno
FROM (
    SELECT g.*,
           MIN(TRY_CONVERT(int, g.[number])) OVER (PARTITION BY g.[year]) AS mn,
           MAX(TRY_CONVERT(int, g.[number])) OVER (PARTITION BY g.[year]) AS mx
    FROM dbo.grouping g
    WHERE g.landuse = @Landuse
) s
GROUP BY [year]
ORDER BY [year];

SELECT TOP 5 awaiting_fileno, [number], [year], landuse, registry, [group], sys_batch_no, tracking_id, created_by
FROM dbo.grouping
WHERE landuse = @Landuse
ORDER BY TRY_CONVERT(int, [number]);

DROP TABLE #cmd;
GO
