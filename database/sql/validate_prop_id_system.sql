/*
PropID Master System Validation Script
- Tests table structure, constraints, computed columns, and data integrity
- Run after executing prop_id_master_table.sql to confirm everything works
*/

USE [klas]
GO

PRINT '=== PropID Master System Validation ==='
PRINT ''

-- 1) Verify table exists and has correct structure
PRINT '1. Checking table structure...'
IF OBJECT_ID('dbo.PropID_Master', 'U') IS NOT NULL
BEGIN
    PRINT '✓ PropID_Master table exists'
    
    SELECT 
        COLUMN_NAME,
        DATA_TYPE,
        CHARACTER_MAXIMUM_LENGTH,
        IS_NULLABLE,
        COLUMN_DEFAULT
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'PropID_Master'
    ORDER BY ORDINAL_POSITION;
    
END
ELSE
    PRINT '✗ PropID_Master table missing!'

PRINT ''

-- 2) Check computed columns
PRINT '2. Verifying computed columns...'
SELECT 
    name AS column_name,
    is_computed,
    is_persisted
FROM sys.columns 
WHERE object_id = OBJECT_ID('dbo.PropID_Master') 
    AND name LIKE '%_norm'
ORDER BY name;

PRINT ''

-- 3) Verify constraints and indexes
PRINT '3. Checking constraints and indexes...'
SELECT 
    i.name AS index_name,
    i.type_desc AS index_type,
    i.is_unique,
    STRING_AGG(c.name, ', ') AS columns
FROM sys.indexes i
JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
WHERE i.object_id = OBJECT_ID('dbo.PropID_Master')
GROUP BY i.name, i.type_desc, i.is_unique
ORDER BY i.name;

PRINT ''

-- 4) Check conflict view exists
PRINT '4. Verifying conflict view...'
IF OBJECT_ID('dbo.vw_prop_id_conflicts', 'V') IS NOT NULL
    PRINT '✓ vw_prop_id_conflicts view exists'
ELSE
    PRINT '✗ vw_prop_id_conflicts view missing!'

PRINT ''

-- 5) Test computed column normalization
PRINT '5. Testing computed column normalization...'
-- Insert test record to verify normalization works
BEGIN TRY
    INSERT INTO dbo.PropID_Master (
        prop_id, primary_file_number, mlsFNo, kangisFileNo, 
        source_table, source_record_id
    )
    VALUES (
        999999, '  test-file-123  ', '  MLS-456  ', '  KANGIS-789  ',
        'test', 1
    );
    
    SELECT 
        primary_file_number,
        primary_file_number_norm,
        mlsFNo,
        mlsFNo_norm,
        kangisFileNo,
        kangisFileNo_norm
    FROM dbo.PropID_Master 
    WHERE prop_id = 999999;
    
    -- Clean up test record
    DELETE FROM dbo.PropID_Master WHERE prop_id = 999999;
    
    PRINT '✓ Computed column normalization working correctly'
END TRY
BEGIN CATCH
    PRINT '✗ Error testing computed columns: ' + ERROR_MESSAGE()
END CATCH

PRINT ''

-- 6) Test unique constraints
PRINT '6. Testing unique constraints...'
BEGIN TRY
    -- Test prop_id uniqueness
    INSERT INTO dbo.PropID_Master (prop_id, primary_file_number, source_table, source_record_id)
    VALUES (999998, 'test-unique-1', 'test', 1);
    
    INSERT INTO dbo.PropID_Master (prop_id, primary_file_number, source_table, source_record_id)
    VALUES (999998, 'test-unique-2', 'test', 2);
    
    PRINT '✗ Unique constraint on prop_id not working!'
END TRY
BEGIN CATCH
    IF ERROR_NUMBER() = 2627 AND ERROR_MESSAGE() LIKE '%UQ_PropID_Master_prop_id%'
        PRINT '✓ Unique constraint on prop_id working correctly'
    ELSE
        PRINT '✗ Unexpected error: ' + ERROR_MESSAGE()
END CATCH

-- Clean up any test records
DELETE FROM dbo.PropID_Master WHERE prop_id IN (999998, 999999);

PRINT ''

