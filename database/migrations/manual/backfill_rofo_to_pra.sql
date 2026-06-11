/*
 * Backfill already-generated ROFOs (Land/OSS, SLTR, ST) into the `pra` table.
 *
 * Mirrors App\Services\Pra\RofoPraSyncer. Idempotent: each INSERT is guarded by
 * NOT EXISTS on pra.rofo_number, so re-running skips rows already synced.
 *
 * CAVEAT — this does NOT allocate prop_id (PraRecordService does that via
 * PropertyIdAllocationService). prop_id is left NULL here. The supported,
 * complete path is:  php artisan rofo:backfill-pra --source=all
 * Prefer that command unless you specifically need a pure-SQL run.
 */

USE klas;   -- IMPORTANT: all ROFO + pra tables live in the `klas` database
SET NOCOUNT ON;

/* ----------------------------------------------------------------------------
 * 1) LAND / OSS ROFOs   (source: land_recommendations)
 *    Match the command's selection: rofo_status='generated' OR type='OSS'.
 * -------------------------------------------------------------------------- */
INSERT INTO pra
    (mlsFNo, fileno, rofo_number,
     instrument_type, transaction_type, transaction_date,
     land_use, purpose, plot_no, location, property_description,
     lgsaOrCity, Grantor, party_1, party_2, Grantee,
     source, system_source, created_at, updated_at)
SELECT
    LTRIM(RTRIM(lr.file_number)),
    LTRIM(RTRIM(lr.file_number)),
    LTRIM(RTRIM(lr.file_number)),
    CASE WHEN UPPER(ISNULL(lr.type,'')) = 'OSS'
         THEN 'Right of Occupancy (OSS)' ELSE 'Right of Occupancy' END,
    CASE WHEN UPPER(ISNULL(lr.type,'')) = 'OSS'
         THEN 'Right of Occupancy (OSS)' ELSE 'Right of Occupancy' END,
    CONVERT(varchar(10),
            COALESCE(TRY_CONVERT(date, lr.rofo_date_generated),
                     TRY_CONVERT(date, lr.rofo_generated_at),
                     CAST(GETDATE() AS date)), 23),
    lr.land_use,
    lr.purpose_of_clause,
    lr.plot_number,
    lr.location,
    lr.location,
    lr.lga,
    'KANO STATE GOVERNMENT',
    'KANO STATE GOVERNMENT',
    lr.applicant_name,
    lr.applicant_name,
    CASE WHEN UPPER(ISNULL(lr.type,'')) = 'OSS' THEN 'oss_rofo' ELSE 'land_rofo' END,
    CASE WHEN UPPER(ISNULL(lr.type,'')) = 'OSS' THEN 'OSS_ROFO' ELSE 'LAND_ROFO' END,
    GETDATE(), GETDATE()
FROM land_recommendations lr
WHERE lr.file_number IS NOT NULL
  AND LTRIM(RTRIM(lr.file_number)) <> ''
  AND (lr.rofo_status = 'generated' OR UPPER(ISNULL(lr.type,'')) = 'OSS')
  AND NOT EXISTS (
        SELECT 1 FROM pra p
        WHERE p.rofo_number = LTRIM(RTRIM(lr.file_number))
  );

PRINT CONCAT('Land/OSS ROFOs inserted: ', @@ROWCOUNT);

/* ----------------------------------------------------------------------------
 * 2) SLTR ROFOs   (source: sltr_recommendations; identifier = sltr_number)
 *    Excludes soft-deleted rows.
 * -------------------------------------------------------------------------- */
INSERT INTO pra
    (mlsFNo, fileno, rofo_number,
     instrument_type, transaction_type, transaction_date,
     land_use, purpose, plot_no, location, property_description,
     lgsaOrCity, Grantor, party_1, party_2, Grantee,
     source, system_source, created_at, updated_at)
SELECT
    LTRIM(RTRIM(sr.sltr_number)),
    LTRIM(RTRIM(sr.sltr_number)),
    LTRIM(RTRIM(sr.sltr_number)),
    'Right of Occupancy (SLTR)',
    'Right of Occupancy (SLTR)',
    CONVERT(varchar(10),
            COALESCE(TRY_CONVERT(date, sr.rofo_date_generated),
                     TRY_CONVERT(date, sr.rofo_generated_at),
                     CAST(GETDATE() AS date)), 23),
    sr.land_use,
    sr.purpose_of_clause,
    sr.plot_number,
    sr.location,
    sr.location,
    sr.lga,
    'KANO STATE GOVERNMENT',
    'KANO STATE GOVERNMENT',
    sr.applicant_name,
    sr.applicant_name,
    'sltr_rofo',
    'SLTR_ROFO',
    GETDATE(), GETDATE()
FROM sltr_recommendations sr
WHERE sr.deleted_at IS NULL
  AND sr.rofo_status = 'generated'
  AND sr.sltr_number IS NOT NULL
  AND LTRIM(RTRIM(sr.sltr_number)) <> ''
  AND NOT EXISTS (
        SELECT 1 FROM pra p
        WHERE p.rofo_number = LTRIM(RTRIM(sr.sltr_number))
  );

