/* ============================================================================
   Backfill: file_indexings.tracking_status / current_location / file_tracker_id
   Mirrors App\Services\FileLocationResolver precedence (set-based, one pass).

   Precedence per row:
     1. Latest matching file_tracker:
          status ACTIVE    -> IN_TRANSIT  (loc = tracker.current_office_name)
          status COMPLETED -> IN_ARCHIVE  (loc = registry + rack/shelf)
          status CANCELLED -> treated as "no tracker", fall through
     2. No active tracker -> registry range (config/file_ranges.php):
          zone archive -> IN_ARCHIVE        (row is indexed => always "scanned")
          zone pool    -> IN_POOL_OFFICE
     3. No tracker and no range -> REFER_TO_ORIGINAL_REGISTRY

   Run inside a transaction; review the verification SELECT at the bottom first.
   ============================================================================ */

BEGIN TRANSACTION;

;WITH norm AS (
    SELECT fi.id AS fid,
           UPPER(LTRIM(RTRIM(
               CASE WHEN fi.file_number LIKE '%(T)'
                    THEN LEFT(fi.file_number, LEN(fi.file_number) - 3)
                    ELSE fi.file_number END
           ))) AS fno
    FROM file_indexings fi
    WHERE fi.file_number IS NOT NULL
      AND LTRIM(RTRIM(fi.file_number)) <> ''
),
pp AS (   -- parse PREFIX + 4-digit YEAR (first "-YYYY" group)
    SELECT n.fid, n.fno,
           CASE WHEN PATINDEX('%-[0-9][0-9][0-9][0-9]%', n.fno) > 0
                THEN LEFT(n.fno, PATINDEX('%-[0-9][0-9][0-9][0-9]%', n.fno) - 1) END AS prefix,
           CASE WHEN PATINDEX('%-[0-9][0-9][0-9][0-9]%', n.fno) > 0
                THEN TRY_CONVERT(int, SUBSTRING(n.fno, PATINDEX('%-[0-9][0-9][0-9][0-9]%', n.fno) + 1, 4)) END AS yr
    FROM norm n
),
rng AS (  -- zone|registry per registry-range config (longest prefixes are distinct strings)
    SELECT pp.fid, pp.fno,
        CASE
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
        END AS zr
    FROM pp
),
trk AS (  -- latest file_tracker matched on any of the file-number variants
    SELECT r.fid,
           ft.id AS tracker_id,
           UPPER(LTRIM(RTRIM(ISNULL(ft.status, '')))) AS tstatus,
           ft.current_office_name,
           ROW_NUMBER() OVER (PARTITION BY r.fid ORDER BY ft.id DESC) AS rn
    FROM rng r
    JOIN file_indexings fi ON fi.id = r.fid
    JOIN file_tracker ft
      ON LTRIM(RTRIM(ISNULL(ft.file_number, ''))) <> ''
     AND UPPER(LTRIM(RTRIM(ft.file_number))) IN (
            r.fno,
            UPPER(LTRIM(RTRIM(ISNULL(fi.new_kangis_file_no, '')))),
            UPPER(LTRIM(RTRIM(ISNULL(fi.kangis_file_no, '')))),
            UPPER(LTRIM(RTRIM(ISNULL(fi.mls_file_no, '')))),
            UPPER(LTRIM(RTRIM(ISNULL(fi.st_fillno, ''))))
         )
),
shelf AS (  -- rack/shelf: print_label_batch_items first, else file_indexings.shelf_location
    SELECT r.fid,
           COALESCE(pli.shelf_location, fi.shelf_location) AS rack_shelf
    FROM rng r
    JOIN file_indexings fi ON fi.id = r.fid
    OUTER APPLY (
        SELECT TOP 1 p.shelf_location
        FROM print_label_batch_items p
        WHERE UPPER(LTRIM(RTRIM(p.file_number))) = r.fno
        ORDER BY p.id DESC
    ) pli
),
base AS (
    SELECT r.fid,
           r.zr,
           CASE WHEN r.zr IS NOT NULL THEN LEFT(r.zr, CHARINDEX('|', r.zr) - 1) END  AS zone,
           CASE WHEN r.zr IS NOT NULL THEN SUBSTRING(r.zr, CHARINDEX('|', r.zr) + 1, 200) END AS registry,
           t.tracker_id, t.tstatus, t.current_office_name,
           s.rack_shelf
    FROM rng r
    LEFT JOIN trk   t ON t.fid = r.fid AND t.rn = 1
    LEFT JOIN shelf s ON s.fid = r.fid
)
UPDATE fi
SET fi.tracking_status  = x.new_status,
    fi.current_location = x.new_loc,
    fi.file_tracker_id  = x.new_tracker
FROM file_indexings fi
JOIN base b ON b.fid = fi.id
CROSS APPLY (
    SELECT
        new_status =
            CASE
                WHEN b.tstatus = 'ACTIVE'    THEN 'IN_TRANSIT'
                WHEN b.tstatus = 'COMPLETED' THEN 'IN_ARCHIVE'
                WHEN b.zone = 'archive'      THEN 'IN_ARCHIVE'
                WHEN b.zone = 'pool'         THEN 'IN_POOL_OFFICE'
                ELSE 'REFER_TO_ORIGINAL_REGISTRY'
            END,
        new_tracker =
            CASE WHEN b.tstatus IN ('ACTIVE', 'COMPLETED') THEN b.tracker_id ELSE NULL END,
        new_loc =
            CASE
                WHEN b.tstatus = 'ACTIVE' THEN b.current_office_name
                WHEN b.tstatus = 'COMPLETED' OR b.zone = 'archive' THEN
                    CASE
                        WHEN b.registry IS NOT NULL AND b.rack_shelf IS NOT NULL
                            THEN b.registry + ' — Rack/Shelf ' + b.rack_shelf
                        WHEN b.registry IS NOT NULL THEN b.registry
                        WHEN b.rack_shelf IS NOT NULL THEN 'Digital Archive — Rack/Shelf ' + b.rack_shelf
                        ELSE 'Digital Archive'
                    END
                WHEN b.zone = 'pool' THEN b.registry + ' — Pool Office'
                ELSE NULL
            END
) x;

/* ---- Verification (run before COMMIT) ---- */
SELECT tracking_status, COUNT(*) AS n
FROM file_indexings
GROUP BY tracking_status
ORDER BY n DESC;

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
