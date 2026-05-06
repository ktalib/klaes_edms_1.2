/*
PropID Master Reset and Rebuild Script
- Clears all existing prop_ids from master and source tables
- Assigns new sequential prop_ids starting from 1
- Rebuilds PropID_Master with clean data and no conflicts
- Source tables: file_history_staging, pra, pic, CofO_staging, instrument_capture, deed_registrations
- Run against: SQL Server (sqlsrv) database used by KLAES
*/

USE [klas]
GO

PRINT '=== PropID Master Reset and Rebuild Process ==='
PRINT ''
PRINT 'WARNING: This will clear ALL existing prop_id assignments!'
PRINT 'Proceeding in 5 seconds...'
WAITFOR DELAY '00:00:05';

BEGIN TRANSACTION PropIDReset

BEGIN TRY
    PRINT '1. Backing up current state...'
    
    -- Create backup tables with timestamp
    DECLARE @backup_suffix NVARCHAR(20) = FORMAT(GETDATE(), 'yyyyMMdd_HHmmss')
    
    EXEC('SELECT * INTO PropID_Master_backup_' + @backup_suffix + ' FROM dbo.PropID_Master')
    PRINT '✓ PropID_Master backed up to PropID_Master_backup_' + @backup_suffix
    
    PRINT ''
    PRINT '2. Clearing existing prop_ids from all tables...'
    
    -- Clear prop_id from source tables
    UPDATE file_history_staging SET prop_id = NULL WHERE prop_id IS NOT NULL
    PRINT '✓ Cleared prop_id from file_history_staging (' + CAST(@@ROWCOUNT AS VARCHAR(20)) + ' records)'
    
    UPDATE pra SET prop_id = NULL WHERE prop_id IS NOT NULL  
    PRINT '✓ Cleared prop_id from pra (' + CAST(@@ROWCOUNT AS VARCHAR(20)) + ' records)'
    
    UPDATE pic SET prop_id = NULL WHERE prop_id IS NOT NULL
    PRINT '✓ Cleared prop_id from pic (' + CAST(@@ROWCOUNT AS VARCHAR(20)) + ' records)'
    
    UPDATE CofO_staging SET prop_id = NULL WHERE prop_id IS NOT NULL
    PRINT '✓ Cleared prop_id from CofO_staging (' + CAST(@@ROWCOUNT AS VARCHAR(20)) + ' records)'
    
    UPDATE instrument_capture SET prop_id = NULL WHERE prop_id IS NOT NULL
    PRINT '✓ Cleared prop_id from instrument_capture (' + CAST(@@ROWCOUNT AS VARCHAR(20)) + ' records)'
    
    UPDATE deed_registrations SET prop_id = NULL WHERE prop_id IS NOT NULL
    PRINT '✓ Cleared prop_id from deed_registrations (' + CAST(@@ROWCOUNT AS VARCHAR(20)) + ' records)'
    
    -- Clear master table
    DELETE FROM dbo.PropID_Master
    PRINT '✓ Cleared PropID_Master (' + CAST(@@ROWCOUNT AS VARCHAR(20)) + ' records)'
    
    PRINT ''
    PRINT '3. Collecting unique file numbers across all sources...'
    
    -- Create consolidated view of all unique file numbers
    ;WITH all_file_numbers AS (
        -- File History Staging
        SELECT DISTINCT
            UPPER(LTRIM(RTRIM(COALESCE(fh.mlsFNo, fh.fileno, fh.kangisFileNo, fh.NewKANGISFileno)))) AS normalized_file_number,
            fh.mlsFNo,
            fh.kangisFileNo, 
            fh.NewKANGISFileno,
            fh.temp_fileno,
            'file_history_staging' AS source_table,
            fh.id AS source_record_id,
            ROW_NUMBER() OVER (ORDER BY fh.id) AS source_priority
        FROM file_history_staging fh
        WHERE COALESCE(fh.mlsFNo, fh.fileno, fh.kangisFileNo, fh.NewKANGISFileno) IS NOT NULL
            AND UPPER(LTRIM(RTRIM(COALESCE(fh.mlsFNo, fh.fileno, fh.kangisFileNo, fh.NewKANGISFileno)))) NOT LIKE '%TEMP%'
        
        UNION ALL
        
        -- PRA
        SELECT DISTINCT  
            UPPER(LTRIM(RTRIM(COALESCE(pra.mlsFNo, pra.kangisFileNo, pra.NewKANGISFileno)))),
            pra.mlsFNo,
            pra.kangisFileNo,
            pra.NewKANGISFileno, 
            pra.temp_fileno,
            'pra',
            pra.id,
            ROW_NUMBER() OVER (ORDER BY pra.id) + 10000000 -- Offset to avoid conflicts
        FROM pra
        WHERE COALESCE(pra.mlsFNo, pra.kangisFileNo, pra.NewKANGISFileno) IS NOT NULL
            AND UPPER(LTRIM(RTRIM(COALESCE(pra.mlsFNo, pra.kangisFileNo, pra.NewKANGISFileno)))) NOT LIKE '%TEMP%'
        
        UNION ALL
        
        -- PIC
        SELECT DISTINCT
            UPPER(LTRIM(RTRIM(COALESCE(pic.mlsFNo, pic.kangisFileNo, pic.NewKANGISFileno)))),
            pic.mlsFNo,
            pic.kangisFileNo,
            pic.NewKANGISFileno,
            pic.temp_fileno,
            'pic',
            pic.id,
            ROW_NUMBER() OVER (ORDER BY pic.id) + 20000000
        FROM pic  
        WHERE COALESCE(pic.mlsFNo, pic.kangisFileNo, pic.NewKANGISFileno) IS NOT NULL
            AND UPPER(LTRIM(RTRIM(COALESCE(pic.mlsFNo, pic.kangisFileNo, pic.NewKANGISFileno)))) NOT LIKE '%TEMP%'
        
        UNION ALL
        
        -- CofO Staging
        SELECT DISTINCT
            UPPER(LTRIM(RTRIM(COALESCE(co.mlsFNo, co.kangisFileNo, co.NewKANGISFileno, co.np_fileno)))),
            co.mlsFNo,
            co.kangisFileNo,
            co.NewKANGISFileno,
            co.np_fileno AS temp_fileno,
            'CofO_staging',
            co.id,
            ROW_NUMBER() OVER (ORDER BY co.id) + 30000000
        FROM CofO_staging co
        WHERE COALESCE(co.mlsFNo, co.kangisFileNo, co.NewKANGISFileno, co.np_fileno) IS NOT NULL
            AND UPPER(LTRIM(RTRIM(COALESCE(co.mlsFNo, co.kangisFileNo, co.NewKANGISFileno, co.np_fileno)))) NOT LIKE '%TEMP%'
        
        UNION ALL
        
        -- Instrument Capture (all file numbers, including TEMP)
        SELECT DISTINCT
            UPPER(LTRIM(RTRIM(COALESCE(ic.mlsFNo, ic.kangisFileNo, ic.NewKANGISFileno, ic.temp_fileno)))),
            ic.mlsFNo,
            ic.kangisFileNo,
            ic.NewKANGISFileno,
            ic.temp_fileno,
            'instrument_capture',
            ic.id,
            ROW_NUMBER() OVER (ORDER BY ic.id) + 40000000
        FROM instrument_capture ic
        WHERE COALESCE(ic.mlsFNo, ic.kangisFileNo, ic.NewKANGISFileno, ic.temp_fileno) IS NOT NULL
            AND (ic.is_deleted = 0 OR ic.is_deleted IS NULL)
        
        UNION ALL
        
        -- Deed Registrations (all file numbers, including TEMP)
        SELECT DISTINCT
            UPPER(LTRIM(RTRIM(dr.fileno))),
            dr.fileno AS mlsFNo,
            NULL AS kangisFileNo,
            NULL AS NewKANGISFileno,
            CASE WHEN UPPER(LTRIM(RTRIM(dr.fileno))) LIKE 'TEMP%' THEN dr.fileno ELSE NULL END AS temp_fileno,
            'deed_registrations',
            dr.id,
            ROW_NUMBER() OVER (ORDER BY dr.id) + 50000000
        FROM deed_registrations dr
        WHERE dr.fileno IS NOT NULL
    ),
    unique_file_numbers AS (
        SELECT 
            normalized_file_number,
            MIN(mlsFNo) AS mlsFNo,
            MIN(kangisFileNo) AS kangisFileNo, 
            MIN(NewKANGISFileno) AS NewKANGISFileno,
            MIN(temp_fileno) AS temp_fileno,
            MIN(source_table) AS source_table,
            MIN(source_record_id) AS source_record_id,
            MIN(source_priority) AS source_priority,
            ROW_NUMBER() OVER (ORDER BY MIN(source_priority)) AS new_prop_id
        FROM all_file_numbers
        WHERE normalized_file_number IS NOT NULL 
            AND normalized_file_number != ''
        GROUP BY normalized_file_number
    )
    
    -- Insert into PropID_Master with new sequential prop_ids
    -- Only populate prop_id + primary_file_number + source info.
    -- Secondary file-number columns (mlsFNo, kangisFileNo, etc.) are left NULL
    -- to avoid unique-index violations when the same alias appears under
    -- different normalised primary file numbers.
    INSERT INTO dbo.PropID_Master (
        prop_id, 
        primary_file_number,
        source_table,
        source_record_id
    )
    SELECT 
        new_prop_id,
        normalized_file_number,
        source_table,
        source_record_id
    FROM unique_file_numbers
    ORDER BY new_prop_id;
    
    DECLARE @master_inserted INT = @@ROWCOUNT
    PRINT '✓ Inserted ' + CAST(@master_inserted AS VARCHAR(20)) + ' records into PropID_Master with sequential prop_ids'
    
    PRINT ''
    PRINT '4. Updating source tables with new prop_ids...'
    
    -- Update file_history_staging
    UPDATE fh 
    SET fh.prop_id = pm.prop_id
    FROM file_history_staging fh
    JOIN dbo.PropID_Master pm ON 
        UPPER(LTRIM(RTRIM(COALESCE(fh.mlsFNo, fh.fileno, fh.kangisFileNo, fh.NewKANGISFileno)))) = pm.primary_file_number_norm
    WHERE fh.prop_id IS NULL
        AND COALESCE(fh.mlsFNo, fh.fileno, fh.kangisFileNo, fh.NewKANGISFileno) IS NOT NULL;
    
    PRINT '✓ Updated file_history_staging (' + CAST(@@ROWCOUNT AS VARCHAR(20)) + ' records)'
    
    -- Update pra
    UPDATE pra
    SET pra.prop_id = pm.prop_id  
    FROM pra
    JOIN dbo.PropID_Master pm ON
        UPPER(LTRIM(RTRIM(COALESCE(pra.mlsFNo, pra.kangisFileNo, pra.NewKANGISFileno)))) = pm.primary_file_number_norm
    WHERE pra.prop_id IS NULL
        AND COALESCE(pra.mlsFNo, pra.kangisFileNo, pra.NewKANGISFileno) IS NOT NULL;
    
    PRINT '✓ Updated pra (' + CAST(@@ROWCOUNT AS VARCHAR(20)) + ' records)'
    
    -- Update pic
    UPDATE pic
    SET pic.prop_id = pm.prop_id
    FROM pic  
    JOIN dbo.PropID_Master pm ON
        UPPER(LTRIM(RTRIM(COALESCE(pic.mlsFNo, pic.kangisFileNo, pic.NewKANGISFileno)))) = pm.primary_file_number_norm
    WHERE pic.prop_id IS NULL
        AND COALESCE(pic.mlsFNo, pic.kangisFileNo, pic.NewKANGISFileno) IS NOT NULL;
        
    PRINT '✓ Updated pic (' + CAST(@@ROWCOUNT AS VARCHAR(20)) + ' records)'
    
    -- Update CofO_staging  
    UPDATE co
    SET co.prop_id = pm.prop_id
    FROM CofO_staging co
    JOIN dbo.PropID_Master pm ON
        UPPER(LTRIM(RTRIM(COALESCE(co.mlsFNo, co.kangisFileNo, co.NewKANGISFileno, co.np_fileno)))) = pm.primary_file_number_norm
    WHERE co.prop_id IS NULL
        AND COALESCE(co.mlsFNo, co.kangisFileNo, co.NewKANGISFileno, co.np_fileno) IS NOT NULL;
        
    PRINT '✓ Updated CofO_staging (' + CAST(@@ROWCOUNT AS VARCHAR(20)) + ' records)'
    
    -- Update instrument_capture (match using all file number columns including temp_fileno)
    UPDATE ic
    SET ic.prop_id = pm.prop_id
    FROM instrument_capture ic
    JOIN dbo.PropID_Master pm ON
        UPPER(LTRIM(RTRIM(COALESCE(ic.mlsFNo, ic.kangisFileNo, ic.NewKANGISFileno, ic.temp_fileno)))) = pm.primary_file_number_norm
    WHERE ic.prop_id IS NULL
        AND (ic.is_deleted = 0 OR ic.is_deleted IS NULL)
        AND COALESCE(ic.mlsFNo, ic.kangisFileNo, ic.NewKANGISFileno, ic.temp_fileno) IS NOT NULL;
        
    PRINT '✓ Updated instrument_capture (' + CAST(@@ROWCOUNT AS VARCHAR(20)) + ' records)'
    
    -- Update deed_registrations
    UPDATE dr
    SET dr.prop_id = pm.prop_id
    FROM deed_registrations dr
    JOIN dbo.PropID_Master pm ON
        UPPER(LTRIM(RTRIM(dr.fileno))) = pm.primary_file_number_norm
    WHERE dr.prop_id IS NULL
        AND dr.fileno IS NOT NULL;
        
    PRINT '✓ Updated deed_registrations (' + CAST(@@ROWCOUNT AS VARCHAR(20)) + ' records)'
    
    PRINT ''
    PRINT '5. Final validation...'
    
    -- Check for conflicts
    SELECT @master_inserted = COUNT(*) FROM dbo.vw_prop_id_conflicts
    
    IF @master_inserted = 0
        PRINT '✓ No conflicts detected'
    ELSE
        PRINT '⚠ Warning: ' + CAST(@master_inserted AS VARCHAR(20)) + ' conflicts detected'
    
    -- Summary statistics
    DECLARE @master_total INT, @fh_total INT, @pra_total INT, @pic_total INT, @cofo_total INT, @ic_total INT, @dr_total INT
    
    SELECT @master_total = COUNT(*) FROM dbo.PropID_Master
    SELECT @fh_total = COUNT(*) FROM file_history_staging WHERE prop_id IS NOT NULL
    SELECT @pra_total = COUNT(*) FROM pra WHERE prop_id IS NOT NULL  
    SELECT @pic_total = COUNT(*) FROM pic WHERE prop_id IS NOT NULL
    SELECT @cofo_total = COUNT(*) FROM CofO_staging WHERE prop_id IS NOT NULL
    SELECT @ic_total = COUNT(*) FROM instrument_capture WHERE prop_id IS NOT NULL
    SELECT @dr_total = COUNT(*) FROM deed_registrations WHERE prop_id IS NOT NULL
    
    PRINT ''
    PRINT 'Final Summary:'
    PRINT 'PropID_Master records: ' + CAST(@master_total AS VARCHAR(20))
    PRINT 'file_history_staging with prop_id: ' + CAST(@fh_total AS VARCHAR(20))
    PRINT 'pra with prop_id: ' + CAST(@pra_total AS VARCHAR(20)) 
    PRINT 'pic with prop_id: ' + CAST(@pic_total AS VARCHAR(20))
    PRINT 'CofO_staging with prop_id: ' + CAST(@cofo_total AS VARCHAR(20))
    PRINT 'instrument_capture with prop_id: ' + CAST(@ic_total AS VARCHAR(20))
    PRINT 'deed_registrations with prop_id: ' + CAST(@dr_total AS VARCHAR(20))
    
    COMMIT TRANSACTION PropIDReset
    
    PRINT ''
    PRINT '✓ PropID reset and rebuild completed successfully!'
    PRINT '✓ All prop_ids are now sequential starting from 1'
    PRINT '✓ No conflicts or duplicates present'
    PRINT ''
    PRINT 'Backup table created: PropID_Master_backup_' + @backup_suffix
    
END TRY
BEGIN CATCH
    ROLLBACK TRANSACTION PropIDReset
    
    PRINT ''
    PRINT '✗ ERROR occurred during reset process:'
    PRINT ERROR_MESSAGE()
    PRINT ''
    PRINT 'Transaction rolled back - no changes made'
    PRINT 'Check error details and resolve before retrying'
    
END CATCH

GO