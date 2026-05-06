/* =========================================
    CONFIGURATION
    ========================================= */
DECLARE @StartNo INT = 1;
DECLARE @EndNo   INT = 100000;   -- change to any N
DECLARE @GroupSize INT = 100;


/* =========================================
    PREFIXES (KANGIS REGISTRIES)
    ========================================= */
DECLARE @Prefixes TABLE (prefix NVARCHAR(10));
INSERT INTO @Prefixes (prefix)
VALUES ('KNML'), ('MLKN'), ('MNKL'), ('KNGP');


/* =========================================
    NUMBER GENERATOR (PER PREFIX)
    ========================================= */
;WITH Numbers AS (
     SELECT @StartNo AS n
     UNION ALL
     SELECT n + 1 FROM Numbers WHERE n < @EndNo
),
AllSeries AS (
     SELECT
          p.prefix,
          n.n AS serial_no,
          CONCAT(p.prefix, ' ', n.n) AS awaiting_fileno
     FROM @Prefixes p
     CROSS JOIN Numbers n
)
INSERT INTO kangis_grouping (
     kangis_awaiting_fileno,
     kangis_fileno,
     [number],
     registry,
     [group],
     mdc_batch_no,
     sys_batch_no,
     registry_batch_no,
     created_at
)
SELECT
     s.awaiting_fileno,
     s.awaiting_fileno,
     s.serial_no,
     'KANGIS',  -- All registry set to KANGIS
     ((s.serial_no - 1) / @GroupSize) + 1,
     ((s.serial_no - 1) / @GroupSize) + 1,
     ((s.serial_no - 1) / @GroupSize) + 1,
     ((s.serial_no - 1) / @GroupSize) + 1,
     GETDATE()
FROM AllSeries s
WHERE NOT EXISTS (
     SELECT 1
     FROM kangis_grouping g
     WHERE g.kangis_awaiting_fileno = s.awaiting_fileno
)
OPTION (MAXRECURSION 0);