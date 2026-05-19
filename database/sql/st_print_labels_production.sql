-- =============================================================================
-- ST Print File Labels — Production SQL Script
-- Creates: st_print_label_batches, st_print_label_batch_items,
--          st_rack_shelf_labels (with A1–Z100 seed data = 2600 rows)
-- Target:  SQL Server (sqlsrv)
-- =============================================================================

-- 1. st_print_label_batches
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'st_print_label_batches')
BEGIN
    CREATE TABLE st_print_label_batches (
        id              BIGINT IDENTITY(1,1) PRIMARY KEY,
        batch_number    NVARCHAR(100)  NOT NULL,
        prefix          NVARCHAR(20)   NOT NULL DEFAULT 'ST',
        sys_batch_no    INT            NULL,
        status          NVARCHAR(20)   NOT NULL DEFAULT 'pending',
        full_label      NVARCHAR(50)   NULL,
        rack_primary    NVARCHAR(10)   NULL,
        rack_secondary  NVARCHAR(10)   NULL,
        shelf_number    INT            NULL,
        generated_count INT            NULL DEFAULT 0,
        label_format    NVARCHAR(20)   NULL DEFAULT 'qrcode',
        label_template  NVARCHAR(50)   NULL DEFAULT '30-in-1',
        notes           NVARCHAR(MAX)  NULL,
        created_by      BIGINT         NULL,
        updated_by      BIGINT         NULL,
        created_at      DATETIME2      NULL,
        updated_at      DATETIME2      NULL,
        deleted_at      DATETIME2      NULL
    );
    -- Unique constraint: one batch per sys_batch_no per prefix
    CREATE UNIQUE INDEX uq_st_plb_prefix_sysbatch
        ON st_print_label_batches (prefix, sys_batch_no)
        WHERE sys_batch_no IS NOT NULL AND deleted_at IS NULL;
END;
GO

-- 2. st_print_label_batch_items
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'st_print_label_batch_items')
BEGIN
    CREATE TABLE st_print_label_batch_items (
        id              BIGINT IDENTITY(1,1) PRIMARY KEY,
        batch_id        BIGINT         NOT NULL,
        file_indexing_id BIGINT        NULL,
        file_number     NVARCHAR(100)  NULL,
        prefix          NVARCHAR(20)   NULL,
        file_title      NVARCHAR(500)  NULL,
        plot_number     NVARCHAR(100)  NULL,
        district        NVARCHAR(200)  NULL,
        lga             NVARCHAR(200)  NULL,
        land_use_type   NVARCHAR(100)  NULL,
        shelf_location  NVARCHAR(100)  NULL,
        label_position  INT            NULL,
        qr_code_data    NVARCHAR(MAX)  NULL,
        barcode_data    NVARCHAR(500)  NULL,
        is_printed      BIT            NOT NULL DEFAULT 0,
        printed_at      DATETIME2      NULL,
        created_at      DATETIME2      NULL,
        updated_at      DATETIME2      NULL,
        CONSTRAINT fk_st_plbi_batch
            FOREIGN KEY (batch_id) REFERENCES st_print_label_batches(id)
            ON DELETE CASCADE
    );
END;
GO

-- 3. st_rack_shelf_labels
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'st_rack_shelf_labels')
BEGIN
    CREATE TABLE st_rack_shelf_labels (
        id          BIGINT IDENTITY(1,1) PRIMARY KEY,
        rack        NVARCHAR(5)    NOT NULL,
        shelf       NVARCHAR(10)   NOT NULL,
        full_label  NVARCHAR(20)   NULL,
        is_used     BIT            NOT NULL DEFAULT 0,
        counter     NVARCHAR(MAX)  NULL DEFAULT '0',
        assigned    NVARCHAR(50)   NULL,
        status      NVARCHAR(20)   NOT NULL DEFAULT 'Available',
        reserved_by BIGINT         NULL,
        reserved_at DATETIME2      NULL,
        created_at  DATETIME2      NULL,
        updated_at  DATETIME2      NULL
    );
END;
GO

-- 4. Seed st_rack_shelf_labels with A1–Z100 (2,600 rows)
IF (SELECT COUNT(*) FROM st_rack_shelf_labels) = 0
BEGIN
    DECLARE @rack CHAR(1), @shelf INT;
    DECLARE rack_cursor CURSOR LOCAL FAST_FORWARD FOR
        SELECT CHAR(n) FROM (
            SELECT 65 AS n UNION ALL SELECT 66 UNION ALL SELECT 67 UNION ALL SELECT 68
            UNION ALL SELECT 69 UNION ALL SELECT 70 UNION ALL SELECT 71 UNION ALL SELECT 72
            UNION ALL SELECT 73 UNION ALL SELECT 74 UNION ALL SELECT 75 UNION ALL SELECT 76
            UNION ALL SELECT 77 UNION ALL SELECT 78 UNION ALL SELECT 79 UNION ALL SELECT 80
            UNION ALL SELECT 81 UNION ALL SELECT 82 UNION ALL SELECT 83 UNION ALL SELECT 84
            UNION ALL SELECT 85 UNION ALL SELECT 86 UNION ALL SELECT 87 UNION ALL SELECT 88
            UNION ALL SELECT 89 UNION ALL SELECT 90
        ) letters;

    OPEN rack_cursor;
    FETCH NEXT FROM rack_cursor INTO @rack;
    WHILE @@FETCH_STATUS = 0
    BEGIN
        SET @shelf = 1;
        WHILE @shelf <= 100
        BEGIN
            INSERT INTO st_rack_shelf_labels (rack, shelf, full_label, is_used, counter, status, created_at)
            VALUES (@rack, CAST(@shelf AS NVARCHAR(10)), @rack + CAST(@shelf AS NVARCHAR(10)), 0, '0', 'Available', GETDATE());
            SET @shelf = @shelf + 1;
        END;
        FETCH NEXT FROM rack_cursor INTO @rack;
    END;
    CLOSE rack_cursor;
    DEALLOCATE rack_cursor;
END;
GO
