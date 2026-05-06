-- Add performance indexes for print label queries
-- Run this SQL against your SQL Server database to improve print label performance

-- Index for file_indexings queries used in print labels
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'idx_file_indexings_print_labels' AND object_id = OBJECT_ID('file_indexings'))
BEGIN
    CREATE NONCLUSTERED INDEX [idx_file_indexings_print_labels] 
    ON [dbo].[file_indexings] ([is_deleted], [batch_no], [subapplication_id], [main_application_id])
    INCLUDE ([id], [file_number], [file_title], [plot_number], [district], [lga], [land_use_type], 
             [shelf_location], [tracking_id], [sys_batch_no], [st_fillno])
    WHERE ([is_deleted] IS NULL OR [is_deleted] = 0);
    PRINT 'Created index: idx_file_indexings_print_labels';
END
ELSE
BEGIN
    PRINT 'Index idx_file_indexings_print_labels already exists';
END

-- Index for grouping table queries
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'idx_grouping_sys_batch_awaiting' AND object_id = OBJECT_ID('grouping'))
BEGIN
    CREATE NONCLUSTERED INDEX [idx_grouping_sys_batch_awaiting] 
    ON [dbo].[grouping] ([sys_batch_no], [awaiting_fileno])
    INCLUDE ([id], [tracking_id], [mls_fileno], [landuse], [batch_no]);
    PRINT 'Created index: idx_grouping_sys_batch_awaiting';
END
ELSE
BEGIN
    PRINT 'Index idx_grouping_sys_batch_awaiting already exists';
END

-- Index for print_label_batch_items to check existing labels
IF EXISTS (SELECT * FROM sys.tables WHERE name = 'print_label_batch_items')
BEGIN
    IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'idx_print_label_batch_items_file_indexing' AND object_id = OBJECT_ID('print_label_batch_items'))
    BEGIN
        CREATE NONCLUSTERED INDEX [idx_print_label_batch_items_file_indexing] 
        ON [dbo].[print_label_batch_items] ([file_indexing_id], [batch_id]);
        PRINT 'Created index: idx_print_label_batch_items_file_indexing';
    END
    ELSE
    BEGIN
        PRINT 'Index idx_print_label_batch_items_file_indexing already exists';
    END
END

PRINT 'Print label performance indexes setup complete';