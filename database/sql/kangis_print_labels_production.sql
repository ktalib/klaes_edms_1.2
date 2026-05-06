-- ============================================================================
-- KANGIS Print File Labels — Production SQL
-- Run against SQL Server (sqlsrv) database
-- Date: 2026-04-14
-- ============================================================================

-- ============================================================================
-- 1. kangis_print_label_batches
-- ============================================================================
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'kangis_print_label_batches')
BEGIN
    CREATE TABLE kangis_print_label_batches (
        id              BIGINT IDENTITY(1,1) PRIMARY KEY,
        batch_number    NVARCHAR(100)  NOT NULL,
        prefix          NVARCHAR(20)   NULL,           -- KNML | MNKL | MLKN | KNGP
        sys_batch_no    INT            NULL,           -- sys_batch_no from kangis_grouping (unique per prefix)
        batch_size      INT            NOT NULL DEFAULT 0,
        generated_count INT            NOT NULL DEFAULT 0,
        status          NVARCHAR(30)   NOT NULL DEFAULT 'pending',  -- pending|generated|printed|completed
        full_label      NVARCHAR(30)   NULL,           -- Shelf label e.g. A1
        rack_primary    NVARCHAR(10)   NULL,
        rack_secondary  NVARCHAR(10)   NULL,
        shelf_number    INT            NULL,
        created_by      BIGINT         NULL,
        updated_by      BIGINT         NULL,
        printed_at      DATETIME2      NULL,
        created_at      DATETIME2      NULL DEFAULT GETUTCDATE(),
        updated_at      DATETIME2      NULL DEFAULT GETUTCDATE(),

        CONSTRAINT uq_kangis_batch_number       UNIQUE (batch_number),
        CONSTRAINT uq_kangis_batch_prefix_sysbatchno UNIQUE (prefix, sys_batch_no)
    );

    CREATE INDEX ix_kangis_batches_prefix     ON kangis_print_label_batches (prefix);
    CREATE INDEX ix_kangis_batches_status     ON kangis_print_label_batches (status);
    CREATE INDEX ix_kangis_batches_created_at ON kangis_print_label_batches (created_at);

    PRINT 'Created table: kangis_print_label_batches';
END
ELSE
    PRINT 'Table kangis_print_label_batches already exists — skipped.';
GO


-- ============================================================================
-- 2. kangis_print_label_batch_items
-- ============================================================================
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'kangis_print_label_batch_items')
BEGIN
    CREATE TABLE kangis_print_label_batch_items (
        id               BIGINT IDENTITY(1,1) PRIMARY KEY,
        batch_id         BIGINT         NOT NULL,
        file_indexing_id BIGINT         NULL,
        file_number      NVARCHAR(100)  NULL,
        prefix           NVARCHAR(20)   NULL,
        file_title       NVARCHAR(255)  NULL,
        plot_number      NVARCHAR(100)  NULL,
        district         NVARCHAR(100)  NULL,
        lga              NVARCHAR(100)  NULL,
        land_use_type    NVARCHAR(100)  NULL,
        shelf_location   NVARCHAR(50)   NULL,
        qr_code_data     NVARCHAR(MAX)  NULL,
        barcode_data     NVARCHAR(200)  NULL,
        label_position   INT            NOT NULL DEFAULT 1,
        is_printed       BIT            NOT NULL DEFAULT 0,
        printed_at       DATETIME2      NULL,
        created_at       DATETIME2      NULL DEFAULT GETUTCDATE(),
        updated_at       DATETIME2      NULL DEFAULT GETUTCDATE(),

        CONSTRAINT fk_kangis_batch_items_batch
            FOREIGN KEY (batch_id)
            REFERENCES kangis_print_label_batches (id)
            ON DELETE CASCADE
    );

    CREATE INDEX ix_kangis_batch_items_batch_id  ON kangis_print_label_batch_items (batch_id);
    CREATE INDEX ix_kangis_batch_items_file_no   ON kangis_print_label_batch_items (file_number);
    CREATE INDEX ix_kangis_batch_items_prefix    ON kangis_print_label_batch_items (prefix);
    CREATE INDEX ix_kangis_batch_items_fi_id     ON kangis_print_label_batch_items (file_indexing_id);

    PRINT 'Created table: kangis_print_label_batch_items';
