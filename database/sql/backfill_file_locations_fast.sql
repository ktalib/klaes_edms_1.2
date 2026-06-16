/* ============================================================================
   FAST backfill of file_indexings.tracking_status / current_location / file_tracker_id
   Optimised + batched version of backfill_file_locations.sql.

   Why it's fast:
     - Latest shelf per file number and latest tracker per file number are
       pre-computed ONCE into indexed temp tables (#shelf, #trk), instead of a
       per-row OUTER APPLY / function-wrapped JOIN over 95k rows.
     - The UPDATE runs in id batches so the transaction log stays small.
     - Skips rows with a manual override (location_status_manual IS NOT NULL).

   Matching note: trackers are matched on file_indexings.file_number (normalised).
   The handful of files whose tracker is keyed only by an alternate number
   (mls/kangis/etc.) self-correct on their next live Quick Search.
   ============================================================================ */
SET NOCOUNT ON;

/* ---- 1. Latest shelf_location per normalised file number ---- */
IF OBJECT_ID('tempdb..#shelf') IS NOT NULL DROP TABLE #shelf;
;WITH s AS (
    SELECT k = UPPER(LTRIM(RTRIM(file_number))),
           shelf_location,
           rn = ROW_NUMBER() OVER (PARTITION BY UPPER(LTRIM(RTRIM(file_number))) ORDER BY id DESC)
    FROM dbo.print_label_batch_items
    WHERE file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
)
SELECT k, shelf_location INTO #shelf FROM s WHERE rn = 1;
CREATE UNIQUE CLUSTERED INDEX ix_shelf_k ON #shelf (k);

/* ---- 2. Latest tracker per normalised file number ---- */
IF OBJECT_ID('tempdb..#trk') IS NOT NULL DROP TABLE #trk;
;WITH t AS (
    SELECT k = UPPER(LTRIM(RTRIM(file_number))),
           tracker_id = id,
           tstatus    = UPPER(LTRIM(RTRIM(ISNULL(status, '')))),
           current_office_name,
           rn = ROW_NUMBER() OVER (PARTITION BY UPPER(LTRIM(RTRIM(file_number))) ORDER BY id DESC)
    FROM dbo.file_tracker
    WHERE file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
)
SELECT k, tracker_id, tstatus, current_office_name INTO #trk FROM t WHERE rn = 1;
CREATE UNIQUE CLUSTERED INDEX ix_trk_k ON #trk (k);

/* ---- 3. Batched UPDATE over file_indexings ---- */
DECLARE @lo BIGINT = 0, @step BIGINT = 5000, @max BIGINT;
SELECT @max = MAX(id) FROM dbo.file_indexings;

