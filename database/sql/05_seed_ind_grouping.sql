/*
    Seed 10,000 IND (Industrial) awaiting file numbers into the [grouping] table.

    Produces IND-2026-1 .. IND-2026-10000  => 10,000 rows (single year).
    [number] is a continuous counter continuing after the current MAX(number);
    group / sys_batch_no / registry_batch_no follow the 100-file block convention.

    Mirrors 04_seed_cmd_com_grouping.sql. Safe to re-run: aborts if IND rows exist.
    Runs as a single T-SQL batch (no GO) so it can be executed via unprepared().
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Prefix     varchar(20)  = 'IND';
DECLARE @Landuse    varchar(150) = 'INDUSTRIAL';
DECLARE @Registry   varchar(100) = 'Lands Registry';
DECLARE @Year       int = 2026;
DECLARE @Count      int = 10000;
DECLARE @GroupSize  int = 100;

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
-- 1. Build the serial grid (1..@Count) and running [number] continuing
--    after whatever is already in the table.
------------------------------------------------------------------------
DECLARE @BaseNumber int = ISNULL((SELECT MAX(TRY_CONVERT(int, number)) FROM dbo.grouping), 0);

;WITH n(i) AS (
    SELECT TOP (@Count) ROW_NUMBER() OVER (ORDER BY (SELECT NULL))
    FROM sys.all_objects a CROSS JOIN sys.all_objects b
)
SELECT
    n.i                          AS serial,
    @BaseNumber + n.i            AS [number],
    CONVERT(varchar(150), NULL)  AS tracking_id
INTO #ind
FROM n;

CREATE UNIQUE CLUSTERED INDEX IX_ind ON #ind (serial);

------------------------------------------------------------------------
-- 2. Unique tracking IDs: TRK-{8 hex}-{5 hex}, regenerate any collisions
--    with each other or with existing grouping rows.
------------------------------------------------------------------------
UPDATE #ind
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
        SELECT c.serial
        FROM #ind c
        WHERE EXISTS (SELECT 1 FROM dbo.grouping g WHERE g.tracking_id = c.tracking_id)
           OR EXISTS (SELECT 1 FROM #ind d WHERE d.tracking_id = c.tracking_id AND d.serial < c.serial)
    )
    UPDATE c
    SET tracking_id = 'TRK-'
        + LEFT(REPLACE(CONVERT(varchar(36), NEWID()), '-', ''), 8) + '-'
        + LEFT(REPLACE(CONVERT(varchar(36), NEWID()), '-', ''), 5)
    FROM #ind c JOIN bad ON bad.serial = c.serial;

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
    @Prefix + '-' + CONVERT(varchar(4), @Year) + '-' + CONVERT(varchar(10), c.serial),
    CONVERT(varchar(100), c.[number]),
    @Year,
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
FROM #ind c
ORDER BY c.serial;

DECLARE @inserted int = @@ROWCOUNT;

IF @inserted <> @Count
BEGIN
    ROLLBACK TRANSACTION;
    RAISERROR('Aborted: inserted %d rows, expected %d. Nothing committed.', 16, 1, @inserted, @Count);
    RETURN;
END

COMMIT TRANSACTION;

PRINT 'Inserted ' + CONVERT(varchar(10), @inserted) + ' ' + @Prefix + ' rows.';

DROP TABLE #ind;
