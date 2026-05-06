-- ============================================================
-- DCIV Print Labels — Production SQL Script
-- Run on SQL Server (sqlsrv) to create tables and seed data
-- ============================================================

-- 1. Batches table
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'dciv_print_label_batches')
BEGIN
    CREATE TABLE dciv_print_label_batches (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        batch_number NVARCHAR(50) NOT NULL,
        prefix NVARCHAR(20) NOT NULL DEFAULT 'DCIV',
        sys_batch_no INT NULL,
        batch_size INT NULL,
        generated_count INT NULL,
        status NVARCHAR(30) NOT NULL DEFAULT 'pending',
        full_label NVARCHAR(30) NULL,
        rack_primary NVARCHAR(5) NULL,
        rack_secondary NVARCHAR(5) NULL,
        shelf_number INT NULL,
        created_by BIGINT NULL,
        updated_by BIGINT NULL,
        printed_at DATETIME2 NULL,
        created_at DATETIME2 NULL,
        updated_at DATETIME2 NULL,
        deleted_at DATETIME2 NULL,
        CONSTRAINT uq_dciv_plb_batch_number UNIQUE (batch_number)
    );

    CREATE UNIQUE NONCLUSTERED INDEX uq_dciv_plb_prefix_sysbatch
        ON dciv_print_label_batches (prefix, sys_batch_no)
        WHERE sys_batch_no IS NOT NULL AND deleted_at IS NULL AND status <> 'pending';
END
GO

-- 2. Batch items table
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'dciv_print_label_batch_items')
BEGIN
    CREATE TABLE dciv_print_label_batch_items (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        batch_id BIGINT NOT NULL,
        file_indexing_id BIGINT NULL,
        file_number NVARCHAR(100) NULL,
        prefix NVARCHAR(20) NULL,
        tracking_id NVARCHAR(100) NULL,
        shelf_label NVARCHAR(50) NULL,
        shelf_location NVARCHAR(100) NULL,
        qr_code_data NVARCHAR(MAX) NULL,
        land_use_type NVARCHAR(100) NULL,
        file_title NVARCHAR(500) NULL,
        created_at DATETIME2 NULL,
        updated_at DATETIME2 NULL,
        deleted_at DATETIME2 NULL,
        CONSTRAINT fk_dciv_plbi_batch FOREIGN KEY (batch_id)
            REFERENCES dciv_print_label_batches(id) ON DELETE CASCADE
    );
END
GO

-- 3. Rack/shelf labels table
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'dciv_rack_shelf_labels')
BEGIN
    CREATE TABLE dciv_rack_shelf_labels (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        rack_letter NVARCHAR(5) NOT NULL,
        shelf_number INT NOT NULL,
        full_label NVARCHAR(10) NOT NULL,
        counter INT NOT NULL DEFAULT 0,
        is_full BIT NOT NULL DEFAULT 0,
        created_at DATETIME2 NULL,
        updated_at DATETIME2 NULL,
        CONSTRAINT uq_dciv_rsl_full_label UNIQUE (full_label)
    );

    -- Seed A1..Z100 = 2600 rows
    DECLARE @letter INT = 65;  -- ASCII 'A'
    WHILE @letter <= 90       -- ASCII 'Z'
    BEGIN
        DECLARE @shelf INT = 1;
        WHILE @shelf <= 100
        BEGIN
            INSERT INTO dciv_rack_shelf_labels (rack_letter, shelf_number, full_label, counter, is_full, created_at, updated_at)
            VALUES (CHAR(@letter), @shelf, CHAR(@letter) + CAST(@shelf AS NVARCHAR(3)), 0, 0, GETDATE(), GETDATE());
            SET @shelf = @shelf + 1;
        END
        SET @letter = @letter + 1;
    END
END
GO

PRINT 'DCIV Print Labels tables created and seeded successfully.';
GO