-- 7) Check source table data availability
PRINT '7. Checking source table data availability...'
DECLARE @fh_count INT, @pra_count INT, @pic_count INT, @cofo_count INT

SELECT @fh_count = COUNT(*) FROM file_history_staging WHERE prop_id IS NOT NULL;
SELECT @pra_count = COUNT(*) FROM pra WHERE prop_id IS NOT NULL;
SELECT @pic_count = COUNT(*) FROM pic WHERE prop_id IS NOT NULL;
SELECT @cofo_count = COUNT(*) FROM CofO_staging WHERE prop_id IS NOT NULL;

PRINT 'Source table records with prop_id:'
PRINT 'file_history_staging: ' + CAST(@fh_count AS VARCHAR(20))
PRINT 'pra: ' + CAST(@pra_count AS VARCHAR(20))
PRINT 'pic: ' + CAST(@pic_count AS VARCHAR(20))
PRINT 'CofO_staging: ' + CAST(@cofo_count AS VARCHAR(20))

PRINT ''

-- 8) Check for conflicts before seeding
PRINT '8. Checking for existing conflicts...'
SELECT TOP 10
    normalized_file_number,
    prop_id_count,
    prop_ids,
    sources
FROM dbo.vw_prop_id_conflicts;

IF @@ROWCOUNT = 0
    PRINT '✓ No conflicts detected'
ELSE
    PRINT '⚠ Conflicts found - review above results before seeding'

PRINT ''

-- 9) Current PropID_Master status
PRINT '9. Current PropID_Master status...'
SELECT 
    COUNT(*) AS total_records,
    COUNT(DISTINCT prop_id) AS unique_prop_ids,
    MIN(created_at) AS earliest_record,
    MAX(created_at) AS latest_record
FROM dbo.PropID_Master;

PRINT ''

-- 10) Sample records
PRINT '10. Sample PropID_Master records...'
SELECT TOP 5
    prop_id,
    primary_file_number,
    mlsFNo,
    source_table,
    created_at
FROM dbo.PropID_Master
ORDER BY created_at DESC;

PRINT ''

-- 11) Cross-validate PropID_Master with source tables
PRINT '11. Cross-validating PropID_Master with source tables...'

-- Create comprehensive view of all source data
WITH all_sources AS (
    SELECT 
        fh.prop_id,
        UPPER(LTRIM(RTRIM(COALESCE(fh.mlsFNo, fh.fileno)))) AS primary_file_number_norm,
        fh.mlsFNo,
        fh.kangisFileNo,
        fh.NewKANGISFileno,
        fh.temp_fileno,
        'file_history_staging' AS source_table,
        fh.id AS source_record_id
    FROM file_history_staging fh
    WHERE fh.prop_id IS NOT NULL
    
    UNION ALL
    SELECT 
        pra.prop_id,
        UPPER(LTRIM(RTRIM(COALESCE(pra.mlsFNo, pra.kangisFileNo, pra.NewKANGISFileno, pra.temp_fileno)))),
        pra.mlsFNo,
        pra.kangisFileNo,
        pra.NewKANGISFileno,
        pra.temp_fileno,
        'pra',
        pra.id
    FROM pra
    WHERE pra.prop_id IS NOT NULL
    
    UNION ALL
    SELECT 
        pic.prop_id,
        UPPER(LTRIM(RTRIM(COALESCE(pic.mlsFNo, pic.kangisFileNo, pic.NewKANGISFileno, pic.temp_fileno)))),
        pic.mlsFNo,
        pic.kangisFileNo,
        pic.NewKANGISFileno,
        pic.temp_fileno,
        'pic',
        pic.id
    FROM pic
    WHERE pic.prop_id IS NOT NULL
    
    UNION ALL
    SELECT 
        co.prop_id,
        UPPER(LTRIM(RTRIM(COALESCE(co.mlsFNo, co.kangisFileNo, co.NewKANGISFileno, co.np_fileno)))),
        co.mlsFNo,
        co.kangisFileNo,
        co.NewKANGISFileno,
        co.np_fileno AS temp_fileno,
        'CofO_staging',
        co.id
    FROM CofO_staging co
    WHERE co.prop_id IS NOT NULL
),
source_summary AS (
    SELECT 
        prop_id,
        STRING_AGG(source_table, ', ') AS source_tables,
        COUNT(*) AS source_count
    FROM all_sources
    GROUP BY prop_id
)

