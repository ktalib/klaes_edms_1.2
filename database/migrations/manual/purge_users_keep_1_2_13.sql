-- ============================================================================
-- purge_users_keep_1_2_13.sql   (Target: SQL Server, DB: klas)
-- Deletes all users except ids 1, 2, 13 and clears blocking FK references.
-- Backups written to *_bak_20260602 tables. Wrapped in a transaction.
-- ============================================================================
SET NOCOUNT ON;
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
SET XACT_ABORT ON;   -- any error rolls the whole thing back

-- ---------------------------------------------------------------------------
-- 0) Backups (drop+recreate snapshot copies)
-- ---------------------------------------------------------------------------
IF OBJECT_ID('dbo.users_bak_20260602') IS NOT NULL DROP TABLE dbo.users_bak_20260602;
SELECT * INTO dbo.users_bak_20260602 FROM dbo.users;
IF OBJECT_ID('dbo.manual_attendance_entries_bak_20260602') IS NOT NULL DROP TABLE dbo.manual_attendance_entries_bak_20260602;
SELECT * INTO dbo.manual_attendance_entries_bak_20260602 FROM dbo.manual_attendance_entries;
IF OBJECT_ID('dbo.manual_attendance_audit_logs_bak_20260602') IS NOT NULL DROP TABLE dbo.manual_attendance_audit_logs_bak_20260602;
SELECT * INTO dbo.manual_attendance_audit_logs_bak_20260602 FROM dbo.manual_attendance_audit_logs;
IF OBJECT_ID('dbo.other_receiving_officers_bak_20260602') IS NOT NULL DROP TABLE dbo.other_receiving_officers_bak_20260602;
SELECT * INTO dbo.other_receiving_officers_bak_20260602 FROM dbo.other_receiving_officers;
IF OBJECT_ID('dbo.payroll_audit_logs_bak_20260602') IS NOT NULL DROP TABLE dbo.payroll_audit_logs_bak_20260602;
SELECT * INTO dbo.payroll_audit_logs_bak_20260602 FROM dbo.payroll_audit_logs;
IF OBJECT_ID('dbo.payroll_rates_bak_20260602') IS NOT NULL DROP TABLE dbo.payroll_rates_bak_20260602;
SELECT * INTO dbo.payroll_rates_bak_20260602 FROM dbo.payroll_rates;
IF OBJECT_ID('dbo.vfc_workers_bak_20260602') IS NOT NULL DROP TABLE dbo.vfc_workers_bak_20260602;
SELECT * INTO dbo.vfc_workers_bak_20260602 FROM dbo.vfc_workers;
PRINT 'Backups created (*_bak_20260602).';

DECLARE @keep TABLE(id INT PRIMARY KEY);
INSERT INTO @keep VALUES (1),(2),(13);

BEGIN TRANSACTION;

    -- 1) manual_attendance_entries: NOT NULL on user_id & captured_by -> delete
    DELETE FROM dbo.manual_attendance_entries
    WHERE user_id     NOT IN (SELECT id FROM @keep)
       OR captured_by NOT IN (SELECT id FROM @keep)
       OR (reviewed_by IS NOT NULL AND reviewed_by NOT IN (SELECT id FROM @keep));
    PRINT CONCAT('manual_attendance_entries deleted: ', @@ROWCOUNT);

    -- 2) manual_attendance_audit_logs.performed_by (nullable) -> NULL
    UPDATE dbo.manual_attendance_audit_logs SET performed_by = NULL
    WHERE performed_by NOT IN (SELECT id FROM @keep);
    PRINT CONCAT('manual_attendance_audit_logs.performed_by nulled: ', @@ROWCOUNT);

    -- 3) other_receiving_officers: null updated_by on kept rows, delete rows whose creator is gone
    -- NOTE: tr_other_receiving_officers_updated_at has a baked-in sp_addextendedproperty
    -- (missing GO at creation) that fails on every UPDATE; disable it for this op.
    DISABLE TRIGGER dbo.tr_other_receiving_officers_updated_at ON dbo.other_receiving_officers;
    UPDATE dbo.other_receiving_officers SET updated_by = NULL
    WHERE updated_by IS NOT NULL AND updated_by NOT IN (SELECT id FROM @keep)
      AND created_by IN (SELECT id FROM @keep);
    PRINT CONCAT('other_receiving_officers.updated_by nulled: ', @@ROWCOUNT);

    DELETE FROM dbo.other_receiving_officers
    WHERE created_by NOT IN (SELECT id FROM @keep);
    PRINT CONCAT('other_receiving_officers deleted: ', @@ROWCOUNT);
    ENABLE TRIGGER dbo.tr_other_receiving_officers_updated_at ON dbo.other_receiving_officers;

    -- 4) payroll_audit_logs.user_id / actor_id (nullable) -> NULL
    UPDATE dbo.payroll_audit_logs SET user_id = NULL
    WHERE user_id IS NOT NULL AND user_id NOT IN (SELECT id FROM @keep);
    PRINT CONCAT('payroll_audit_logs.user_id nulled: ', @@ROWCOUNT);
    UPDATE dbo.payroll_audit_logs SET actor_id = NULL
    WHERE actor_id IS NOT NULL AND actor_id NOT IN (SELECT id FROM @keep);
    PRINT CONCAT('payroll_audit_logs.actor_id nulled: ', @@ROWCOUNT);

    -- 5) payroll_rates.created_by (nullable) -> NULL
    UPDATE dbo.payroll_rates SET created_by = NULL
    WHERE created_by IS NOT NULL AND created_by NOT IN (SELECT id FROM @keep);
    PRINT CONCAT('payroll_rates.created_by nulled: ', @@ROWCOUNT);

    -- 6) vfc_workers.user_id (NOT NULL) -> delete
    DELETE FROM dbo.vfc_workers
    WHERE user_id NOT IN (SELECT id FROM @keep);
    PRINT CONCAT('vfc_workers deleted: ', @@ROWCOUNT);

    -- 7) Finally, the users (CASCADE / SET NULL FKs handle the rest)
    DELETE FROM dbo.users
    WHERE id NOT IN (SELECT id FROM @keep);
    PRINT CONCAT('users deleted: ', @@ROWCOUNT);

    SELECT id, first_name, last_name, email, type FROM dbo.users ORDER BY id;

COMMIT TRANSACTION;
PRINT 'COMMITTED.';