WHILE @lo < @max
BEGIN
    ;WITH b AS (
        SELECT fi.id,
               fno = UPPER(LTRIM(RTRIM(
                       CASE WHEN fi.file_number LIKE '%(T)'
                            THEN LEFT(fi.file_number, LEN(fi.file_number) - 3)
                            ELSE fi.file_number END)))
        FROM dbo.file_indexings fi
        WHERE fi.id > @lo AND fi.id <= @lo + @step
          AND fi.file_number IS NOT NULL AND LTRIM(RTRIM(fi.file_number)) <> ''
          AND fi.location_status_manual IS NULL
    ),
    pp AS (
        SELECT b.id, b.fno,
               prefix = CASE WHEN PATINDEX('%-[0-9][0-9][0-9][0-9]%', b.fno) > 0
                             THEN LEFT(b.fno, PATINDEX('%-[0-9][0-9][0-9][0-9]%', b.fno) - 1) END,
               yr     = CASE WHEN PATINDEX('%-[0-9][0-9][0-9][0-9]%', b.fno) > 0
                             THEN TRY_CONVERT(int, SUBSTRING(b.fno, PATINDEX('%-[0-9][0-9][0-9][0-9]%', b.fno) + 1, 4)) END
        FROM b
    ),
    comp AS (
        SELECT pp.id, pp.fno,
            zr = CASE
                WHEN pp.prefix = 'RES'        AND pp.yr BETWEEN 1981 AND 1991 THEN 'archive|Registry 1'
                WHEN pp.prefix = 'RES'        AND pp.yr BETWEEN 1992 AND 2025 THEN 'pool|Registry 2'
                WHEN pp.prefix = 'COM'        AND pp.yr BETWEEN 1981 AND 2025 THEN 'archive|Registry 1'
                WHEN pp.prefix = 'IND'        AND pp.yr BETWEEN 1981 AND 2025 THEN 'archive|Registry 1'
                WHEN pp.prefix = 'AG'         AND pp.yr BETWEEN 1981 AND 2025 THEN 'archive|Registry 1'
                WHEN pp.prefix = 'RES-RC'     AND pp.yr BETWEEN 1981 AND 1991 THEN 'archive|Registry 1'
                WHEN pp.prefix = 'COM-RC'     AND pp.yr BETWEEN 1981 AND 2025 THEN 'archive|Registry 1'
                WHEN pp.prefix = 'IND-RC'     AND pp.yr BETWEEN 1981 AND 2025 THEN 'archive|Registry 1'
                WHEN pp.prefix = 'AG-RC'      AND pp.yr BETWEEN 1981 AND 2025 THEN 'archive|Registry 1'
                WHEN pp.prefix = 'CON-RES'    AND pp.yr BETWEEN 1981 AND 2024 THEN 'archive|Registry 3'
                WHEN pp.prefix = 'CON-RES'    AND pp.yr = 2025                THEN 'pool|Registry 3'
                WHEN pp.prefix = 'CON-COM'    AND pp.yr BETWEEN 1981 AND 2025 THEN 'pool|Registry 3'
                WHEN pp.prefix = 'CON-IND'    AND pp.yr BETWEEN 1981 AND 2025 THEN 'pool|Registry 3'
                WHEN pp.prefix = 'CON-AG'     AND pp.yr BETWEEN 1981 AND 2025 THEN 'pool|Registry 3'
                WHEN pp.prefix = 'CON-RES-RC' AND pp.yr BETWEEN 1981 AND 2025 THEN 'pool|Registry 3'
                WHEN pp.prefix = 'CON-COM-RC' AND pp.yr BETWEEN 1981 AND 2025 THEN 'pool|Registry 3'
                WHEN pp.prefix = 'CON-IND-RC' AND pp.yr BETWEEN 1981 AND 2025 THEN 'pool|Registry 3'
                WHEN pp.prefix = 'CON-AG-RC'  AND pp.yr BETWEEN 1981 AND 2025 THEN 'pool|Registry 3'
                ELSE NULL
            END
        FROM pp
    )
    UPDATE fi
    SET fi.tracking_status  = x.st,
        fi.current_location = x.loc,
        fi.file_tracker_id  = x.tid
    FROM dbo.file_indexings fi
    JOIN comp c       ON c.id = fi.id
    LEFT JOIN #trk t  ON t.k  = c.fno
    LEFT JOIN #shelf s ON s.k = c.fno
    CROSS APPLY (
        SELECT zone = CASE WHEN c.zr IS NOT NULL THEN LEFT(c.zr, CHARINDEX('|', c.zr) - 1) END,
               reg  = CASE WHEN c.zr IS NOT NULL THEN SUBSTRING(c.zr, CHARINDEX('|', c.zr) + 1, 200) END
    ) z
    CROSS APPLY (
        SELECT
            st = CASE
                    WHEN t.tstatus = 'ACTIVE'    THEN 'IN_TRANSIT'
                    WHEN t.tstatus = 'COMPLETED' THEN 'IN_ARCHIVE'
                    WHEN z.zone = 'archive'      THEN 'IN_ARCHIVE'
                    WHEN z.zone = 'pool'         THEN 'IN_POOL_OFFICE'
                    ELSE 'REFER_TO_ORIGINAL_REGISTRY'
                 END,
            tid = CASE WHEN t.tstatus IN ('ACTIVE', 'COMPLETED') THEN t.tracker_id END,
            loc = CASE
                    WHEN t.tstatus = 'ACTIVE' THEN t.current_office_name
                    WHEN t.tstatus = 'COMPLETED' OR z.zone = 'archive' THEN
                        CASE
                            WHEN z.reg IS NOT NULL AND s.shelf_location IS NOT NULL THEN z.reg + ' — Rack/Shelf ' + s.shelf_location
                            WHEN z.reg IS NOT NULL THEN z.reg
                            WHEN s.shelf_location IS NOT NULL THEN 'Digital Archive — Rack/Shelf ' + s.shelf_location
                            ELSE 'Digital Archive'
                        END
                    WHEN z.zone = 'pool' THEN z.reg + ' — Pool Office'
                    ELSE NULL
                 END
    ) x;

    SET @lo = @lo + @step;
END

DROP TABLE #shelf;
DROP TABLE #trk;

/* ---- Verification ---- */
SELECT tracking_status, COUNT(*) AS n
FROM dbo.file_indexings
GROUP BY tracking_status
ORDER BY n DESC;
