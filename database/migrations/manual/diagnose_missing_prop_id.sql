-- ============================================================================
-- diagnose_missing_prop_id.sql
-- ----------------------------------------------------------------------------
-- Diagnoses why some related_file_number rows from file_indexings have a
-- NULL prop_id. Pass the parent file numbers you want to investigate.
-- ============================================================================

SET NOCOUNT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

-- ---------------------------------------------------------------------------
-- 0) Files we want to investigate (edit this list)
-- ---------------------------------------------------------------------------
DECLARE @target TABLE (file_no NVARCHAR(255));
INSERT INTO @target VALUES
    ('COM-1994-66'),
    ('RES-1993-4499'),
    ('CON-COM-2009-183'),
    ('RES-2009-6164');

-- ---------------------------------------------------------------------------
-- 1) What does file_indexings have for them?
-- ---------------------------------------------------------------------------
PRINT '=== A) file_indexings rows for the target file numbers ===';
SELECT
    t.file_no                      AS target_file,
    fi.id                          AS fi_id,
    fi.file_number,
    fi.prop_id                     AS fi_prop_id,
    fi.parent_prop_id              AS fi_parent_prop_id,
    fi.file_title,
    fi.current_holder,
    fi.deleted_at                  AS fi_deleted_at,
    CASE WHEN fi.id IS NULL THEN '!! MISSING FROM file_indexings' ELSE '' END AS note
FROM @target t
LEFT JOIN [dbo].[file_indexings] fi WITH (NOLOCK) ON fi.file_number = t.file_no
ORDER BY t.file_no;

-- ---------------------------------------------------------------------------
-- 2) Does PropID_Master know about these files?
-- ---------------------------------------------------------------------------
PRINT '=== B) PropID_Master lookups ===';
SELECT
    t.file_no                          AS target_file,
    pm.id                              AS pm_id,
    pm.prop_id                         AS pm_prop_id,
    pm.primary_file_number,
    pm.mlsFNo,
    pm.kangisFileNo,
    pm.NewKANGISFileno,
    pm.temp_fileno,
    CASE WHEN pm.id IS NULL THEN '!! NOT in PropID_Master at all' ELSE '' END AS note
FROM @target t
LEFT JOIN [dbo].[PropID_Master] pm WITH (NOLOCK)
       ON pm.mlsFNo           = t.file_no
       OR pm.kangisFileNo     = t.file_no
       OR pm.NewKANGISFileno  = t.file_no
       OR pm.temp_fileno      = t.file_no
       OR pm.primary_file_number = t.file_no
ORDER BY t.file_no;

-- ---------------------------------------------------------------------------
-- 3) Do PRA / PIC / IC / FH / CofO know about them?
-- ---------------------------------------------------------------------------
PRINT '=== C) Source table coverage ===';
SELECT t.file_no AS target_file,
       (SELECT COUNT(*) FROM pra                  WHERE mlsFNo = t.file_no) AS in_pra,
       (SELECT COUNT(*) FROM pic                  WHERE mlsFNo = t.file_no) AS in_pic,
       (SELECT COUNT(*) FROM instrument_capture   WHERE mlsFNo = t.file_no) AS in_ic,
       (SELECT COUNT(*) FROM file_history_staging WHERE mlsFNo = t.file_no) AS in_fh,
       (SELECT COUNT(*) FROM CofO_staging         WHERE mlsFNo = t.file_no) AS in_cofo
FROM @target t;

-- ---------------------------------------------------------------------------
-- 4) Aggregate: how many file_indexings rows total are missing prop_id?
-- ---------------------------------------------------------------------------
PRINT '=== D) file_indexings overall prop_id coverage ===';
SELECT
    COUNT(*) AS total_rows,
    SUM(CASE WHEN prop_id IS NOT NULL THEN 1 ELSE 0 END) AS with_prop_id,
    SUM(CASE WHEN prop_id IS NULL     THEN 1 ELSE 0 END) AS without_prop_id
FROM [dbo].[file_indexings] WITH (NOLOCK)
WHERE deleted_at IS NULL;

-- ---------------------------------------------------------------------------
-- 5) Has remap_file_indexings_propid.sql run successfully?
--    (If yes, every file_indexings row whose file_number is in PropID_Master
--     should have a prop_id; check the gap.)
-- ---------------------------------------------------------------------------
PRINT '=== E) Resolvable but not resolved (suggests remap was not run) ===';
SELECT COUNT(*) AS resolvable_but_null
FROM [dbo].[file_indexings] fi WITH (NOLOCK)
WHERE fi.deleted_at IS NULL
  AND fi.prop_id IS NULL
  AND EXISTS (
        SELECT 1 FROM [dbo].[PropID_Master] pm WITH (NOLOCK)
        WHERE pm.mlsFNo          = fi.file_number
           OR pm.kangisFileNo    = fi.file_number
           OR pm.NewKANGISFileno = fi.file_number
           OR pm.temp_fileno     = fi.file_number
  );
