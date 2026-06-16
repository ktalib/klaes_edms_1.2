/* ============================================================================
   Quick Search & File Location + File Search Request (FSR/SCB)
   PRODUCTION SCHEMA CHANGES (SQL Server)  —  idempotent / safe to re-run.

   Equivalent to these Laravel migrations:
     2026_06_15_000001_add_location_columns_to_file_indexings_table
     2026_06_15_000002_create_file_search_requests_table
     2026_06_15_000003_add_fr_permissions_to_users_table
     2026_06_15_000004_add_location_status_manual_to_file_indexings_table

   PREFERRED on production: run  php artisan migrate  (keeps the migrations table
   in sync). Use this script only if you apply DDL manually — and then mark the
   four migrations as run (see note at the bottom).
   ============================================================================ */

/* 1) file_indexings — location snapshot columns (000001 + 000004) */
IF COL_LENGTH('dbo.file_indexings','tracking_status') IS NULL
    ALTER TABLE dbo.file_indexings ADD tracking_status NVARCHAR(50) NULL;
IF COL_LENGTH('dbo.file_indexings','current_location') IS NULL
    ALTER TABLE dbo.file_indexings ADD current_location NVARCHAR(255) NULL;
IF COL_LENGTH('dbo.file_indexings','file_tracker_id') IS NULL
    ALTER TABLE dbo.file_indexings ADD file_tracker_id BIGINT NULL;
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'file_indexings_file_tracker_id_idx' AND object_id = OBJECT_ID('dbo.file_indexings'))
    CREATE INDEX file_indexings_file_tracker_id_idx ON dbo.file_indexings (file_tracker_id);
IF COL_LENGTH('dbo.file_indexings','location_status_manual') IS NULL
    ALTER TABLE dbo.file_indexings ADD location_status_manual DATETIME NULL;

/* 2) users — SCB Monitor flag (000003)  ('SCB' => is an SCB Monitor) */
IF COL_LENGTH('dbo.users','fr_permissions') IS NULL
    ALTER TABLE dbo.users ADD fr_permissions NVARCHAR(20) NULL;

/* 3) file_search_requests — the FSR table (000002) */
IF OBJECT_ID('dbo.file_search_requests','U') IS NULL
BEGIN
    CREATE TABLE dbo.file_search_requests (
        id                  BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        request_no          NVARCHAR(30)  NOT NULL,
        file_number         NVARCHAR(255) NOT NULL,
        file_title          NVARCHAR(255) NULL,
        requester_user_id   BIGINT NULL,
        assigned_monitor_id BIGINT NULL,
        status              NVARCHAR(20)  NOT NULL CONSTRAINT DF_file_search_requests_status DEFAULT ('PENDING'),
        resolved_status     NVARCHAR(50)  NULL,
        current_location    NVARCHAR(255) NULL,
        feedback_note       NVARCHAR(MAX) NULL,
        responded_by        BIGINT NULL,
        responded_at        DATETIME NULL,
        created_at          DATETIME NULL,
        updated_at          DATETIME NULL,
        CONSTRAINT file_search_requests_request_no_unique UNIQUE (request_no)
    );
    CREATE INDEX file_search_requests_file_number_index         ON dbo.file_search_requests (file_number);
    CREATE INDEX file_search_requests_status_index              ON dbo.file_search_requests (status);
    CREATE INDEX file_search_requests_assigned_monitor_id_index ON dbo.file_search_requests (assigned_monitor_id);
END;

/* ----------------------------------------------------------------------------
   NOTE — if you ran this DDL manually (instead of php artisan migrate), also
   record the migrations so artisan won't try to re-create them. Set @batch to
   (SELECT MAX(batch)+1 FROM migrations).
   ----------------------------------------------------------------------------
DECLARE @batch INT = (SELECT ISNULL(MAX(batch),0)+1 FROM dbo.migrations);
INSERT INTO dbo.migrations (migration, batch) VALUES
 ('2026_06_15_000001_add_location_columns_to_file_indexings_table', @batch),
 ('2026_06_15_000002_create_file_search_requests_table', @batch),
 ('2026_06_15_000003_add_fr_permissions_to_users_table', @batch),
 ('2026_06_15_000004_add_location_status_manual_to_file_indexings_table', @batch);
*/
