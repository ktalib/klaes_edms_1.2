-- ============================================================================
-- query_related_file_number_by_indexing.sql
-- ----------------------------------------------------------------------------
-- Returns related_file_number rows where the PARENT file_number actually
-- exists in file_indexings (i.e. the parent is a tracked, indexed file).
-- ============================================================================

SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

SELECT
    r.id,
    r.related_fileno,
    r.file_number              AS parent_file,
    r.prop_id,
    r.file_title,
    r.party_2,
    r.location,
    r.comment,
    r.source_table,
    fi.id                      AS file_indexing_id,
    fi.file_title              AS indexing_file_title,
    fi.current_holder          AS indexing_current_holder,
    fi.district                AS indexing_district,
    fi.lga                     AS indexing_lga
FROM [dbo].[related_file_number] r
INNER JOIN [dbo].[file_indexings] fi
        ON fi.file_number = r.file_number
       AND fi.deleted_at IS NULL
ORDER BY r.id DESC;
