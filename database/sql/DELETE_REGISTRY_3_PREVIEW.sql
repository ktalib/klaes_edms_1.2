USE [klas];
GO

/*
    Registry 3 purge preview
    ------------------------
    This script only inspects the rows that would be deleted for the following target pairs:
        • Registry batch numbers 1201-1207  -> Rack/Shelf C18-C24
        • Registry batch numbers 1301-1308  -> Rack/Shelf C25-C32
        • Registry batch numbers 1401-1406  -> Rack/Shelf C33-C38
        • Registry batch numbers 1501-1506  -> Rack/Shelf C39-D2  (handled as C39-C48 plus D1-D2)

    Outputs:
        1. High-level counts per target group.
        2. Detailed file_indexings rows that match the definition.
        3. Pending print_label_batch_items (if any) tied to those rows.
        4. Rack_Shelf_Labels state for every shelf in scope.
*/

SET NOCOUNT ON;

DECLARE @RegistryFilter NVARCHAR(64) = N'Registry 3%';

DECLARE @TargetDefinitions TABLE (
    target_id         INT          PRIMARY KEY,
    batch_start       INT          NOT NULL,
    batch_end         INT          NOT NULL,
    batch_label       NVARCHAR(32) NOT NULL,
    shelf_span_label  NVARCHAR(32) NOT NULL
);

INSERT INTO @TargetDefinitions (target_id, batch_start, batch_end, batch_label, shelf_span_label)
VALUES
    (1, 1201, 1207, N'1201-1207', N'C18-C24'),
    (2, 1301, 1308, N'1301-1308', N'C25-C32'),
    (3, 1401, 1406, N'1401-1406', N'C33-C38'),
    (4, 1501, 1506, N'1501-1506', N'C39-D2');

DECLARE @ShelfSegments TABLE (
    target_id  INT,
    rack       NVARCHAR(10),
    shelf_from INT,
    shelf_to   INT
);

INSERT INTO @ShelfSegments (target_id, rack, shelf_from, shelf_to)
VALUES
    (1, N'C', 18, 24),
    (2, N'C', 25, 32),
    (3, N'C', 33, 38),
    (4, N'C', 39, 48), -- C39 through C48
    (4, N'D',  1,  2); -- D1 through D2 (completes the C39-D2 span)

DECLARE @ShelfTargets TABLE (
    target_id        INT,
    shelf_label_id   INT,
    full_label       NVARCHAR(20),
    rack             NVARCHAR(10),
    shelf_number     INT,
    is_used          BIT,
    reserved_by      NVARCHAR(255),
    reserved_at      DATETIME2
);

INSERT INTO @ShelfTargets (target_id, shelf_label_id, full_label, rack, shelf_number, is_used, reserved_by, reserved_at)
SELECT
    ss.target_id,
    rsl.id,
    rsl.full_label,
    rsl.rack,
    TRY_CONVERT(INT, rsl.shelf) AS shelf_number,
    rsl.is_used,
    rsl.reserved_by,
    rsl.reserved_at
FROM @ShelfSegments ss
JOIN Rack_Shelf_Labels rsl
  ON rsl.rack = ss.rack
 AND TRY_CONVERT(INT, rsl.shelf) BETWEEN ss.shelf_from AND ss.shelf_to;

IF OBJECT_ID('tempdb..#CandidateFiles') IS NOT NULL DROP TABLE #CandidateFiles;

SELECT
    fi.id,
    fi.file_number,
    fi.file_title,
    fi.land_use_type,
    fi.plot_number,
    fi.district,
    fi.lga,
    fi.registry,
    fi.registry_batch_no,
    conv.registry_batch_no_int,
    fi.batch_no,
    fi.serial_no,
    fi.shelf_location,
    fi.shelf_label_id,
    fi.batch_generated,
    fi.created_at,
    fi.updated_at,
    fi.created_by,
    fi.updated_by,
    td.target_id,
    td.batch_label,
    td.shelf_span_label
INTO #CandidateFiles
FROM file_indexings fi
CROSS APPLY (SELECT TRY_CONVERT(INT, fi.registry_batch_no) AS registry_batch_no_int) AS conv
JOIN @TargetDefinitions td
  ON conv.registry_batch_no_int BETWEEN td.batch_start AND td.batch_end
WHERE conv.registry_batch_no_int IS NOT NULL
  AND fi.registry LIKE @RegistryFilter
  AND ISNULL(fi.is_deleted, 0) = 0;

IF OBJECT_ID('tempdb..#FileTargets') IS NOT NULL DROP TABLE #FileTargets;

SELECT
    cf.*,
    st.shelf_label_id    AS matched_shelf_label_id,
    st.full_label        AS matched_shelf_full_label,
    st.is_used           AS matched_shelf_is_used,
    st.reserved_by       AS matched_shelf_reserved_by,
    st.reserved_at       AS matched_shelf_reserved_at,
    CASE WHEN st.shelf_label_id IS NOT NULL THEN 1 ELSE 0 END AS matches_defined_shelf_range
INTO #FileTargets
FROM #CandidateFiles cf
OUTER APPLY (
    SELECT TOP (1) st.*
    FROM @ShelfTargets st
    WHERE st.target_id = cf.target_id
      AND (
            (cf.shelf_label_id IS NOT NULL AND cf.shelf_label_id = st.shelf_label_id)
         OR (cf.shelf_label_id IS NULL AND cf.shelf_location = st.full_label)
          )
) AS st;

IF OBJECT_ID('tempdb..#LabelBatchLinks') IS NOT NULL DROP TABLE #LabelBatchLinks;

