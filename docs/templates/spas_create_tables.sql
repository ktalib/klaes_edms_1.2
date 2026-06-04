-- ============================================================================
-- SPAS (Special Assignment) – Table Creation Script
-- Target: SQL Server  |  Database: [klas]
-- Run this script on the target database before deploying the application.
-- All tables use BIGINT IDENTITY PKs to match Laravel's bigIncrements.
-- ============================================================================

USE [klas];
GO
SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

-- ---------------------------------------------------------------------------
-- 1. spa_applications  (anchor record per case)
-- ---------------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'spa_applications')
BEGIN
    CREATE TABLE [dbo].[spa_applications] (
        [id]               BIGINT          IDENTITY(1,1) NOT NULL,
        [file_number]      NVARCHAR(255)   NULL,
        [tracking_id]      NVARCHAR(100)   NULL,
        [file_indexing_id] BIGINT          NULL,
        [is_indexed]       BIT             NOT NULL DEFAULT 0,
        [owner_name]       NVARCHAR(255)   NULL,
        [phone]            NVARCHAR(20)    NULL,
        [location]         NVARCHAR(255)   NULL,
        [district]         NVARCHAR(255)   NULL,
        [lga]              NVARCHAR(255)   NULL,
        [land_use_type]    NVARCHAR(255)   NULL,   -- Applied Land Use (from file indexing)
        [existing_use]     NVARCHAR(255)   NULL,   -- Prevailing Land Use (on ground)
        [proposed_use]     NVARCHAR(255)   NULL,   -- Approved Land Use (official decision)
        [photos]           NVARCHAR(MAX)   NULL,   -- JSON array of storage paths
        [scenario]         NVARCHAR(1)     NULL,   -- A = compelled, B = already applied
        [status]           NVARCHAR(50)    NOT NULL DEFAULT 'open',
        -- open | in_progress | memo_stage | approved | certificate_issued | closed
        [created_by]       NVARCHAR(255)   NULL,
        [created_at]       DATETIME2(0)    NULL,
        [updated_at]       DATETIME2(0)    NULL,

        CONSTRAINT [PK_spa_applications] PRIMARY KEY CLUSTERED ([id] ASC)
    );

    CREATE NONCLUSTERED INDEX [IX_spa_applications_file_number]  ON [dbo].[spa_applications] ([file_number]);
    CREATE NONCLUSTERED INDEX [IX_spa_applications_tracking_id]  ON [dbo].[spa_applications] ([tracking_id]);
    CREATE NONCLUSTERED INDEX [IX_spa_applications_status]       ON [dbo].[spa_applications] ([status]);

    PRINT 'Created: spa_applications';
END
ELSE PRINT 'Exists:  spa_applications';
GO

-- ---------------------------------------------------------------------------
-- 2. spa_field_data  (field inspection records)
-- ---------------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'spa_field_data')
BEGIN
    CREATE TABLE [dbo].[spa_field_data] (
        [id]                   BIGINT          IDENTITY(1,1) NOT NULL,
        [spa_application_id]   BIGINT          NOT NULL,
        [file_number]          NVARCHAR(255)   NULL,
        [surveyor_id]          BIGINT          NULL,   -- FK users.id
        [inspection_date]      DATE            NULL,
        [coordinates]          NVARCHAR(MAX)   NULL,   -- JSON {"lat":..., "lng":...}
        [findings]             NVARCHAR(MAX)   NULL,
        [photos]               NVARCHAR(MAX)   NULL,   -- JSON array of storage paths
        [status]               NVARCHAR(30)    NOT NULL DEFAULT 'pending', -- pending | verified
        [created_by]           NVARCHAR(255)   NULL,
        [created_at]           DATETIME2(0)    NULL,
        [updated_at]           DATETIME2(0)    NULL,

        CONSTRAINT [PK_spa_field_data] PRIMARY KEY CLUSTERED ([id] ASC)
    );

    CREATE NONCLUSTERED INDEX [IX_spa_field_data_app_id]    ON [dbo].[spa_field_data] ([spa_application_id]);
    CREATE NONCLUSTERED INDEX [IX_spa_field_data_status]    ON [dbo].[spa_field_data] ([status]);

    PRINT 'Created: spa_field_data';
END
ELSE PRINT 'Exists:  spa_field_data';
GO

