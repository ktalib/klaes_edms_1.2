-- Production DDL equivalent of migrations 2026_07_11_100000 .. 2026_07_12_120000
-- Generated from the migration files themselves; each block is guarded so it is
-- safe to re-run if a prior attempt partially applied.
-- Review before running. Take a backup first.

BEGIN TRAN;

-- ============================================================
-- 2026_07_11_100000_create_file_merger_table
-- ============================================================
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'file_merger')
BEGIN
    CREATE TABLE file_merger (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        merger_id NVARCHAR(64) NOT NULL,
        fmerge_id NVARCHAR(64) NOT NULL,
        role NVARCHAR(16) NULL,
        parent_file NVARCHAR(255) NULL,
        child_file NVARCHAR(255) NULL,
        file_number NVARCHAR(255) NOT NULL,
        file_title NVARCHAR(500) NULL,
        location NVARCHAR(500) NULL,
        date_commissioned DATETIME NULL,
        date_decommissioned DATETIME NULL,
        source NVARCHAR(32) NULL,
        reason NVARCHAR(255) NULL,
        created_at DATETIME NULL,
        updated_at DATETIME NULL
    );

    CREATE INDEX file_merger_merger_id_index ON file_merger(merger_id);
    CREATE UNIQUE INDEX file_merger_fmerge_id_unique ON file_merger(fmerge_id);
    CREATE INDEX file_merger_file_number_index ON file_merger(file_number);
    CREATE UNIQUE INDEX file_merger_group_file_unique ON file_merger(merger_id, file_number);
END;

-- ============================================================
-- 2026_07_11_100100_add_merger_id_to_file_tracker_table
-- ============================================================
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('file_tracker') AND name = 'merger_id')
BEGIN
    ALTER TABLE file_tracker ADD merger_id NVARCHAR(64) NULL;
    CREATE INDEX file_tracker_merger_id_index ON file_tracker(merger_id);
END;

-- ============================================================
-- 2026_07_12_090000_create_request_purposes_table
-- ============================================================
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'request_purposes')
BEGIN
    CREATE TABLE request_purposes (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        name NVARCHAR(255) NOT NULL,
        turnaround_days INT NOT NULL DEFAULT 5,
        is_active BIT NOT NULL DEFAULT 1,
        created_at DATETIME NULL,
        updated_at DATETIME NULL
    );

    CREATE UNIQUE INDEX request_purposes_name_unique ON request_purposes(name);
    CREATE INDEX request_purposes_is_active_index ON request_purposes(is_active);
END;

-- ============================================================
-- 2026_07_12_090100_add_request_purpose_to_file_tracker_table
-- ============================================================
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('file_tracker') AND name = 'request_purpose_id')
    ALTER TABLE file_tracker ADD request_purpose_id BIGINT NULL;
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('file_tracker') AND name = 'request_purpose_name')
    ALTER TABLE file_tracker ADD request_purpose_name NVARCHAR(255) NULL;

-- ============================================================
-- 2026_07_12_100000_add_leave_and_deputy_fields_to_users_table
-- ============================================================
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('users') AND name = 'is_on_leave')
    ALTER TABLE users ADD is_on_leave BIT NOT NULL DEFAULT 0;
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('users') AND name = 'leave_start_date')
    ALTER TABLE users ADD leave_start_date DATE NULL;
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('users') AND name = 'leave_end_date')
    ALTER TABLE users ADD leave_end_date DATE NULL;
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('users') AND name = 'leave_reason')
    ALTER TABLE users ADD leave_reason NVARCHAR(255) NULL;
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('users') AND name = 'deputy_user_id')
    ALTER TABLE users ADD deputy_user_id BIGINT NULL;
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('users') AND name = 'deputy_redirect_notes')
    ALTER TABLE users ADD deputy_redirect_notes NVARCHAR(1000) NULL;

-- ============================================================
-- 2026_07_12_100100_drop_deputy_redirect_notes_from_users_table
-- ============================================================
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('users') AND name = 'deputy_redirect_notes')
    ALTER TABLE users DROP COLUMN deputy_redirect_notes;

