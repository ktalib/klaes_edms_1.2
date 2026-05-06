-- Indexed Files performance improvements (created by GitHub Copilot on 2025-11-06)
-- Adds covering indexes used by FileIndexController::getIndexedFiles API

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_file_indexings_active_batch_created'
      AND object_id = OBJECT_ID('dbo.file_indexings')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_file_indexings_active_batch_created
        ON dbo.file_indexings (is_deleted, batch_generated, created_at DESC)
        INCLUDE (
            id,
            file_number,
            file_title,
            plot_number,
            district,
            lga,
            registry,
            batch_no,
            sys_batch_no,
            group_no,
            shelf_location,
            tracking_id,
            land_use_type,
            has_cofo,
            has_transaction,
            is_problematic,
            is_co_owned_plot,
            batch_generated_at
        );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_file_indexings_file_number_search'
      AND object_id = OBJECT_ID('dbo.file_indexings')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_file_indexings_file_number_search
        ON dbo.file_indexings (file_number)
        INCLUDE (
            file_title,
            plot_number,
            district,
            lga,
            registry,
            batch_no,
            created_at
        );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_grouping_awaiting_fileno'
      AND object_id = OBJECT_ID('dbo.grouping')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_grouping_awaiting_fileno
        ON dbo.grouping (awaiting_fileno)
        INCLUDE (
            registry,
            batch_no,
            sys_batch_no,
            [group],
            shelf_rack,
            landuse,
            date_index,
            indexed_by
        );
END;
GO