END
ELSE
    PRINT 'Table kangis_print_label_batch_items already exists — skipped.';
GO


-- ============================================================================
-- 3. kangis_rack_shelf_labels
-- ============================================================================
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'kangis_rack_shelf_labels')
BEGIN
    CREATE TABLE kangis_rack_shelf_labels (
        id           BIGINT IDENTITY(1,1) PRIMARY KEY,
        rack         NVARCHAR(10)   NOT NULL,           -- Rack letter e.g. A, B, C
        shelf        NVARCHAR(10)   NOT NULL,           -- Shelf number e.g. 1, 2, 3
        full_label   NVARCHAR(20)   NOT NULL,           -- Combined rack+shelf e.g. A1
        is_used      BIT            NOT NULL DEFAULT 0,
        counter      NVARCHAR(20)   NOT NULL DEFAULT '0', -- Number of files assigned
        assigned     NVARCHAR(255)  NULL,               -- Assignment info e.g. KANGIS-KNML
        status       NVARCHAR(30)   NOT NULL DEFAULT 'Available', -- Available|Occupied|Full
        reserved_by  NVARCHAR(255)  NULL,
        reserved_at  DATETIME2      NULL,
        created_at   DATETIME2      NULL DEFAULT GETUTCDATE(),
        updated_at   DATETIME2      NULL DEFAULT GETUTCDATE(),

        CONSTRAINT uq_kangis_rack_shelf_full_label UNIQUE (full_label)
    );

    CREATE INDEX ix_kangis_rack_shelf_rack_shelf ON kangis_rack_shelf_labels (rack, shelf);
    CREATE INDEX ix_kangis_rack_shelf_status     ON kangis_rack_shelf_labels (status);

    PRINT 'Created table: kangis_rack_shelf_labels';
END
ELSE
    PRINT 'Table kangis_rack_shelf_labels already exists — skipped.';
GO


-- ============================================================================
-- 4. Seed kangis_rack_shelf_labels with A1–Z100 (2,600 rows)
-- ============================================================================
IF NOT EXISTS (SELECT TOP 1 1 FROM kangis_rack_shelf_labels)
BEGIN
    DECLARE @rack   CHAR(1);
    DECLARE @shelf  INT;
    DECLARE @ascii  INT = 65;  -- 'A'
    DECLARE @now    DATETIME2 = GETUTCDATE();

    WHILE @ascii <= 90  -- 'Z'
    BEGIN
        SET @rack = CHAR(@ascii);
        SET @shelf = 1;

        WHILE @shelf <= 100
        BEGIN
            INSERT INTO kangis_rack_shelf_labels (rack, shelf, full_label, is_used, counter, status, created_at, updated_at)
            VALUES (@rack, CAST(@shelf AS NVARCHAR(10)), @rack + CAST(@shelf AS NVARCHAR(10)), 0, '0', 'Available', @now, @now);

            SET @shelf = @shelf + 1;
        END

        SET @ascii = @ascii + 1;
    END

    PRINT 'Seeded kangis_rack_shelf_labels: ' + CAST(@@ROWCOUNT AS VARCHAR) + ' rows (A1 through Z100)';
END
ELSE
    PRINT 'kangis_rack_shelf_labels already has data — seed skipped.';
GO


-- ============================================================================
-- Verification
-- ============================================================================
SELECT 'kangis_print_label_batches'     AS [Table], COUNT(*) AS [Rows] FROM kangis_print_label_batches
UNION ALL
SELECT 'kangis_print_label_batch_items' AS [Table], COUNT(*) AS [Rows] FROM kangis_print_label_batch_items
UNION ALL
SELECT 'kangis_rack_shelf_labels'       AS [Table], COUNT(*) AS [Rows] FROM kangis_rack_shelf_labels;
GO