PRINT CONCAT('SLTR ROFOs inserted: ', @@ROWCOUNT);

/* ----------------------------------------------------------------------------
 * 3) ST (Sectional Titling) ROFOs
 *    source: rofo (per sub_application_id) -> subapplications -> mother_applications
 *    NOTE: owner-name for applicant_type='multiple' is a JSON array in
 *    subapplications.multiple_owners_names and is NOT decoded here; it falls
 *    back to title+first+surname. Use the artisan command for exact parity.
 * -------------------------------------------------------------------------- */
INSERT INTO pra
    (mlsFNo, fileno, rofo_number,
     instrument_type, transaction_type, transaction_date,
     land_use, purpose, plot_no, house_no, location, property_description,
     lgsaOrCity, Grantor, party_1, party_2, Grantee,
     source, system_source, created_at, updated_at)
SELECT
    fileNumber.fn,
    fileNumber.fn,
    fileNumber.fn,
    'Right of Occupancy (ST)',
    'Right of Occupancy (ST)',
    CONVERT(varchar(10),
            COALESCE(TRY_CONVERT(date, r.approval_date),
                     TRY_CONVERT(date, sa.approval_date),
                     TRY_CONVERT(date, ma.approval_date),
                     CAST(GETDATE() AS date)), 23),
    COALESCE(NULLIF(LTRIM(RTRIM(sa.specific_land_use)),''), ma.land_use),
    r.purpose,
    COALESCE(NULLIF(LTRIM(RTRIM(r.plot_no)),''), ma.property_plot_no),
    ma.property_house_no,
    COALESCE(NULLIF(LTRIM(RTRIM(r.location)),''),
             LTRIM(RTRIM(
                CONCAT_WS(' ', ma.property_house_no, ma.property_plot_no,
                              ma.property_street_name, ma.property_district)))),
    COALESCE(NULLIF(LTRIM(RTRIM(r.location)),''),
             LTRIM(RTRIM(
                CONCAT_WS(' ', ma.property_house_no, ma.property_plot_no,
                              ma.property_street_name, ma.property_district)))),
    ma.property_lga,
    'KANO STATE GOVERNMENT',
    'KANO STATE GOVERNMENT',
    /* owner name (corporate -> corporate_name; else title+first+surname) */
    CASE WHEN LOWER(LTRIM(RTRIM(ISNULL(sa.applicant_type,'')))) = 'corporate'
              AND NULLIF(LTRIM(RTRIM(sa.corporate_name)),'') IS NOT NULL
         THEN LTRIM(RTRIM(sa.corporate_name))
         ELSE NULLIF(LTRIM(RTRIM(
                CONCAT_WS(' ', sa.applicant_title, sa.first_name, sa.surname))),'')
    END,
    CASE WHEN LOWER(LTRIM(RTRIM(ISNULL(sa.applicant_type,'')))) = 'corporate'
              AND NULLIF(LTRIM(RTRIM(sa.corporate_name)),'') IS NOT NULL
         THEN LTRIM(RTRIM(sa.corporate_name))
         ELSE NULLIF(LTRIM(RTRIM(
                CONCAT_WS(' ', sa.applicant_title, sa.first_name, sa.surname))),'')
    END,
    'st_rofo',
    'ST_ROFO',
    GETDATE(), GETDATE()
/*
 * Drive off subapplications (the reliable source of unit file numbers), NOT
 * rofo.sub_application_id (stale on legacy rows). A unit qualifies if its
 * mother (main_application_id) has any ROFO, or it is directly referenced by a
 * ROFO. The matching ROFO row (when one exists) is pulled via OUTER APPLY TOP 1
 * so a unit can never be duplicated by multiple rofo rows.
 */
FROM subapplications sa
LEFT JOIN mother_applications ma ON ma.id = sa.main_application_id
OUTER APPLY (
    SELECT TOP 1 r2.approval_date, r2.purpose, r2.plot_no, r2.location
    FROM rofo r2
    WHERE r2.sub_application_id = sa.id
    ORDER BY r2.id DESC
) r
CROSS APPLY (
    SELECT LTRIM(RTRIM(COALESCE(NULLIF(LTRIM(RTRIM(sa.fileno)),''), ma.fileno))) AS fn
) fileNumber
WHERE (sa.is_deleted IS NULL OR sa.is_deleted = 0)
  AND (
        sa.main_application_id IN (SELECT DISTINCT application_id   FROM rofo WHERE application_id   IS NOT NULL)
     OR sa.id                  IN (SELECT DISTINCT sub_application_id FROM rofo WHERE sub_application_id IS NOT NULL)
  )
  AND fileNumber.fn IS NOT NULL
  AND fileNumber.fn <> ''
  AND NOT EXISTS (
        SELECT 1 FROM pra p WHERE p.rofo_number = fileNumber.fn
  );

PRINT CONCAT('ST ROFOs inserted: ', @@ROWCOUNT);
