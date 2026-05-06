/*
 * Script: import_st_file_numbers_into_fileNumber.sql
 * Purpose: Seed dbo.fileNumber with the authoritative records from dbo.st_file_numbers.
 *          Use this when the legacy fileNumber table is empty and you want to
 *          bootstrap it with the new Sectional Titling (ST) numbers.
 *
 * Usage overview:
 *   1. Review STEP 1 preview output before running the INSERT.
 *   2. Execute the INSERT block inside an explicit transaction so you can
 *      COMMIT or ROLLBACK after validating the row count.
 *   3. Rerunning the script is safe; the INSERT protects against duplicates.
 */

/* ----------------------------------------------------------------------------
STEP 0 (optional): capture an empty snapshot table in case you need to inspect
                   results after the import. Uncomment if desired.
-------------------------------------------------------------------------------
*/
-- IF OBJECT_ID('dbo.fileNumber_pre_import_backup', 'U') IS NOT NULL DROP TABLE dbo.fileNumber_pre_import_backup;
-- SELECT * INTO dbo.fileNumber_pre_import_backup FROM dbo.fileNumber;

/* ----------------------------------------------------------------------------
STEP 1: Stage ST data in a temp table and preview the rows that will be inserted
        into dbo.fileNumber.
-------------------------------------------------------------------------------
*/
IF OBJECT_ID('tempdb..#st_normalized', 'U') IS NOT NULL DROP TABLE #st_normalized;

SELECT
    st.id                                        AS st_id,
    LTRIM(RTRIM(ISNULL(st.mls_fileno, '')))      AS raw_mlsf,
    LTRIM(RTRIM(ISNULL(st.np_fileno, '')))       AS raw_np,
    LTRIM(RTRIM(ISNULL(st.fileno, '')))          AS raw_st,
    st.file_no_type,
    st.land_use,
    st.land_use_code,
    st.year,
    st.serial_no,
    st.unit_sequence,
    st.status,
    st.reserved_at,
    st.used_at,
    st.created_at,
    st.updated_at,
    st.created_by,
    st.mother_application_id,
    st.subapplication_id,
    st.tra,
    st.application_type,
    st.date_commissioned,
    st.applicant_type,
    st.applicant_title,
    st.first_name,
    st.surname,
    st.corporate_name,
    st.rc_number,
    st.multiple_owners_names
INTO #st_normalized
FROM dbo.st_file_numbers st
WHERE LTRIM(RTRIM(ISNULL(st.fileno, ''))) <> '';

SELECT
    n.st_id,
    CASE
        WHEN n.raw_mlsf <> '' THEN n.raw_mlsf
        WHEN n.raw_np   <> '' THEN n.raw_np
        ELSE n.raw_st
    END                                            AS mlsfNo,
    n.raw_st                                       AS st_file_no,
    n.raw_np                                       AS kangisFileNo,
    NULL                                           AS NewKANGISFileNo,
    CASE 
        WHEN n.applicant_type = 'Corporate' AND n.corporate_name <> ''
            THEN n.corporate_name
        WHEN n.applicant_type = 'Individual'
            THEN RTRIM(LTRIM(CONCAT(n.applicant_title, ' ', n.first_name, ' ', n.surname)))
        ELSE n.multiple_owners_names
    END                                            AS FileName,
    n.land_use                                     AS location_hint,
    n.reserved_at                                  AS created_at,
    ISNULL(n.used_at, n.updated_at)                AS updated_at,
    n.created_by,
    n.file_no_type,
    n.status,
    n.mother_application_id,
    n.subapplication_id,
    n.tra,
    n.application_type,
    n.date_commissioned
FROM #st_normalized n
ORDER BY n.st_id;

/* ----------------------------------------------------------------------------
STEP 2: Insert into dbo.fileNumber. Wrap in a manual transaction so you can
        COMMIT or ROLLBACK after inspecting the results.
-------------------------------------------------------------------------------
*/
BEGIN TRANSACTION;

    INSERT INTO dbo.fileNumber (
        application_id,
        sub_application_id,
        kangisFileNo,
        mlsfNo,
        NewKANGISFileNo,
        FileName,
        created_at,
        updated_at,
        location,
        created_by,
        type,
        is_deleted,
        SOURCE,
        commissioning_date,
        st_file_no,
        tracking_id,
        temp_fileno
    )
    SELECT
        n.mother_application_id                     AS application_id,
        n.subapplication_id                         AS sub_application_id,
        NULLIF(n.raw_np, '')                        AS kangisFileNo,
        CASE
            WHEN n.raw_mlsf <> '' THEN n.raw_mlsf
            WHEN n.raw_np   <> '' THEN n.raw_np
            ELSE n.raw_st
        END                                         AS mlsfNo,
        NULL                                        AS NewKANGISFileNo,
        CASE 
            WHEN n.applicant_type = 'Corporate' AND n.corporate_name <> ''
                THEN n.corporate_name
            WHEN n.applicant_type = 'Individual'
                THEN RTRIM(LTRIM(CONCAT(n.applicant_title, ' ', n.first_name, ' ', n.surname)))
            ELSE NULLIF(n.multiple_owners_names, '')
        END                                         AS FileName,
        n.reserved_at                               AS created_at,
        ISNULL(n.used_at, n.updated_at)             AS updated_at,
        n.land_use                                  AS location,
        n.created_by                                AS created_by,
        n.file_no_type                              AS type,
        0                                           AS is_deleted,
        'ST Import'                                 AS SOURCE,
        n.date_commissioned                         AS commissioning_date,
        n.raw_st                                    AS st_file_no,
        n.tra                                       AS tracking_id,
        CASE WHEN n.file_no_type = 'PRIMARY' THEN n.raw_st ELSE NULL END AS temp_fileno
    FROM #st_normalized n
    WHERE n.raw_st <> ''
      AND NOT EXISTS (
            SELECT 1
            FROM dbo.fileNumber existing
            WHERE LTRIM(RTRIM(ISNULL(existing.st_file_no, ''))) = n.raw_st
        );

    DECLARE @rows_inserted INT = @@ROWCOUNT;
    PRINT CONCAT('Rows inserted into dbo.fileNumber: ', @rows_inserted);

    SELECT TOP (50)
        fn.id,
        fn.mlsfNo,
        fn.st_file_no,
        fn.FileName,
        fn.created_at,
        fn.type,
        fn.SOURCE
    FROM dbo.fileNumber fn
    ORDER BY fn.id DESC;

-- COMMIT TRANSACTION;   -- <<< IMPORTANT: uncomment when satisfied with the preview above.
-- ROLLBACK TRANSACTION; -- Use this if validation fails.

/* ----------------------------------------------------------------------------
STEP 3: Post-import validation checklist
-------------------------------------------------------------------------------
1. Run SELECT COUNT(*) FROM dbo.fileNumber; to confirm the expected row count.
2. Spot-check a few records against dbo.st_file_numbers to ensure mlsfNo and
   st_file_no line up correctly.
3. Remember to COMMIT the transaction after validation, or ROLLBACK if you need
   to make adjustments and rerun.
-------------------------------------------------------------------------------
 */