-- ============================================================
-- 2026_07_12_100200_add_out_of_office_date_to_users_table
-- 2026_07_12_100300_split_out_of_office_date_into_range_on_users_table
-- Net effect only: 100200 adds out_of_office_date, 100300 immediately replaces
-- it with a from/to range, so the intermediate column is skipped here.
-- ============================================================
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('users') AND name = 'out_of_office_from')
    ALTER TABLE users ADD out_of_office_from DATE NULL;
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('users') AND name = 'out_of_office_to')
    ALTER TABLE users ADD out_of_office_to DATE NULL;
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('users') AND name = 'out_of_office_date')
    ALTER TABLE users DROP COLUMN out_of_office_date;

-- ============================================================
-- 2026_07_12_120000_add_request_purpose_to_file_search_requests_table
-- ============================================================
IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'file_search_requests')
BEGIN
    IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('file_search_requests') AND name = 'request_purpose_id')
        ALTER TABLE file_search_requests ADD request_purpose_id BIGINT NULL;
    IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('file_search_requests') AND name = 'request_purpose_name')
        ALTER TABLE file_search_requests ADD request_purpose_name NVARCHAR(255) NULL;
    IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('file_search_requests') AND name = 'expected_return_date')
        ALTER TABLE file_search_requests ADD expected_return_date DATE NULL;
END;

COMMIT;
GO

-- ============================================================
-- Seed request_purposes with the corrected canonical list
-- (matches database/seeders/RequestPurposeSeeder.php)
-- ============================================================
;WITH canonical(name) AS (
    SELECT v FROM (VALUES
        ('5% PAYMENT'),('ALTERNATIVE PLOT'),('APPLICATION FOR AN OFFSET'),
        ('APPLICATION LOSS OF LETTER OF GRANT'),('ASSIGNMENT'),('BILL BALANCE'),
        ('CADASTRAL PROCESSING'),('CAVEAT'),('CERTIFIED TRUE COPY'),('CHANGE OF NAME'),
        ('CHANGE OF OWNERSHIP'),('CLEARANCE FROM KNUPDA'),('COMPLAINT LETTER'),
        ('CONTRAVENTION'),('CORRECTION'),('COURT CASE'),('DEMAND NOTICE'),('EXTENSION'),
        ('GRANT RENT'),('HIGH COURT SUBPOENA'),('INVESTIGATION'),('LANDUSE CHARGE'),
        ('LOSS OF DOCUMENT'),('MERGER'),('MORTGAGE'),('MORTGAGE ISSUE'),
        ('OFFICIAL PURPOSE'),('PLOT IDENTIFICATION'),('PLOT SEPARETION'),
        ('PLOT SHOWING'),('PROCESSING COFO'),('RECERTIFICATION'),('RECOMMENDATION'),
        ('RE-ESTABLISHMENT OF BEACONS'),('REGISTRATION'),('REGRANT'),('RE-INSTATEMENT'),
        ('SEARCH'),('SUBDIVISION'),('SURRENDER & RELEASE'),('SURVEYING'),('VERIFICATION')
    ) AS t(v)
)
INSERT INTO request_purposes (name, turnaround_days, is_active, created_at, updated_at)
SELECT c.name, 5, 1, GETDATE(), GETDATE()
FROM canonical c
WHERE NOT EXISTS (SELECT 1 FROM request_purposes rp WHERE rp.name = c.name);
GO

-- ============================================================
-- Optional: record these as run in Laravel's migrations table so
-- `php artisan migrate` won't try to run them again afterwards.
-- Check current max batch first: SELECT MAX(batch) FROM migrations;
-- Replace @batch below with (that max + 1).
-- ============================================================
-- DECLARE @batch INT = (SELECT MAX(batch) + 1 FROM migrations);
-- INSERT INTO migrations (migration, batch) VALUES
-- ('2026_07_11_100000_create_file_merger_table', @batch),
-- ('2026_07_11_100100_add_merger_id_to_file_tracker_table', @batch),
-- ('2026_07_12_090000_create_request_purposes_table', @batch),
-- ('2026_07_12_090100_add_request_purpose_to_file_tracker_table', @batch),
-- ('2026_07_12_100000_add_leave_and_deputy_fields_to_users_table', @batch),
-- ('2026_07_12_100100_drop_deputy_redirect_notes_from_users_table', @batch),
-- ('2026_07_12_100200_add_out_of_office_date_to_users_table', @batch),
-- ('2026_07_12_100300_split_out_of_office_date_into_range_on_users_table', @batch),
-- ('2026_07_12_120000_add_request_purpose_to_file_search_requests_table', @batch);
