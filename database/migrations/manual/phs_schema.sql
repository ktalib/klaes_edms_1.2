/* =====================================================================
   PHS Portal — schema create / update script  (SQL Server / sqlsrv)
   ---------------------------------------------------------------------
   Idempotent: safe to run on a fresh DB (creates the tables) or an
   existing one (adds only the columns/indexes that are missing).
   Mirrors the PHS database migrations, plus two columns the app writes
   that were never in a migration:
     phs_token_transactions.approved_at
     phs_token_transactions.expires_at
   ===================================================================== */

SET NOCOUNT ON;
GO

/* =====================================================================
   1. phs_institutions
   ===================================================================== */
IF OBJECT_ID('dbo.phs_institutions', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.phs_institutions (
        id                      BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        name                    NVARCHAR(255)  NOT NULL,
        username                NVARCHAR(100)  NULL,
        type                    NVARCHAR(30)   NOT NULL CONSTRAINT DF_phs_inst_type    DEFAULT ('bank'),
        email                   NVARCHAR(255)  NOT NULL,
        phone                   NVARCHAR(50)   NULL,
        token_balance           INT            NOT NULL CONSTRAINT DF_phs_inst_balance DEFAULT (0),
        primary_color           NVARCHAR(20)   NOT NULL CONSTRAINT DF_phs_inst_pcolor  DEFAULT ('#2563eb'),
        secondary_color         NVARCHAR(20)   NOT NULL CONSTRAINT DF_phs_inst_scolor  DEFAULT ('#7c3aed'),
        logo_path               NVARCHAR(255)  NULL,
        banner_path             NVARCHAR(255)  NULL,
        status                  NVARCHAR(20)   NOT NULL CONSTRAINT DF_phs_inst_status  DEFAULT ('active'),
        low_balance_notified_at DATETIME       NULL,
        created_at              DATETIME       NULL,
        updated_at              DATETIME       NULL
    );
    CREATE UNIQUE INDEX phs_institutions_email_unique ON dbo.phs_institutions (email);
    CREATE INDEX phs_institutions_status_index ON dbo.phs_institutions (status);
END
GO
/* incremental columns */
IF COL_LENGTH('dbo.phs_institutions', 'username') IS NULL
    ALTER TABLE dbo.phs_institutions ADD username NVARCHAR(100) NULL;
GO
IF COL_LENGTH('dbo.phs_institutions', 'low_balance_notified_at') IS NULL
    ALTER TABLE dbo.phs_institutions ADD low_balance_notified_at DATETIME NULL;
GO
/* filtered unique index on username (SQL Server treats multiple NULLs as dupes otherwise) */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'phs_institutions_username_unique' AND object_id = OBJECT_ID('dbo.phs_institutions'))
    CREATE UNIQUE INDEX phs_institutions_username_unique ON dbo.phs_institutions (username) WHERE username IS NOT NULL;
GO

/* =====================================================================
   2. phs_members
   ===================================================================== */
