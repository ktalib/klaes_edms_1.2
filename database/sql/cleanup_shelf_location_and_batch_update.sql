-- =====================================================================================
-- CLEAN UP SHELF_LOCATION AND UPDATE FILEINDEXING_BATCH STATISTICS
-- =====================================================================================
-- This script will:
-- 1. Update shelf_location in file_indexings with full_label from Rack_Shelf_Labels
-- 2. Update fileindexing_batch statistics based on actual usage
-- 3. Handle any inconsistencies in the data
-- =====================================================================================

BEGIN TRANSACTION;

BEGIN TRY
    PRINT '=== STARTING SHELF_LOCATION CLEANUP AND BATCH UPDATE ==='
    PRINT 'Timestamp: ' + CONVERT(VARCHAR, GETDATE(), 120)
    PRINT ''

    -- ==========================================
    -- STEP 1: BACKUP CURRENT STATE (Optional)
    -- ==========================================
    PRINT 'Step 1: Creating backup snapshots...'
    
    -- Get initial counts
    DECLARE @TotalFileIndexings INT = (SELECT COUNT(*) FROM dbo.file_indexings)
    DECLARE @FileIndexingsWithShelfId INT = (SELECT COUNT(*) FROM dbo.file_indexings WHERE shelf_label_id IS NOT NULL)
    DECLARE @FileIndexingsWithLocation INT = (SELECT COUNT(*) FROM dbo.file_indexings WHERE shelf_location IS NOT NULL AND shelf_location != '')
    DECLARE @TotalBatches INT = (SELECT COUNT(*) FROM dbo.fileindexing_batch)

    PRINT '  - Total file_indexings records: ' + CAST(@TotalFileIndexings AS VARCHAR)
    PRINT '  - Records with shelf_label_id: ' + CAST(@FileIndexingsWithShelfId AS VARCHAR)
    PRINT '  - Records with shelf_location: ' + CAST(@FileIndexingsWithLocation AS VARCHAR)
    PRINT '  - Total batches: ' + CAST(@TotalBatches AS VARCHAR)
    PRINT ''

    -- ==========================================
    -- STEP 2: UPDATE SHELF_LOCATION FROM FULL_LABEL
    -- ==========================================
    PRINT 'Step 2: Updating shelf_location with full_label from Rack_Shelf_Labels...'
    
    -- Update shelf_location where shelf_label_id exists but shelf_location is missing or incorrect
    UPDATE fi
    SET fi.shelf_location = rsl.full_label,
        fi.updated_at = GETDATE()
    FROM dbo.file_indexings AS fi
    INNER JOIN dbo.Rack_Shelf_Labels AS rsl 
        ON fi.shelf_label_id = rsl.id
    WHERE fi.shelf_label_id IS NOT NULL
      AND (fi.shelf_location IS NULL 
           OR fi.shelf_location = '' 
           OR fi.shelf_location != rsl.full_label);

    DECLARE @UpdatedShelfLocations INT = @@ROWCOUNT;
    PRINT '  - Updated shelf_location for ' + CAST(@UpdatedShelfLocations AS VARCHAR) + ' records'

    -- ==========================================
    -- STEP 3: CLEAR ORPHANED SHELF_LOCATION DATA
    -- ==========================================
    PRINT 'Step 3: Clearing orphaned shelf_location data...'
    
    -- Clear shelf_location where shelf_label_id is NULL (orphaned data)
    UPDATE dbo.file_indexings
    SET shelf_location = NULL,
        updated_at = GETDATE()
    WHERE shelf_label_id IS NULL 
      AND shelf_location IS NOT NULL 
      AND shelf_location != '';

    DECLARE @ClearedOrphaned INT = @@ROWCOUNT;
    PRINT '  - Cleared orphaned shelf_location for ' + CAST(@ClearedOrphaned AS VARCHAR) + ' records'

    -- ==========================================
    -- STEP 4: UPDATE RACK_SHELF_LABELS IS_USED FLAG
    -- ==========================================
    PRINT 'Step 4: Updating Rack_Shelf_Labels is_used flags...'
    
    -- Mark shelves as used if they're assigned to files
    UPDATE rsl
    SET rsl.is_used = 1
    FROM dbo.Rack_Shelf_Labels AS rsl
    INNER JOIN dbo.file_indexings AS fi 
        ON fi.shelf_label_id = rsl.id
    WHERE rsl.is_used != 1 OR rsl.is_used IS NULL;

    DECLARE @MarkedUsed INT = @@ROWCOUNT;
    PRINT '  - Marked ' + CAST(@MarkedUsed AS VARCHAR) + ' shelves as used'

    -- Mark shelves as unused if they're not assigned to any files
    UPDATE rsl
    SET rsl.is_used = 0
    FROM dbo.Rack_Shelf_Labels AS rsl
    LEFT JOIN dbo.file_indexings AS fi 
        ON fi.shelf_label_id = rsl.id
    WHERE fi.shelf_label_id IS NULL 
      AND (rsl.is_used = 1 OR rsl.is_used IS NULL);

    DECLARE @MarkedUnused INT = @@ROWCOUNT;
    PRINT '  - Marked ' + CAST(@MarkedUnused AS VARCHAR) + ' shelves as unused'

    -- ==========================================
    -- STEP 5: UPDATE FILEINDEXING_BATCH STATISTICS
    -- ==========================================
    PRINT 'Step 5: Updating fileindexing_batch statistics...'
    
    -- Update used_shelves count for each batch based on actual assignments
    WITH batch_usage AS (
        SELECT 
            fb.id AS batch_id,
            fb.batch_number,
            fb.start_shelf_id,
            fb.end_shelf_id,
            fb.shelf_count,
            COUNT(fi.id) AS actual_used_shelves
        FROM dbo.fileindexing_batch AS fb
        LEFT JOIN dbo.file_indexings AS fi 
            ON fi.shelf_label_id BETWEEN fb.start_shelf_id AND fb.end_shelf_id
        GROUP BY fb.id, fb.batch_number, fb.start_shelf_id, fb.end_shelf_id, fb.shelf_count
    )
    UPDATE fb
    SET fb.used_shelves = bu.actual_used_shelves,
        fb.is_full = CASE 
            WHEN bu.actual_used_shelves >= fb.shelf_count THEN 1 
            ELSE 0 
        END,
        fb.updated_at = GETDATE()
    FROM dbo.fileindexing_batch AS fb
    INNER JOIN batch_usage AS bu ON bu.batch_id = fb.id
    WHERE fb.used_shelves != bu.actual_used_shelves 
       OR fb.is_full != CASE WHEN bu.actual_used_shelves >= fb.shelf_count THEN 1 ELSE 0 END;

    DECLARE @UpdatedBatches INT = @@ROWCOUNT;
    PRINT '  - Updated statistics for ' + CAST(@UpdatedBatches AS VARCHAR) + ' batches'

    -- ==========================================
    -- STEP 6: ADD BATCH_ID TO FILE_INDEXINGS
    -- ==========================================
    PRINT 'Step 6: Assigning batch_id to file_indexings records...'
    
    -- Update batch_id in file_indexings based on shelf_label_id ranges
    UPDATE fi
    SET fi.batch_id = fb.id
    FROM dbo.file_indexings AS fi
    INNER JOIN dbo.fileindexing_batch AS fb
        ON fi.shelf_label_id BETWEEN fb.start_shelf_id AND fb.end_shelf_id
    WHERE fi.shelf_label_id IS NOT NULL 
      AND fi.batch_id IS NULL;

    DECLARE @AssignedBatchIds INT = @@ROWCOUNT;
    PRINT '  - Assigned batch_id to ' + CAST(@AssignedBatchIds AS VARCHAR) + ' records'

    -- ==========================================
    -- STEP 7: VALIDATION AND FINAL REPORT
    -- ==========================================
    PRINT 'Step 7: Validation and final report...'
    PRINT ''

    -- Get final counts
    DECLARE @FinalFileIndexingsWithLocation INT = (SELECT COUNT(*) FROM dbo.file_indexings WHERE shelf_location IS NOT NULL AND shelf_location != '')
    DECLARE @FinalFileIndexingsWithBatch INT = (SELECT COUNT(*) FROM dbo.file_indexings WHERE batch_id IS NOT NULL)
    DECLARE @FinalUsedShelves INT = (SELECT COUNT(*) FROM dbo.Rack_Shelf_Labels WHERE is_used = 1)
    DECLARE @FinalBatchUsage INT = (SELECT SUM(used_shelves) FROM dbo.fileindexing_batch)

    -- Validation checks
    PRINT '=== VALIDATION RESULTS ==='
    PRINT 'Records with valid shelf_location: ' + CAST(@FinalFileIndexingsWithLocation AS VARCHAR)
    PRINT 'Records with batch_id assigned: ' + CAST(@FinalFileIndexingsWithBatch AS VARCHAR)
    PRINT 'Shelf labels marked as used: ' + CAST(@FinalUsedShelves AS VARCHAR)
    PRINT 'Total batch usage count: ' + CAST(@FinalBatchUsage AS VARCHAR)
    PRINT ''

    -- Check for inconsistencies
    DECLARE @Inconsistencies INT = 0;

    -- Check: Records with shelf_label_id but no shelf_location
    DECLARE @MissingLocation INT = (
        SELECT COUNT(*) 
        FROM dbo.file_indexings 
        WHERE shelf_label_id IS NOT NULL 
          AND (shelf_location IS NULL OR shelf_location = '')
    )
    IF @MissingLocation > 0 BEGIN
        PRINT 'WARNING: ' + CAST(@MissingLocation AS VARCHAR) + ' records have shelf_label_id but missing shelf_location'
        SET @Inconsistencies = @Inconsistencies + 1
    END

    -- Check: Records with shelf_location but no shelf_label_id
    DECLARE @OrphanedLocation INT = (
        SELECT COUNT(*) 
        FROM dbo.file_indexings 
        WHERE shelf_label_id IS NULL 
          AND shelf_location IS NOT NULL 
          AND shelf_location != ''
    )
    IF @OrphanedLocation > 0 BEGIN
        PRINT 'WARNING: ' + CAST(@OrphanedLocation AS VARCHAR) + ' records have shelf_location but missing shelf_label_id'
        SET @Inconsistencies = @Inconsistencies + 1
    END

    -- Check: Batch usage consistency
    DECLARE @BatchInconsistency INT = (
        SELECT COUNT(*)
        FROM dbo.fileindexing_batch fb
        WHERE fb.used_shelves != (
            SELECT COUNT(fi.id)
            FROM dbo.file_indexings fi
            WHERE fi.shelf_label_id BETWEEN fb.start_shelf_id AND fb.end_shelf_id
        )
    )
    IF @BatchInconsistency > 0 BEGIN
        PRINT 'WARNING: ' + CAST(@BatchInconsistency AS VARCHAR) + ' batches have inconsistent usage counts'
        SET @Inconsistencies = @Inconsistencies + 1
    END

    -- ==========================================
    -- STEP 8: DETAILED BATCH REPORT
    -- ==========================================
    PRINT ''
    PRINT '=== BATCH SUMMARY REPORT ==='
    
    SELECT 
        fb.batch_number,
        fb.shelf_count,
        fb.used_shelves,
        (fb.shelf_count - fb.used_shelves) AS available_shelves,
        CAST((fb.used_shelves * 100.0 / fb.shelf_count) AS DECIMAL(5,2)) AS usage_percent,
        CASE 
            WHEN fb.is_full = 1 THEN 'FULL'
            WHEN fb.is_active = 0 THEN 'INACTIVE' 
            ELSE 'ACTIVE' 
        END AS status,
        fb.start_shelf_id,
        fb.end_shelf_id
    FROM dbo.fileindexing_batch fb
    ORDER BY fb.batch_number;

    -- ==========================================
    -- FINAL STATUS
    -- ==========================================
    PRINT ''
    PRINT '=== CLEANUP COMPLETED SUCCESSFULLY ==='
    PRINT 'Summary of changes:'
    PRINT '  - Updated shelf_location: ' + CAST(@UpdatedShelfLocations AS VARCHAR) + ' records'
    PRINT '  - Cleared orphaned data: ' + CAST(@ClearedOrphaned AS VARCHAR) + ' records'
    PRINT '  - Updated batch statistics: ' + CAST(@UpdatedBatches AS VARCHAR) + ' batches'
    PRINT '  - Assigned batch_ids: ' + CAST(@AssignedBatchIds AS VARCHAR) + ' records'
    PRINT '  - Marked shelves as used: ' + CAST(@MarkedUsed AS VARCHAR) + ' shelves'
    PRINT '  - Marked shelves as unused: ' + CAST(@MarkedUnused AS VARCHAR) + ' shelves'
    
    IF @Inconsistencies = 0 BEGIN
        PRINT ''
        PRINT '✅ NO INCONSISTENCIES FOUND - DATA IS CLEAN!'
    END ELSE BEGIN
        PRINT ''
        PRINT '⚠️  ' + CAST(@Inconsistencies AS VARCHAR) + ' INCONSISTENCIES FOUND - PLEASE REVIEW WARNINGS ABOVE'
    END
    
    PRINT ''
    PRINT 'Timestamp: ' + CONVERT(VARCHAR, GETDATE(), 120)
    PRINT '=== SCRIPT EXECUTION COMPLETED ==='

    -- Commit the transaction
    COMMIT TRANSACTION;
    PRINT ''
    PRINT '✅ TRANSACTION COMMITTED SUCCESSFULLY'

END TRY
BEGIN CATCH
    -- Rollback on error
    ROLLBACK TRANSACTION;
    
    PRINT ''
    PRINT '❌ ERROR OCCURRED - TRANSACTION ROLLED BACK'
    PRINT 'Error Number: ' + CAST(ERROR_NUMBER() AS VARCHAR)
    PRINT 'Error Message: ' + ERROR_MESSAGE()
    PRINT 'Error Line: ' + CAST(ERROR_LINE() AS VARCHAR)
    PRINT 'Error Procedure: ' + ISNULL(ERROR_PROCEDURE(), 'N/A')
    
    -- Re-throw the error
    THROW;
END CATCH;