IF OBJECT_ID('dbo.print_label_batch_items', 'U') IS NOT NULL
BEGIN
    SELECT
        pbi.id              AS batch_item_id,
        pbi.batch_id,
        pbi.file_indexing_id,
        pbi.is_printed,
        pbi.printed_at,
        pbi.created_at      AS batch_item_created_at,
        pb.batch_number,
        pb.status           AS batch_status,
        pb.created_at       AS batch_created_at,
        pb.updated_at       AS batch_updated_at
    INTO #LabelBatchLinks
    FROM print_label_batch_items pbi
    JOIN #CandidateFiles cf ON cf.id = pbi.file_indexing_id
    JOIN print_label_batches pb ON pb.id = pbi.batch_id;
END
ELSE
BEGIN
    SELECT
        CAST(NULL AS INT)         AS batch_item_id,
        CAST(NULL AS INT)         AS batch_id,
        CAST(NULL AS INT)         AS file_indexing_id,
        CAST(NULL AS BIT)         AS is_printed,
        CAST(NULL AS DATETIME2)   AS printed_at,
        CAST(NULL AS DATETIME2)   AS batch_item_created_at,
        CAST(NULL AS NVARCHAR(50)) AS batch_number,
        CAST(NULL AS NVARCHAR(20)) AS batch_status,
        CAST(NULL AS DATETIME2)   AS batch_created_at,
        CAST(NULL AS DATETIME2)   AS batch_updated_at
    INTO #LabelBatchLinks
    WHERE 1 = 0;
END;

--------------------------------------------------------------------------------
-- 1. Summary of affected file_indexings rows per registry batch / shelf span
--------------------------------------------------------------------------------
SELECT
    td.target_id,
    td.batch_label,
    td.shelf_span_label,
    COUNT(ft.id)                                         AS file_count,
    MIN(ft.registry_batch_no_int)                        AS min_registry_batch_no,
    MAX(ft.registry_batch_no_int)                        AS max_registry_batch_no,
    SUM(CASE WHEN ft.matches_defined_shelf_range = 1 THEN 1 ELSE 0 END) AS files_with_matching_shelf,
    SUM(CASE WHEN ft.matches_defined_shelf_range = 0 THEN 1 ELSE 0 END) AS files_missing_shelf_alignment,
    COUNT(DISTINCT ft.matched_shelf_label_id)            AS distinct_shelf_labels
FROM #FileTargets ft
JOIN @TargetDefinitions td ON td.target_id = ft.target_id
GROUP BY td.target_id, td.batch_label, td.shelf_span_label
ORDER BY td.target_id;

--------------------------------------------------------------------------------
-- 2. Detailed file list
--------------------------------------------------------------------------------
SELECT
    td.target_id,
    td.batch_label,
    td.shelf_span_label,
    ft.registry_batch_no,
    ft.file_number,
    ft.batch_no,
    ft.serial_no,
    ft.shelf_location,
    ft.matched_shelf_full_label,
    CASE WHEN ft.matches_defined_shelf_range = 1 THEN 'Y' ELSE 'N' END AS shelf_in_target_range,
    ft.created_at,
    ft.updated_at
FROM #FileTargets ft
JOIN @TargetDefinitions td ON td.target_id = ft.target_id
ORDER BY td.target_id, ft.registry_batch_no_int, ft.file_number;

--------------------------------------------------------------------------------
-- 3. Print label batches/items that would also be orphaned
--------------------------------------------------------------------------------
SELECT
    lbl.batch_number,
    lbl.batch_status,
    COUNT(*)                                         AS total_items,
    SUM(CASE WHEN lbl.is_printed = 1 THEN 1 ELSE 0 END) AS printed_items,
    SUM(CASE WHEN lbl.is_printed = 0 THEN 1 ELSE 0 END) AS pending_items
FROM #LabelBatchLinks lbl
GROUP BY lbl.batch_number, lbl.batch_status
ORDER BY lbl.batch_number;

SELECT
    lbl.batch_number,
    lbl.batch_status,
    lbl.batch_item_id,
    lbl.is_printed,
    cf.registry_batch_no,
    cf.file_number,
    cf.shelf_location
FROM #LabelBatchLinks lbl
JOIN #CandidateFiles cf ON cf.id = lbl.file_indexing_id
ORDER BY lbl.batch_number, cf.registry_batch_no_int, cf.file_number;

--------------------------------------------------------------------------------
-- 4. Rack/Shelf utilization preview
--------------------------------------------------------------------------------
SELECT
    st.target_id,
    td.batch_label,
    td.shelf_span_label,
    st.full_label,
    st.rack,
    st.shelf_number,
    st.is_used,
    st.reserved_by,
    st.reserved_at,
    COUNT(ft.id) AS linked_file_count
FROM @ShelfTargets st
JOIN @TargetDefinitions td ON td.target_id = st.target_id
LEFT JOIN #FileTargets ft ON ft.matched_shelf_label_id = st.shelf_label_id
GROUP BY
    st.target_id,
    td.batch_label,
    td.shelf_span_label,
    st.full_label,
    st.rack,
    st.shelf_number,
    st.is_used,
    st.reserved_by,
    st.reserved_at
ORDER BY st.target_id, st.rack, st.shelf_number;

--------------------------------------------------------------------------------
-- Cleanup temp tables
--------------------------------------------------------------------------------
DROP TABLE IF EXISTS #LabelBatchLinks;
DROP TABLE IF EXISTS #FileTargets;
DROP TABLE IF EXISTS #CandidateFiles;