-- ---------------------------------------------------------------------------
-- 3. spa_notices  (first and second serve notices)
-- ---------------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'spa_notices')
BEGIN
    CREATE TABLE [dbo].[spa_notices] (
        [id]                   BIGINT          IDENTITY(1,1) NOT NULL,
        [spa_application_id]   BIGINT          NOT NULL,
        [file_number]          NVARCHAR(255)   NULL,
        [notice_type]          NVARCHAR(10)    NOT NULL,  -- first | second
        [recipient_name]       NVARCHAR(255)   NULL,
        [phone]                NVARCHAR(20)    NULL,
        [served_by]            BIGINT          NULL,      -- FK users.id
        [served_date]          DATE            NULL,
        [scheduled_date]       DATE            NULL,      -- auto-trigger date for 2nd serve
        [sms_sent]             BIT             NOT NULL DEFAULT 0,
        [sms_sent_at]          DATETIME2(0)    NULL,
        [status]               NVARCHAR(20)    NOT NULL DEFAULT 'pending', -- pending | served | failed
        [created_by]           NVARCHAR(255)   NULL,
        [created_at]           DATETIME2(0)    NULL,
        [updated_at]           DATETIME2(0)    NULL,

        CONSTRAINT [PK_spa_notices] PRIMARY KEY CLUSTERED ([id] ASC)
    );

    CREATE NONCLUSTERED INDEX [IX_spa_notices_app_type]  ON [dbo].[spa_notices] ([spa_application_id], [notice_type]);
    CREATE NONCLUSTERED INDEX [IX_spa_notices_type]      ON [dbo].[spa_notices] ([notice_type]);
    CREATE NONCLUSTERED INDEX [IX_spa_notices_status]    ON [dbo].[spa_notices] ([status]);

    PRINT 'Created: spa_notices';
END
ELSE PRINT 'Exists:  spa_notices';
GO

-- ---------------------------------------------------------------------------
-- 4. spa_bills  (billing line items per application)
-- ---------------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'spa_bills')
BEGIN
    CREATE TABLE [dbo].[spa_bills] (
        [id]                   BIGINT          IDENTITY(1,1) NOT NULL,
        [spa_application_id]   BIGINT          NOT NULL,
        [bill_type]            NVARCHAR(100)   NULL,
        [description]          NVARCHAR(500)   NULL,
        [amount]               DECIMAL(15,2)   NOT NULL DEFAULT 0,
        [reference_id]         NVARCHAR(100)   NULL,  -- SPA-BILL-YYYY-###
        [due_date]             DATE            NULL,
        [status]               NVARCHAR(20)    NOT NULL DEFAULT 'unpaid', -- unpaid | paid | partial
        [created_by]           NVARCHAR(255)   NULL,
        [created_at]           DATETIME2(0)    NULL,
        [updated_at]           DATETIME2(0)    NULL,

        CONSTRAINT [PK_spa_bills] PRIMARY KEY CLUSTERED ([id] ASC)
    );

    CREATE UNIQUE NONCLUSTERED INDEX [UQ_spa_bills_reference_id] ON [dbo].[spa_bills] ([reference_id]) WHERE [reference_id] IS NOT NULL;
    CREATE NONCLUSTERED INDEX [IX_spa_bills_app_id]  ON [dbo].[spa_bills] ([spa_application_id]);
    CREATE NONCLUSTERED INDEX [IX_spa_bills_status]  ON [dbo].[spa_bills] ([status]);

    PRINT 'Created: spa_bills';
END
ELSE PRINT 'Exists:  spa_bills';
GO

-- ---------------------------------------------------------------------------
-- 5. spa_payments  (payment records against bills)
-- ---------------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'spa_payments')
BEGIN
    CREATE TABLE [dbo].[spa_payments] (
        [id]                   BIGINT          IDENTITY(1,1) NOT NULL,
        [spa_bill_id]          BIGINT          NOT NULL,
        [spa_application_id]   BIGINT          NOT NULL,
        [amount_paid]          DECIMAL(15,2)   NOT NULL DEFAULT 0,
        [receipt_number]       NVARCHAR(100)   NULL,
        [payment_date]         DATE            NULL,
        [payment_method]       NVARCHAR(50)    NULL,  -- cash | bank_transfer | pos | cheque
        [recorded_by]          NVARCHAR(255)   NULL,
        [created_at]           DATETIME2(0)    NULL,
        [updated_at]           DATETIME2(0)    NULL,

        CONSTRAINT [PK_spa_payments] PRIMARY KEY CLUSTERED ([id] ASC)
    );

    CREATE NONCLUSTERED INDEX [IX_spa_payments_bill_id]  ON [dbo].[spa_payments] ([spa_bill_id]);
    CREATE NONCLUSTERED INDEX [IX_spa_payments_app_id]   ON [dbo].[spa_payments] ([spa_application_id]);

    PRINT 'Created: spa_payments';
END
ELSE PRINT 'Exists:  spa_payments';
GO

-- ---------------------------------------------------------------------------
-- 6. spa_department_referrals  (referrals to other departments)
-- ---------------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'spa_department_referrals')
BEGIN
    CREATE TABLE [dbo].[spa_department_referrals] (
        [id]                   BIGINT          IDENTITY(1,1) NOT NULL,
        [spa_application_id]   BIGINT          NOT NULL,
        [file_number]          NVARCHAR(255)   NULL,
        [department]           NVARCHAR(30)    NOT NULL,  -- cadastral | physical_planning | survey
        [referred_by]          BIGINT          NULL,      -- FK users.id
        [referral_date]        DATE            NULL,
        [response_date]        DATE            NULL,
        [notes]                NVARCHAR(MAX)   NULL,
        [response_notes]       NVARCHAR(MAX)   NULL,
        [status]               NVARCHAR(20)    NOT NULL DEFAULT 'pending', -- pending | responded | approved | rejected
        [created_by]           NVARCHAR(255)   NULL,
        [created_at]           DATETIME2(0)    NULL,
        [updated_at]           DATETIME2(0)    NULL,

        CONSTRAINT [PK_spa_department_referrals] PRIMARY KEY CLUSTERED ([id] ASC)
    );

    CREATE NONCLUSTERED INDEX [IX_spa_dept_referrals_app_dept] ON [dbo].[spa_department_referrals] ([spa_application_id], [department]);
    CREATE NONCLUSTERED INDEX [IX_spa_dept_referrals_dept]     ON [dbo].[spa_department_referrals] ([department]);
    CREATE NONCLUSTERED INDEX [IX_spa_dept_referrals_status]   ON [dbo].[spa_department_referrals] ([status]);

    PRINT 'Created: spa_department_referrals';