-- A) Props in master but missing from source tables
PRINT '11a. Props in PropID_Master but missing from source tables:'
SELECT TOP 10
    pm.prop_id,
    pm.primary_file_number,
    pm.source_table AS master_source_table
FROM dbo.PropID_Master pm
LEFT JOIN source_summary ss ON pm.prop_id = ss.prop_id
WHERE ss.prop_id IS NULL;

IF @@ROWCOUNT = 0
    PRINT '✓ No orphaned props in master table'
ELSE
    PRINT '⚠ Found props in master without source records'

PRINT ''

-- B) Props in source tables but missing from master
PRINT '11b. Props in source tables but missing from PropID_Master:'
SELECT TOP 10
    ss.prop_id,
    ss.source_tables,
    ss.source_count
FROM source_summary ss
LEFT JOIN dbo.PropID_Master pm ON ss.prop_id = pm.prop_id
WHERE pm.prop_id IS NULL;

IF @@ROWCOUNT = 0
    PRINT '✓ No missing props from master table'
ELSE
    PRINT '⚠ Found props in sources missing from master'

PRINT ''

-- C) File number mismatches between master and sources
PRINT '11c. File number mismatches between master and sources:'
WITH mismatches AS (
    SELECT 
        pm.prop_id,
        pm.primary_file_number_norm AS master_primary,
        aso.primary_file_number_norm AS source_primary,
        pm.source_table AS master_source,
        aso.source_table AS actual_source
    FROM dbo.PropID_Master pm
    JOIN all_sources aso ON pm.prop_id = aso.prop_id
    WHERE pm.primary_file_number_norm != aso.primary_file_number_norm
       OR (pm.source_table != aso.source_table AND pm.source_record_id != aso.source_record_id)
)
SELECT TOP 10 * FROM mismatches;

IF @@ROWCOUNT = 0
    PRINT '✓ No file number mismatches detected'
ELSE
    PRINT '⚠ Found file number mismatches between master and sources'

PRINT ''

-- D) Summary statistics
PRINT '11d. Cross-validation summary:'
SELECT 
    'PropID_Master' AS table_name,
    COUNT(*) AS total_records,
    COUNT(DISTINCT prop_id) AS unique_prop_ids
FROM dbo.PropID_Master

UNION ALL

SELECT 
    'All Sources Combined' AS table_name,
    COUNT(*) AS total_records,
    COUNT(DISTINCT prop_id) AS unique_prop_ids
FROM all_sources;

-- E) Matching percentage
DECLARE @master_count INT, @source_count INT, @match_count INT
SELECT @master_count = COUNT(DISTINCT prop_id) FROM dbo.PropID_Master
SELECT @source_count = COUNT(DISTINCT prop_id) FROM all_sources
SELECT @match_count = COUNT(DISTINCT pm.prop_id) 
FROM dbo.PropID_Master pm
JOIN source_summary ss ON pm.prop_id = ss.prop_id

PRINT ''
PRINT 'Match Summary:'
PRINT 'Master prop_ids: ' + CAST(@master_count AS VARCHAR(20))
PRINT 'Source prop_ids: ' + CAST(@source_count AS VARCHAR(20))  
PRINT 'Matching prop_ids: ' + CAST(@match_count AS VARCHAR(20))
PRINT 'Match percentage: ' + CAST(ROUND((@match_count * 100.0 / NULLIF(@source_count, 0)), 2) AS VARCHAR(20)) + '%'

PRINT ''
PRINT '=== Validation Complete ==='
PRINT ''
PRINT 'Next Steps:'
PRINT '1. If no errors above, the system is ready'
PRINT '2. To seed data, run the MERGE statement from prop_id_master_table.sql'
PRINT '3. After seeding, re-run this validation script to confirm data integrity'
PRINT '4. Monitor vw_prop_id_conflicts view regularly for ongoing data quality'
PRINT '5. Investigate any ⚠ warnings found in cross-validation'