/*
    Production schema audit for Conversion JSI lookup + report columns.
    Safe to run in production (read-only).
*/

DECLARE @SchemaName sysname = 'dbo';

;WITH ExpectedColumns AS (
    SELECT 'detailed_land_use' AS table_name, 'id' AS column_name UNION ALL
    SELECT 'detailed_land_use', 'name' UNION ALL
    SELECT 'detailed_land_use', 'is_active' UNION ALL
    SELECT 'detailed_land_use', 'created_at' UNION ALL
    SELECT 'detailed_land_use', 'updated_at' UNION ALL

    SELECT 'jsi_lut_purposes', 'id' UNION ALL
    SELECT 'jsi_lut_purposes', 'land_use_id' UNION ALL
    SELECT 'jsi_lut_purposes', 'name' UNION ALL
    SELECT 'jsi_lut_purposes', 'is_active' UNION ALL
    SELECT 'jsi_lut_purposes', 'created_at' UNION ALL
    SELECT 'jsi_lut_purposes', 'updated_at' UNION ALL

    SELECT 'jsi_lut_road_categories', 'id' UNION ALL
    SELECT 'jsi_lut_road_categories', 'name' UNION ALL
    SELECT 'jsi_lut_road_categories', 'is_active' UNION ALL
    SELECT 'jsi_lut_road_categories', 'created_at' UNION ALL
    SELECT 'jsi_lut_road_categories', 'updated_at' UNION ALL

    SELECT 'jsi_lut_reservations', 'id' UNION ALL
    SELECT 'jsi_lut_reservations', 'name' UNION ALL
    SELECT 'jsi_lut_reservations', 'is_active' UNION ALL
    SELECT 'jsi_lut_reservations', 'created_at' UNION ALL
    SELECT 'jsi_lut_reservations', 'updated_at' UNION ALL

    SELECT 'joint_site_inspection_reports', 'application_id' UNION ALL
    SELECT 'joint_site_inspection_reports', 'sub_application_id' UNION ALL
    SELECT 'joint_site_inspection_reports', 'file_number' UNION ALL
    SELECT 'joint_site_inspection_reports', 'inspection_date' UNION ALL
    SELECT 'joint_site_inspection_reports', 'applicant_name' UNION ALL
    SELECT 'joint_site_inspection_reports', 'location' UNION ALL
    SELECT 'joint_site_inspection_reports', 'purpose' UNION ALL
    SELECT 'joint_site_inspection_reports', 'existing_road_reservation' UNION ALL
    SELECT 'joint_site_inspection_reports', 'recommended_road_reservation' UNION ALL
    SELECT 'joint_site_inspection_reports', 'road_reservation' UNION ALL
    SELECT 'joint_site_inspection_reports', 'prevailing_land_use' UNION ALL
    SELECT 'joint_site_inspection_reports', 'applied_land_use' UNION ALL
    SELECT 'joint_site_inspection_reports', 'inspection_officer' UNION ALL
    SELECT 'joint_site_inspection_reports', 'compliance_status' UNION ALL
    SELECT 'joint_site_inspection_reports', 'existing_site_length' UNION ALL
    SELECT 'joint_site_inspection_reports', 'existing_site_width' UNION ALL
    SELECT 'joint_site_inspection_reports', 'existing_site_area' UNION ALL
    SELECT 'joint_site_inspection_reports', 'recommended_site_length' UNION ALL
    SELECT 'joint_site_inspection_reports', 'recommended_site_width' UNION ALL
    SELECT 'joint_site_inspection_reports', 'recommended_site_area' UNION ALL
    SELECT 'joint_site_inspection_reports', 'existing_site_measurement_entries' UNION ALL
    SELECT 'joint_site_inspection_reports', 'source' UNION ALL
    SELECT 'joint_site_inspection_reports', 'created_at' UNION ALL
    SELECT 'joint_site_inspection_reports', 'updated_at'
),
ActualColumns AS (
    SELECT
        t.name AS table_name,
        c.name AS column_name
    FROM sys.tables t
    INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
    INNER JOIN sys.columns c ON c.object_id = t.object_id
    WHERE s.name = @SchemaName
),
TablePresence AS (
    SELECT DISTINCT
        e.table_name,
        CASE WHEN t.object_id IS NULL THEN 0 ELSE 1 END AS table_exists
    FROM (SELECT DISTINCT table_name FROM ExpectedColumns) e
    LEFT JOIN sys.tables t
        ON t.name = e.table_name
    LEFT JOIN sys.schemas s
        ON s.schema_id = t.schema_id
       AND s.name = @SchemaName
)
SELECT
    e.table_name,
    e.column_name,
    CASE WHEN a.column_name IS NULL THEN 'MISSING' ELSE 'OK' END AS status
FROM ExpectedColumns e
LEFT JOIN ActualColumns a
    ON a.table_name = e.table_name
   AND a.column_name = e.column_name
ORDER BY e.table_name, e.column_name;

-- Summary: missing columns only
SELECT
    e.table_name,
    COUNT(*) AS missing_columns
FROM ExpectedColumns e
LEFT JOIN ActualColumns a
    ON a.table_name = e.table_name
   AND a.column_name = e.column_name
WHERE a.column_name IS NULL
GROUP BY e.table_name
ORDER BY e.table_name;

-- Table existence
SELECT
    table_name,
    CASE WHEN table_exists = 1 THEN 'OK' ELSE 'MISSING TABLE' END AS status
FROM TablePresence
ORDER BY table_name;

-- Optional: verify lookup seed counts
SELECT 'detailed_land_use' AS table_name, COUNT(*) AS row_count FROM dbo.detailed_land_use
UNION ALL
SELECT 'jsi_lut_purposes', COUNT(*) FROM dbo.jsi_lut_purposes
UNION ALL
SELECT 'jsi_lut_road_categories', COUNT(*) FROM dbo.jsi_lut_road_categories
UNION ALL
SELECT 'jsi_lut_reservations', COUNT(*) FROM dbo.jsi_lut_reservations;