END
ELSE PRINT 'Exists:  spa_department_referrals';
GO

-- ---------------------------------------------------------------------------
-- 7. spa_memos  (Commissioner approval memos)
-- ---------------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'spa_memos')
BEGIN
    CREATE TABLE [dbo].[spa_memos] (
        [id]                       BIGINT          IDENTITY(1,1) NOT NULL,
        [spa_application_id]       BIGINT          NOT NULL,
        [memo_no]                  NVARCHAR(50)    NULL,  -- SPA/YYYY/####
        [prepared_by]              BIGINT          NULL,  -- FK users.id
        [forwarded_to]             NVARCHAR(255)   NULL,
        [forwarded_at]             DATETIME2(0)    NULL,
        [commissioner_decision]    NVARCHAR(20)    NOT NULL DEFAULT 'pending', -- pending | approved | rejected
        [commissioner_notes]       NVARCHAR(MAX)   NULL,
        [decided_at]               DATETIME2(0)    NULL,
        [created_by]               NVARCHAR(255)   NULL,
        [created_at]               DATETIME2(0)    NULL,
        [updated_at]               DATETIME2(0)    NULL,

        CONSTRAINT [PK_spa_memos] PRIMARY KEY CLUSTERED ([id] ASC)
    );

    CREATE UNIQUE NONCLUSTERED INDEX [UQ_spa_memos_memo_no]       ON [dbo].[spa_memos] ([memo_no]) WHERE [memo_no] IS NOT NULL;
    CREATE NONCLUSTERED INDEX [IX_spa_memos_app_id]               ON [dbo].[spa_memos] ([spa_application_id]);
    CREATE NONCLUSTERED INDEX [IX_spa_memos_commissioner_decision] ON [dbo].[spa_memos] ([commissioner_decision]);

    PRINT 'Created: spa_memos';
END
ELSE PRINT 'Exists:  spa_memos';
GO

-- ---------------------------------------------------------------------------
-- 8. spa_certificates  (Certificate of Change of Purpose)
-- ---------------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'spa_certificates')
BEGIN
    CREATE TABLE [dbo].[spa_certificates] (
        [id]                   BIGINT          IDENTITY(1,1) NOT NULL,
        [spa_application_id]   BIGINT          NOT NULL,
        [cert_number]          NVARCHAR(50)    NULL,  -- SPA-COP/YYYY/####
        [file_number]          NVARCHAR(255)   NULL,
        [holder_name]          NVARCHAR(255)   NULL,
        [from_use]             NVARCHAR(255)   NULL,
        [to_use]               NVARCHAR(255)   NULL,
        [issue_date]           DATE            NULL,
        [expiry_date]          DATE            NULL,
        [issued_by]            BIGINT          NULL,  -- FK users.id
        [status]               NVARCHAR(20)    NOT NULL DEFAULT 'draft', -- draft | issued | collected | revoked
        [created_by]           NVARCHAR(255)   NULL,
        [created_at]           DATETIME2(0)    NULL,
        [updated_at]           DATETIME2(0)    NULL,

        CONSTRAINT [PK_spa_certificates] PRIMARY KEY CLUSTERED ([id] ASC)
    );

    CREATE UNIQUE NONCLUSTERED INDEX [UQ_spa_certificates_cert_number] ON [dbo].[spa_certificates] ([cert_number]) WHERE [cert_number] IS NOT NULL;
    CREATE NONCLUSTERED INDEX [IX_spa_certificates_app_id]      ON [dbo].[spa_certificates] ([spa_application_id]);
    CREATE NONCLUSTERED INDEX [IX_spa_certificates_file_number] ON [dbo].[spa_certificates] ([file_number]);
    CREATE NONCLUSTERED INDEX [IX_spa_certificates_status]      ON [dbo].[spa_certificates] ([status]);

    PRINT 'Created: spa_certificates';
END
ELSE PRINT 'Exists:  spa_certificates';
GO

-- ---------------------------------------------------------------------------
-- Summary
-- ---------------------------------------------------------------------------
SELECT
    t.name          AS table_name,
    SUM(p.rows)     AS row_count
FROM sys.tables t
INNER JOIN sys.partitions p ON p.object_id = t.object_id AND p.index_id IN (0,1)
WHERE t.name LIKE 'spa_%'
GROUP BY t.name
ORDER BY t.name;
GO

PRINT 'SPAS table creation complete.';
GO