IF OBJECT_ID('dbo.phs_members', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.phs_members (
        id                  BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        phs_institution_id  BIGINT         NOT NULL,
        name                NVARCHAR(255)  NOT NULL,
        email               NVARCHAR(255)  NOT NULL,
        password            NVARCHAR(255)  NOT NULL,
        job_title           NVARCHAR(150)  NULL,
        department          NVARCHAR(150)  NULL,
        user_type           NVARCHAR(20)   NOT NULL CONSTRAINT DF_phs_mem_utype  DEFAULT ('regular_user'),
        access_role         NVARCHAR(30)   NOT NULL CONSTRAINT DF_phs_mem_role   DEFAULT ('search_only'),
        tokens_used         INT            NOT NULL CONSTRAINT DF_phs_mem_used    DEFAULT (0),
        allocated_tokens    INT            NOT NULL CONSTRAINT DF_phs_mem_alloc   DEFAULT (0),
        status              NVARCHAR(20)   NOT NULL CONSTRAINT DF_phs_mem_status  DEFAULT ('active'),
        last_login_at       DATETIME       NULL,
        remember_token      NVARCHAR(100)  NULL,
        created_at          DATETIME       NULL,
        updated_at          DATETIME       NULL
    );
    CREATE UNIQUE INDEX phs_members_email_unique ON dbo.phs_members (email);
    CREATE INDEX phs_members_phs_institution_id_index ON dbo.phs_members (phs_institution_id);
    CREATE INDEX phs_members_status_index ON dbo.phs_members (status);
END
GO
IF COL_LENGTH('dbo.phs_members', 'allocated_tokens') IS NULL
    ALTER TABLE dbo.phs_members ADD allocated_tokens INT NOT NULL CONSTRAINT DF_phs_mem_alloc DEFAULT (0);
GO

/* =====================================================================
   3. phs_token_transactions
   ===================================================================== */
IF OBJECT_ID('dbo.phs_token_transactions', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.phs_token_transactions (
        id                          BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        phs_institution_id          BIGINT         NOT NULL,
        phs_member_id               BIGINT         NULL,
        type                        NVARCHAR(30)   NOT NULL,          -- purchase | search_debit | topup | bonus | adjustment
        tokens                      INT            NOT NULL,          -- signed: + credit, - debit
        balance_after               INT            NOT NULL CONSTRAINT DF_phs_txn_balance DEFAULT (0),
        package_name                NVARCHAR(100)  NULL,
        topup_bundle_count          INT            NULL,
        topup_unit_price            DECIMAL(18,2)  NULL,
        payment_gateway_reference   NVARCHAR(255)  NULL,
        payment_confirmed_at        DATETIME       NULL,
        credited_at                 DATETIME       NULL,
        amount                      DECIMAL(18,2)  NULL,
        expected_amount             DECIMAL(18,2)  NULL,
        paid_amount                 DECIMAL(18,2)  NULL,
        payment_variance            DECIMAL(18,2)  NULL,
        payment_proof_path          NVARCHAR(500)  NULL,
        bank_reference              NVARCHAR(255)  NULL,
        payment_date                DATE           NULL,
        validation_status           NVARCHAR(20)   NULL CONSTRAINT DF_phs_txn_vstatus DEFAULT ('pending'),
        validation_notes            NVARCHAR(1000) NULL,
        validated_by                BIGINT         NULL,
        validated_at                DATETIME       NULL,
        payment_method              NVARCHAR(20)   NULL,              -- online | invoice | bank_transfer
        status                      NVARCHAR(20)   NOT NULL CONSTRAINT DF_phs_txn_status DEFAULT ('completed'),
        reference_no                NVARCHAR(100)  NULL,
        notes                       NVARCHAR(500)  NULL,
        approved_by                 BIGINT         NULL,
        approved_at                 DATETIME       NULL,
        expires_at                  DATETIME       NULL,
        created_at                  DATETIME       NULL,
        updated_at                  DATETIME       NULL
    );
    CREATE INDEX phs_token_transactions_phs_institution_id_index ON dbo.phs_token_transactions (phs_institution_id);
    CREATE INDEX phs_token_transactions_type_index   ON dbo.phs_token_transactions (type);
    CREATE INDEX phs_token_transactions_status_index ON dbo.phs_token_transactions (status);
END
GO
/* incremental columns (payment validation set) */
IF COL_LENGTH('dbo.phs_token_transactions', 'expected_amount')    IS NULL ALTER TABLE dbo.phs_token_transactions ADD expected_amount    DECIMAL(18,2)  NULL;
GO
IF COL_LENGTH('dbo.phs_token_transactions', 'paid_amount')        IS NULL ALTER TABLE dbo.phs_token_transactions ADD paid_amount        DECIMAL(18,2)  NULL;
GO
IF COL_LENGTH('dbo.phs_token_transactions', 'payment_variance')   IS NULL ALTER TABLE dbo.phs_token_transactions ADD payment_variance   DECIMAL(18,2)  NULL;
GO
IF COL_LENGTH('dbo.phs_token_transactions', 'payment_proof_path') IS NULL ALTER TABLE dbo.phs_token_transactions ADD payment_proof_path NVARCHAR(500)  NULL;
GO
IF COL_LENGTH('dbo.phs_token_transactions', 'bank_reference')     IS NULL ALTER TABLE dbo.phs_token_transactions ADD bank_reference     NVARCHAR(255)  NULL;
GO
IF COL_LENGTH('dbo.phs_token_transactions', 'payment_date')       IS NULL ALTER TABLE dbo.phs_token_transactions ADD payment_date       DATE           NULL;
GO
IF COL_LENGTH('dbo.phs_token_transactions', 'validation_status')  IS NULL ALTER TABLE dbo.phs_token_transactions ADD validation_status  NVARCHAR(20)   NULL CONSTRAINT DF_phs_txn_vstatus DEFAULT ('pending');
GO
IF COL_LENGTH('dbo.phs_token_transactions', 'validation_notes')   IS NULL ALTER TABLE dbo.phs_token_transactions ADD validation_notes   NVARCHAR(1000) NULL;
GO
IF COL_LENGTH('dbo.phs_token_transactions', 'validated_by')       IS NULL ALTER TABLE dbo.phs_token_transactions ADD validated_by       BIGINT         NULL;
GO
IF COL_LENGTH('dbo.phs_token_transactions', 'validated_at')       IS NULL ALTER TABLE dbo.phs_token_transactions ADD validated_at       DATETIME       NULL;
GO
/* incremental columns (top-up set) */
IF COL_LENGTH('dbo.phs_token_transactions', 'topup_bundle_count')        IS NULL ALTER TABLE dbo.phs_token_transactions ADD topup_bundle_count        INT           NULL;
GO
IF COL_LENGTH('dbo.phs_token_transactions', 'topup_unit_price')          IS NULL ALTER TABLE dbo.phs_token_transactions ADD topup_unit_price          DECIMAL(18,2) NULL;
GO
IF COL_LENGTH('dbo.phs_token_transactions', 'payment_gateway_reference') IS NULL ALTER TABLE dbo.phs_token_transactions ADD payment_gateway_reference NVARCHAR(255) NULL;
GO
IF COL_LENGTH('dbo.phs_token_transactions', 'payment_confirmed_at')      IS NULL ALTER TABLE dbo.phs_token_transactions ADD payment_confirmed_at      DATETIME      NULL;
GO
IF COL_LENGTH('dbo.phs_token_transactions', 'credited_at')               IS NULL ALTER TABLE dbo.phs_token_transactions ADD credited_at               DATETIME      NULL;
GO
/* columns the app writes that had no migration */
IF COL_LENGTH('dbo.phs_token_transactions', 'approved_at') IS NULL ALTER TABLE dbo.phs_token_transactions ADD approved_at DATETIME NULL;
GO
IF COL_LENGTH('dbo.phs_token_transactions', 'expires_at')  IS NULL ALTER TABLE dbo.phs_token_transactions ADD expires_at  DATETIME NULL;
GO

/* =====================================================================
   4. phs_search_logs
   ===================================================================== */
IF OBJECT_ID('dbo.phs_search_logs', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.phs_search_logs (
        id                  BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        phs_institution_id  BIGINT         NOT NULL,
        phs_member_id       BIGINT         NULL,
        query               NVARCHAR(255)  NULL,
        file_number         NVARCHAR(100)  NULL,
        result_count        INT            NOT NULL CONSTRAINT DF_phs_log_rcount DEFAULT (0),
        reference_no        NVARCHAR(100)  NULL,
        tokens_used         INT            NOT NULL CONSTRAINT DF_phs_log_tokens DEFAULT (1),
        created_at          DATETIME       NULL,
        updated_at          DATETIME       NULL
    );
    CREATE INDEX phs_search_logs_phs_institution_id_index ON dbo.phs_search_logs (phs_institution_id);
    CREATE INDEX phs_search_logs_reference_no_index ON dbo.phs_search_logs (reference_no);
END
GO

/* =====================================================================
   5. phs_token_packages
   ===================================================================== */
IF OBJECT_ID('dbo.phs_token_packages', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.phs_token_packages (
        id            BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        name          NVARCHAR(100)  NOT NULL,
        slug          NVARCHAR(50)   NOT NULL,
        bundles       INT            NOT NULL CONSTRAINT DF_phs_pkg_bundles DEFAULT (1),
        tokens        INT            NOT NULL,
        price         DECIMAL(18,2)  NOT NULL,
        team_members  INT            NOT NULL CONSTRAINT DF_phs_pkg_team    DEFAULT (2),
        description   NVARCHAR(500)  NULL,
        is_active     BIT            NOT NULL CONSTRAINT DF_phs_pkg_active  DEFAULT (1),
        display_order INT            NOT NULL CONSTRAINT DF_phs_pkg_order   DEFAULT (0),
        created_at    DATETIME       NULL,
        updated_at    DATETIME       NULL
    );
    CREATE UNIQUE INDEX phs_token_packages_slug_unique ON dbo.phs_token_packages (slug);

    INSERT INTO dbo.phs_token_packages (name, slug, bundles, tokens, price, team_members, description, is_active, display_order, created_at, updated_at)
    VALUES
        ('Starter',      'starter',       2, 2000,  50000,  2,  'For small teams getting started.',      1, 1, GETDATE(), GETDATE()),
        ('Professional', 'professional',  5, 5000,  100000, 5,  'For growing teams with regular searches.', 1, 2, GETDATE(), GETDATE()),
        ('Enterprise',   'enterprise',   10, 10000, 180000, 10, 'For large, high-volume organizations.',   1, 3, GETDATE(), GETDATE());
END
GO
IF COL_LENGTH('dbo.phs_token_packages', 'bundles') IS NULL
    ALTER TABLE dbo.phs_token_packages ADD bundles INT NOT NULL CONSTRAINT DF_phs_pkg_bundles DEFAULT (1);
GO

/* =====================================================================
   6. phs_onboarding_requests
   ===================================================================== */
IF OBJECT_ID('dbo.phs_onboarding_requests', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.phs_onboarding_requests (
        id                          BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        organization_name           NVARCHAR(255)  NOT NULL,
        organization_type           NVARCHAR(50)   NOT NULL,
        contact_name                NVARCHAR(255)  NOT NULL,
        contact_email               NVARCHAR(191)  NOT NULL,
        phone                       NVARCHAR(255)  NULL,
        address                     NVARCHAR(MAX)  NULL,
        department                  NVARCHAR(255)  NULL,
        job_title                   NVARCHAR(255)  NULL,
        initial_token_package       NVARCHAR(255)  NULL,
        additional_notes            NVARCHAR(MAX)  NULL,
        /* invoice */
        invoice_number              NVARCHAR(100)  NULL,
        invoice_generated_at        DATETIME       NULL,
        invoice_sent_at             DATETIME       NULL,
        invoice_pdf_path            NVARCHAR(500)  NULL,
        /* CAC */
        cac_registration_number     NVARCHAR(100)  NULL,
        cac_document_path           NVARCHAR(500)  NULL,
        additional_documents        NVARCHAR(MAX)  NULL,   -- JSON array of paths
        /* workflow */
        status                      NVARCHAR(50)   NOT NULL CONSTRAINT DF_phs_req_status DEFAULT ('pending'),
        /* payment */
        payment_reference           NVARCHAR(255)  NULL,
        payment_amount              DECIMAL(10,2)  NULL,
        payment_received_at         DATETIME       NULL,
        payment_status              NVARCHAR(20)   NOT NULL CONSTRAINT DF_phs_req_pstatus DEFAULT ('not_paid'),
        expected_amount             DECIMAL(18,2)  NULL,
        verified_amount             DECIMAL(18,2)  NULL,
        outstanding_amount          DECIMAL(18,2)  NULL,
        payment_verified_at         DATETIME       NULL,
        payment_verified_by         BIGINT         NULL,
        payment_verification_notes  NVARCHAR(1000) NULL,
        /* approval / activation */
        approved_at                 DATETIME       NULL,
        approved_by                 BIGINT         NULL,
        rejection_reason            NVARCHAR(MAX)  NULL,
        activation_token            NVARCHAR(255)  NULL,
        activation_token_expires_at DATETIME       NULL,
        created_phs_institution_id  BIGINT         NULL,
        created_at                  DATETIME       NULL,
        updated_at                  DATETIME       NULL
    );
    CREATE INDEX phs_onboarding_requests_status_index ON dbo.phs_onboarding_requests (status);
    CREATE UNIQUE INDEX phs_onboarding_requests_activation_token_unique ON dbo.phs_onboarding_requests (activation_token) WHERE activation_token IS NOT NULL;
END
GO
/* invoice columns */
IF COL_LENGTH('dbo.phs_onboarding_requests', 'invoice_number')       IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD invoice_number       NVARCHAR(100) NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'invoice_generated_at') IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD invoice_generated_at DATETIME      NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'invoice_sent_at')      IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD invoice_sent_at      DATETIME      NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'invoice_pdf_path')     IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD invoice_pdf_path     NVARCHAR(500) NULL;
GO
/* CAC columns */
IF COL_LENGTH('dbo.phs_onboarding_requests', 'cac_registration_number') IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD cac_registration_number NVARCHAR(100) NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'cac_document_path')       IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD cac_document_path       NVARCHAR(500) NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'additional_documents')    IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD additional_documents    NVARCHAR(MAX) NULL;
GO
/* payment verification columns */
IF COL_LENGTH('dbo.phs_onboarding_requests', 'payment_status')              IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD payment_status              NVARCHAR(20)   NOT NULL CONSTRAINT DF_phs_req_pstatus DEFAULT ('not_paid');
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'expected_amount')             IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD expected_amount             DECIMAL(18,2)  NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'verified_amount')             IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD verified_amount             DECIMAL(18,2)  NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'outstanding_amount')          IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD outstanding_amount          DECIMAL(18,2)  NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'payment_verified_at')         IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD payment_verified_at         DATETIME       NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'payment_verified_by')         IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD payment_verified_by         BIGINT         NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'payment_verification_notes')  IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD payment_verification_notes  NVARCHAR(1000) NULL;
GO
/* request letter + LSA columns (2026-06-12) */
IF COL_LENGTH('dbo.phs_onboarding_requests', 'request_letter_path')        IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD request_letter_path        NVARCHAR(500) NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'lsa_token')                  IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD lsa_token                  NVARCHAR(64)  NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'lsa_signed_document_path')   IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD lsa_signed_document_path   NVARCHAR(500) NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'lsa_signed_at')              IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD lsa_signed_at              DATETIME      NULL;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UQ_phs_onboarding_requests_lsa_token')
    CREATE UNIQUE INDEX UQ_phs_onboarding_requests_lsa_token ON dbo.phs_onboarding_requests (lsa_token) WHERE lsa_token IS NOT NULL;
GO

/* workflow columns — payment token, legal review, paystack (2026-06-12) */
IF COL_LENGTH('dbo.phs_onboarding_requests', 'payment_token')          IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD payment_token          NVARCHAR(64)   NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'legal_approved_at')      IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD legal_approved_at      DATETIME       NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'legal_approved_by')      IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD legal_approved_by      NVARCHAR(150)  NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'legal_rejection_reason') IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD legal_rejection_reason NVARCHAR(MAX)  NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'legal_sla_approved_at')  IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD legal_sla_approved_at  DATETIME       NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'legal_sla_approved_by')  IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD legal_sla_approved_by  NVARCHAR(150)  NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'paystack_reference')     IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD paystack_reference     NVARCHAR(100)  NULL;
GO
IF COL_LENGTH('dbo.phs_onboarding_requests', 'paystack_amount')        IS NULL ALTER TABLE dbo.phs_onboarding_requests ADD paystack_amount        DECIMAL(12, 2) NULL;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UQ_phs_onboarding_requests_payment_token')
    CREATE UNIQUE INDEX UQ_phs_onboarding_requests_payment_token ON dbo.phs_onboarding_requests (payment_token) WHERE payment_token IS NOT NULL;
GO

PRINT 'PHS schema create/update complete.';
GO
