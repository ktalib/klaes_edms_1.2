-- ═══════════════════════════════════════════════════════════════════════════
-- oss_applications — FULL TABLE VERIFICATION SCRIPT
-- Run against production to confirm the table is complete.
-- Generated 2026-03-11
-- ═══════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────
-- SECTION 1: CREATE TABLE (if table does not exist)
-- ─────────────────────────────────────────────────────────────────────────

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'oss_applications')
BEGIN
    CREATE TABLE oss_applications (

        -- === PRIMARY KEY ===
        id                          BIGINT          IDENTITY(1,1) PRIMARY KEY,

        -- === CORE / DISCRIMINATOR ===
        application_type            NVARCHAR(30)    NOT NULL,
        status                      NVARCHAR(30)    NOT NULL DEFAULT 'pending',

        -- === APPLICANT INFO (Migration 1: create) ===
        applicant_name              NVARCHAR(255)   NULL,
        address                     NVARCHAR(500)   NULL,
        phone                       NVARCHAR(50)    NULL,
        email                       NVARCHAR(255)   NULL,
        state_of_origin             NVARCHAR(100)   NULL,
        lga                         NVARCHAR(100)   NULL,

        -- === PROPERTY / LAND INFO (Migration 1: create) ===
        file_no                     NVARCHAR(100)   NULL,
        plot_no                     NVARCHAR(100)   NULL,
        plan_no                     NVARCHAR(100)   NULL,
        location                    NVARCHAR(500)   NULL,
        district                    NVARCHAR(255)   NULL,
        land_use                    NVARCHAR(100)   NULL,
        land_area                   NVARCHAR(100)   NULL,
        property_description        NVARCHAR(1000)  NULL,
        purpose                     NVARCHAR(500)   NULL,
        nature_of_development       NVARCHAR(500)   NULL,

        -- === FINANCIAL (Migration 1: create) ===
        proposed_dev_value          DECIMAL(18,2)   NULL,
        annual_ground_rent          DECIMAL(18,2)   NULL,
        development_charge          DECIMAL(18,2)   NULL,
        survey_processing_charges   DECIMAL(18,2)   NULL,
        term_years                  NVARCHAR(20)    NULL,

        -- === BUSINESS / CORPORATE (Migration 1: create) ===
        business_name               NVARCHAR(255)   NULL,
        business_type               NVARCHAR(255)   NULL,
        rc_number                   NVARCHAR(100)   NULL,
        industry_type               NVARCHAR(255)   NULL,
        product_type                NVARCHAR(500)   NULL,
        environmental_clearance     NVARCHAR(100)   NULL,

        -- === REMARKS AND AUDIT (Migration 1: create) ===
        remarks                     NVARCHAR(MAX)   NULL,
        captured_by                 BIGINT          NULL,
        updated_by                  BIGINT          NULL,
        created_at                  DATETIME2       NULL,
        updated_at                  DATETIME2       NULL,

        -- === EXTENDED PERSONAL INFO (Migration 2: add_form_fields) ===
        age                         NVARCHAR(10)    NULL,
        sex                         NVARCHAR(20)    NULL,
        marital_status              NVARCHAR(50)    NULL,
        husband_name_address        NVARCHAR(500)   NULL,
        nationality                 NVARCHAR(255)   NULL,
        occupation                  NVARCHAR(255)   NULL,
        annual_income               NVARCHAR(100)   NULL,
        prev_allocated              NVARCHAR(10)    NULL,
        prev_allocation_details     NVARCHAR(MAX)   NULL,
        home_domicile               NVARCHAR(255)   NULL,

        -- === EXTENDED ADDRESSES (Migration 2: add_form_fields) ===
        residential_address         NVARCHAR(MAX)   NULL,
        correspondence_address      NVARCHAR(MAX)   NULL,
        business_address            NVARCHAR(MAX)   NULL,

        -- === COMMERCIAL FIELDS (Migration 2: add_form_fields) ===
        occupation_or_business      NVARCHAR(255)   NULL,
        nature_of_commerce          NVARCHAR(500)   NULL,
        company_registered_under    NVARCHAR(500)   NULL,
        registration_particulars    NVARCHAR(500)   NULL,
        business_location           NVARCHAR(500)   NULL,
        annual_income_anticipation  NVARCHAR(100)   NULL,
        prev_land_purpose           NVARCHAR(500)   NULL,
        intended_activities         NVARCHAR(MAX)   NULL,

        -- === INDUSTRIAL FIELDS (Migration 2: add_form_fields) ===
        nature_of_occupation        NVARCHAR(500)   NULL,
        annual_income_turnover      NVARCHAR(100)   NULL,
        number_of_employees         NVARCHAR(50)    NULL,
        nature_of_industrial        NVARCHAR(500)   NULL,
        waste_disposal_requirements NVARCHAR(MAX)   NULL,

        -- === RESIDENTIAL ADDRESS BUILDER (Migration 3: address_builder) ===
        res_addr_plot               NVARCHAR(100)   NULL,
        res_addr_street             NVARCHAR(255)   NULL,
        res_addr_street_other       NVARCHAR(255)   NULL,
        res_addr_district           NVARCHAR(255)   NULL,
        res_addr_district_other     NVARCHAR(255)   NULL,
        res_addr_lga                NVARCHAR(255)   NULL,
        res_addr_state              NVARCHAR(255)   NULL,

        -- === RESIDENTIAL CORRESPONDENCE ADDRESS BUILDER (Migration 3) ===
        res_corr_plot               NVARCHAR(100)   NULL,
        res_corr_street             NVARCHAR(255)   NULL,
        res_corr_street_other       NVARCHAR(255)   NULL,
        res_corr_district           NVARCHAR(255)   NULL,
        res_corr_district_other     NVARCHAR(255)   NULL,
        res_corr_lga                NVARCHAR(255)   NULL,
        res_corr_state              NVARCHAR(255)   NULL,

        -- === RESIDENTIAL BUSINESS ADDRESS BUILDER (Migration 3) ===
        res_biz_plot                NVARCHAR(100)   NULL,
        res_biz_street              NVARCHAR(255)   NULL,
        res_biz_street_other        NVARCHAR(255)   NULL,
        res_biz_district            NVARCHAR(255)   NULL,
        res_biz_district_other      NVARCHAR(255)   NULL,
        res_biz_lga                 NVARCHAR(255)   NULL,
        res_biz_state               NVARCHAR(255)   NULL,

        -- === COMMERCIAL CORRESPONDENCE ADDRESS BUILDER (Migration 3) ===
        com_corr_plot               NVARCHAR(100)   NULL,
        com_corr_street             NVARCHAR(255)   NULL,
        com_corr_street_other       NVARCHAR(255)   NULL,
        com_corr_district           NVARCHAR(255)   NULL,
        com_corr_district_other     NVARCHAR(255)   NULL,
        com_corr_lga                NVARCHAR(255)   NULL,
        com_corr_state              NVARCHAR(255)   NULL,

        -- === COMMERCIAL BUSINESS ADDRESS BUILDER (No migration — model only) ===
        com_biz_plot                NVARCHAR(100)   NULL,
        com_biz_street              NVARCHAR(255)   NULL,
        com_biz_street_other        NVARCHAR(255)   NULL,
        com_biz_district            NVARCHAR(255)   NULL,
        com_biz_district_other      NVARCHAR(255)   NULL,
        com_biz_lga                 NVARCHAR(255)   NULL,
        com_biz_state               NVARCHAR(255)   NULL,

        -- === INDUSTRIAL CORRESPONDENCE ADDRESS BUILDER (Migration 3) ===
        ind_corr_plot               NVARCHAR(100)   NULL,
        ind_corr_street             NVARCHAR(255)   NULL,
        ind_corr_street_other       NVARCHAR(255)   NULL,
        ind_corr_district           NVARCHAR(255)   NULL,
        ind_corr_district_other     NVARCHAR(255)   NULL,
        ind_corr_lga                NVARCHAR(255)   NULL,
        ind_corr_state              NVARCHAR(255)   NULL,

        -- === INDUSTRIAL BUSINESS ADDRESS BUILDER (No migration — model only) ===
        ind_biz_plot                NVARCHAR(100)   NULL,
        ind_biz_street              NVARCHAR(255)   NULL,
        ind_biz_street_other        NVARCHAR(255)   NULL,
        ind_biz_district            NVARCHAR(255)   NULL,
        ind_biz_district_other      NVARCHAR(255)   NULL,
        ind_biz_lga                 NVARCHAR(255)   NULL,
        ind_biz_state               NVARCHAR(255)   NULL,

        -- === INSTRUMENT CAPTURE LINK (Migration 4) ===
        instrument_capture_id       BIGINT          NULL,

        -- === AGRICULTURAL FIELDS (Migration 5) ===
        nature_of_agricultural      NVARCHAR(500)   NULL,

        -- === AGRICULTURAL BUSINESS ADDRESS BUILDER (Migration 5) ===
        agr_biz_plot                NVARCHAR(100)   NULL,
        agr_biz_street              NVARCHAR(255)   NULL,
        agr_biz_street_other        NVARCHAR(255)   NULL,
        agr_biz_district            NVARCHAR(255)   NULL,
        agr_biz_district_other      NVARCHAR(255)   NULL,
        agr_biz_lga                 NVARCHAR(255)   NULL,
        agr_biz_state               NVARCHAR(255)   NULL,

        -- === AGRICULTURAL CORRESPONDENCE ADDRESS BUILDER (Migration 5) ===
        agr_corr_plot               NVARCHAR(100)   NULL,
        agr_corr_street             NVARCHAR(255)   NULL,
        agr_corr_street_other       NVARCHAR(255)   NULL,
        agr_corr_district           NVARCHAR(255)   NULL,
        agr_corr_district_other     NVARCHAR(255)   NULL,
        agr_corr_lga                NVARCHAR(255)   NULL,
        agr_corr_state              NVARCHAR(255)   NULL,

        -- === PASSPORT PHOTO (Migration 6) ===
        passport_photo              NVARCHAR(500)   NULL
    );

    PRINT 'Table oss_applications CREATED with all 123 columns.';
END
GO


-- ─────────────────────────────────────────────────────────────────────────
-- SECTION 2: ADD MISSING COLUMNS (idempotent — safe to re-run)
-- Run this on production to patch any columns that are missing.
-- ─────────────────────────────────────────────────────────────────────────

DECLARE @cols TABLE (col_name NVARCHAR(128), col_type NVARCHAR(50), col_len INT);
INSERT INTO @cols VALUES
-- Migration 1: create
('application_type','NVARCHAR',30),('status','NVARCHAR',30),
('applicant_name','NVARCHAR',255),('address','NVARCHAR',500),
('phone','NVARCHAR',50),('email','NVARCHAR',255),
('state_of_origin','NVARCHAR',100),('lga','NVARCHAR',100),
('file_no','NVARCHAR',100),('plot_no','NVARCHAR',100),
('plan_no','NVARCHAR',100),('location','NVARCHAR',500),
('district','NVARCHAR',255),('land_use','NVARCHAR',100),
('land_area','NVARCHAR',100),('property_description','NVARCHAR',1000),
('purpose','NVARCHAR',500),('nature_of_development','NVARCHAR',500),
('term_years','NVARCHAR',20),('business_name','NVARCHAR',255),
('business_type','NVARCHAR',255),('rc_number','NVARCHAR',100),
('industry_type','NVARCHAR',255),('product_type','NVARCHAR',500),
('environmental_clearance','NVARCHAR',100),
-- Migration 2: form fields
('age','NVARCHAR',10),('sex','NVARCHAR',20),
('marital_status','NVARCHAR',50),('husband_name_address','NVARCHAR',500),
('nationality','NVARCHAR',255),('occupation','NVARCHAR',255),
('annual_income','NVARCHAR',100),('prev_allocated','NVARCHAR',10),
('home_domicile','NVARCHAR',255),
('occupation_or_business','NVARCHAR',255),
('nature_of_commerce','NVARCHAR',500),
('company_registered_under','NVARCHAR',500),
('registration_particulars','NVARCHAR',500),
('business_location','NVARCHAR',500),
('annual_income_anticipation','NVARCHAR',100),
('prev_land_purpose','NVARCHAR',500),
('nature_of_occupation','NVARCHAR',500),
('annual_income_turnover','NVARCHAR',100),
('number_of_employees','NVARCHAR',50),
('nature_of_industrial','NVARCHAR',500),
-- Migration 3: address builder (35 cols)
('res_addr_plot','NVARCHAR',100),('res_addr_street','NVARCHAR',255),
('res_addr_street_other','NVARCHAR',255),('res_addr_district','NVARCHAR',255),
('res_addr_district_other','NVARCHAR',255),('res_addr_lga','NVARCHAR',255),
('res_addr_state','NVARCHAR',255),
('res_corr_plot','NVARCHAR',100),('res_corr_street','NVARCHAR',255),
('res_corr_street_other','NVARCHAR',255),('res_corr_district','NVARCHAR',255),
('res_corr_district_other','NVARCHAR',255),('res_corr_lga','NVARCHAR',255),
('res_corr_state','NVARCHAR',255),
('res_biz_plot','NVARCHAR',100),('res_biz_street','NVARCHAR',255),
('res_biz_street_other','NVARCHAR',255),('res_biz_district','NVARCHAR',255),
('res_biz_district_other','NVARCHAR',255),('res_biz_lga','NVARCHAR',255),
('res_biz_state','NVARCHAR',255),
('com_corr_plot','NVARCHAR',100),('com_corr_street','NVARCHAR',255),
('com_corr_street_other','NVARCHAR',255),('com_corr_district','NVARCHAR',255),
('com_corr_district_other','NVARCHAR',255),('com_corr_lga','NVARCHAR',255),
('com_corr_state','NVARCHAR',255),
('ind_corr_plot','NVARCHAR',100),('ind_corr_street','NVARCHAR',255),
('ind_corr_street_other','NVARCHAR',255),('ind_corr_district','NVARCHAR',255),
('ind_corr_district_other','NVARCHAR',255),('ind_corr_lga','NVARCHAR',255),
('ind_corr_state','NVARCHAR',255),
-- No migration (model-only, from fix_oss_address_columns.php)
('com_biz_plot','NVARCHAR',100),('com_biz_street','NVARCHAR',255),
('com_biz_street_other','NVARCHAR',255),('com_biz_district','NVARCHAR',255),
('com_biz_district_other','NVARCHAR',255),('com_biz_lga','NVARCHAR',255),
('com_biz_state','NVARCHAR',255),
('ind_biz_plot','NVARCHAR',100),('ind_biz_street','NVARCHAR',255),
('ind_biz_street_other','NVARCHAR',255),('ind_biz_district','NVARCHAR',255),
('ind_biz_district_other','NVARCHAR',255),('ind_biz_lga','NVARCHAR',255),
('ind_biz_state','NVARCHAR',255),
-- Migration 5: agricultural
('nature_of_agricultural','NVARCHAR',500),
('agr_biz_plot','NVARCHAR',100),('agr_biz_street','NVARCHAR',255),
('agr_biz_street_other','NVARCHAR',255),('agr_biz_district','NVARCHAR',255),
('agr_biz_district_other','NVARCHAR',255),('agr_biz_lga','NVARCHAR',255),
('agr_biz_state','NVARCHAR',255),
('agr_corr_plot','NVARCHAR',100),('agr_corr_street','NVARCHAR',255),
('agr_corr_street_other','NVARCHAR',255),('agr_corr_district','NVARCHAR',255),
('agr_corr_district_other','NVARCHAR',255),('agr_corr_lga','NVARCHAR',255),
('agr_corr_state','NVARCHAR',255),
-- Migration 6: passport
('passport_photo','NVARCHAR',500);

-- Handle NVARCHAR(MAX) columns separately (can't parameterize MAX in dynamic SQL)
DECLARE @max_cols TABLE (col_name NVARCHAR(128));
INSERT INTO @max_cols VALUES
('remarks'),('prev_allocation_details'),('intended_activities'),
('waste_disposal_requirements'),('residential_address'),
('correspondence_address'),('business_address');

-- Handle DECIMAL columns
DECLARE @dec_cols TABLE (col_name NVARCHAR(128));
INSERT INTO @dec_cols VALUES
('proposed_dev_value'),('annual_ground_rent'),
('development_charge'),('survey_processing_charges');

-- Handle BIGINT columns
DECLARE @bigint_cols TABLE (col_name NVARCHAR(128));
INSERT INTO @bigint_cols VALUES
('captured_by'),('updated_by'),('instrument_capture_id');

-- Handle DATETIME2 columns
DECLARE @dt_cols TABLE (col_name NVARCHAR(128));
INSERT INTO @dt_cols VALUES ('created_at'),('updated_at');

-- Add missing NVARCHAR(n) columns
DECLARE @name NVARCHAR(128), @type NVARCHAR(50), @len INT, @sql NVARCHAR(500);
DECLARE cur CURSOR FOR SELECT col_name, col_type, col_len FROM @cols;
OPEN cur;
FETCH NEXT FROM cur INTO @name, @type, @len;
WHILE @@FETCH_STATUS = 0
BEGIN
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_NAME = 'oss_applications' AND COLUMN_NAME = @name)
    BEGIN
        SET @sql = 'ALTER TABLE oss_applications ADD [' + @name + '] '
                 + @type + '(' + CAST(@len AS NVARCHAR) + ') NULL';
        EXEC sp_executesql @sql;
        PRINT 'ADDED: ' + @name + ' ' + @type + '(' + CAST(@len AS NVARCHAR) + ')';
    END
    FETCH NEXT FROM cur INTO @name, @type, @len;
END
CLOSE cur; DEALLOCATE cur;

-- Add missing NVARCHAR(MAX) columns
DECLARE cur2 CURSOR FOR SELECT col_name FROM @max_cols;
OPEN cur2;
FETCH NEXT FROM cur2 INTO @name;
WHILE @@FETCH_STATUS = 0
BEGIN
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_NAME = 'oss_applications' AND COLUMN_NAME = @name)
    BEGIN
        SET @sql = 'ALTER TABLE oss_applications ADD [' + @name + '] NVARCHAR(MAX) NULL';
        EXEC sp_executesql @sql;
        PRINT 'ADDED: ' + @name + ' NVARCHAR(MAX)';
    END
    FETCH NEXT FROM cur2 INTO @name;
END
CLOSE cur2; DEALLOCATE cur2;

-- Add missing DECIMAL(18,2) columns
DECLARE cur3 CURSOR FOR SELECT col_name FROM @dec_cols;
OPEN cur3;
FETCH NEXT FROM cur3 INTO @name;
WHILE @@FETCH_STATUS = 0
BEGIN
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_NAME = 'oss_applications' AND COLUMN_NAME = @name)
    BEGIN
        SET @sql = 'ALTER TABLE oss_applications ADD [' + @name + '] DECIMAL(18,2) NULL';
        EXEC sp_executesql @sql;
        PRINT 'ADDED: ' + @name + ' DECIMAL(18,2)';
    END
    FETCH NEXT FROM cur3 INTO @name;
END
CLOSE cur3; DEALLOCATE cur3;

-- Add missing BIGINT columns
DECLARE cur4 CURSOR FOR SELECT col_name FROM @bigint_cols;
OPEN cur4;
FETCH NEXT FROM cur4 INTO @name;
WHILE @@FETCH_STATUS = 0
BEGIN
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_NAME = 'oss_applications' AND COLUMN_NAME = @name)
    BEGIN
        SET @sql = 'ALTER TABLE oss_applications ADD [' + @name + '] BIGINT NULL';
        EXEC sp_executesql @sql;
        PRINT 'ADDED: ' + @name + ' BIGINT';
    END
    FETCH NEXT FROM cur4 INTO @name;
END
CLOSE cur4; DEALLOCATE cur4;

-- Add missing DATETIME2 columns
DECLARE cur5 CURSOR FOR SELECT col_name FROM @dt_cols;
OPEN cur5;
FETCH NEXT FROM cur5 INTO @name;
WHILE @@FETCH_STATUS = 0
BEGIN
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_NAME = 'oss_applications' AND COLUMN_NAME = @name)
    BEGIN
        SET @sql = 'ALTER TABLE oss_applications ADD [' + @name + '] DATETIME2 NULL';
        EXEC sp_executesql @sql;
        PRINT 'ADDED: ' + @name + ' DATETIME2';
    END
    FETCH NEXT FROM cur5 INTO @name;
END
CLOSE cur5; DEALLOCATE cur5;

PRINT '';
PRINT 'All missing columns have been added (if any).';
GO


-- ─────────────────────────────────────────────────────────────────────────
-- SECTION 3: CREATE INDEXES (idempotent)
-- ─────────────────────────────────────────────────────────────────────────

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'idx_oss_applications_type' AND object_id = OBJECT_ID('oss_applications'))
BEGIN
    CREATE NONCLUSTERED INDEX idx_oss_applications_type
    ON oss_applications (application_type)
    INCLUDE (applicant_name, file_no, status, created_at);
    PRINT 'Created index: idx_oss_applications_type';
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'idx_oss_app_type_status' AND object_id = OBJECT_ID('oss_applications'))
BEGIN
    CREATE NONCLUSTERED INDEX idx_oss_app_type_status
    ON oss_applications (application_type, status)
    INCLUDE (applicant_name, file_no, created_at);
    PRINT 'Created index: idx_oss_app_type_status';
END
GO

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'oss_applications_instrument_capture_id_idx' AND object_id = OBJECT_ID('oss_applications'))
BEGIN
    CREATE NONCLUSTERED INDEX oss_applications_instrument_capture_id_idx
    ON oss_applications (instrument_capture_id);
    PRINT 'Created index: oss_applications_instrument_capture_id_idx';
END
GO


-- ─────────────────────────────────────────────────────────────────────────
-- SECTION 4: VERIFICATION — compare production columns vs expected
-- Run this SELECT to see which columns EXIST and which are MISSING
-- ─────────────────────────────────────────────────────────────────────────

;WITH expected AS (
    SELECT col_name FROM (VALUES
        -- Core
        ('id'),('application_type'),('status'),
        -- Applicant
        ('applicant_name'),('address'),('phone'),('email'),
        ('state_of_origin'),('lga'),
        -- Property
        ('file_no'),('plot_no'),('plan_no'),('location'),('district'),
        ('land_use'),('land_area'),('property_description'),('purpose'),
        ('nature_of_development'),
        -- Financial
        ('proposed_dev_value'),('annual_ground_rent'),
        ('development_charge'),('survey_processing_charges'),('term_years'),
        -- Business
        ('business_name'),('business_type'),('rc_number'),
        ('industry_type'),('product_type'),('environmental_clearance'),
        -- Audit
        ('remarks'),('captured_by'),('updated_by'),('created_at'),('updated_at'),
        -- Extended personal
        ('age'),('sex'),('marital_status'),('husband_name_address'),
        ('nationality'),('occupation'),('annual_income'),('prev_allocated'),
        ('prev_allocation_details'),('home_domicile'),
        -- Extended addresses
        ('residential_address'),('correspondence_address'),('business_address'),
        -- Commercial fields
        ('occupation_or_business'),('nature_of_commerce'),
        ('company_registered_under'),('registration_particulars'),
        ('business_location'),('annual_income_anticipation'),
        ('prev_land_purpose'),('intended_activities'),
        -- Industrial fields
        ('nature_of_occupation'),('annual_income_turnover'),
        ('number_of_employees'),('nature_of_industrial'),
        ('waste_disposal_requirements'),
        -- Residential address builder (7)
        ('res_addr_plot'),('res_addr_street'),('res_addr_street_other'),
        ('res_addr_district'),('res_addr_district_other'),
        ('res_addr_lga'),('res_addr_state'),
        -- Residential correspondence (7)
        ('res_corr_plot'),('res_corr_street'),('res_corr_street_other'),
        ('res_corr_district'),('res_corr_district_other'),
        ('res_corr_lga'),('res_corr_state'),
        -- Residential business (7)
        ('res_biz_plot'),('res_biz_street'),('res_biz_street_other'),
        ('res_biz_district'),('res_biz_district_other'),
        ('res_biz_lga'),('res_biz_state'),
        -- Commercial correspondence (7)
        ('com_corr_plot'),('com_corr_street'),('com_corr_street_other'),
        ('com_corr_district'),('com_corr_district_other'),
        ('com_corr_lga'),('com_corr_state'),
        -- Commercial business (7)
        ('com_biz_plot'),('com_biz_street'),('com_biz_street_other'),
        ('com_biz_district'),('com_biz_district_other'),
        ('com_biz_lga'),('com_biz_state'),
        -- Industrial correspondence (7)
        ('ind_corr_plot'),('ind_corr_street'),('ind_corr_street_other'),
        ('ind_corr_district'),('ind_corr_district_other'),
        ('ind_corr_lga'),('ind_corr_state'),
        -- Industrial business (7)
        ('ind_biz_plot'),('ind_biz_street'),('ind_biz_street_other'),
        ('ind_biz_district'),('ind_biz_district_other'),
        ('ind_biz_lga'),('ind_biz_state'),
        -- Instrument capture
        ('instrument_capture_id'),
        -- Agricultural
        ('nature_of_agricultural'),
        ('agr_biz_plot'),('agr_biz_street'),('agr_biz_street_other'),
        ('agr_biz_district'),('agr_biz_district_other'),
        ('agr_biz_lga'),('agr_biz_state'),
        ('agr_corr_plot'),('agr_corr_street'),('agr_corr_street_other'),
        ('agr_corr_district'),('agr_corr_district_other'),
        ('agr_corr_lga'),('agr_corr_state'),
        -- Passport
        ('passport_photo')
    ) AS v(col_name)
),
actual AS (
    SELECT COLUMN_NAME AS col_name
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'oss_applications'
)
SELECT
    e.col_name,
    CASE WHEN a.col_name IS NOT NULL THEN 'EXISTS' ELSE '*** MISSING ***' END AS col_status
FROM expected e
LEFT JOIN actual a ON e.col_name = a.col_name
ORDER BY col_status DESC, e.col_name;
GO

-- Also show any EXTRA columns on production that are NOT in our expected list
;WITH expected AS (
    SELECT col_name FROM (VALUES
        ('id'),('application_type'),('status'),
        ('applicant_name'),('address'),('phone'),('email'),
        ('state_of_origin'),('lga'),
        ('file_no'),('plot_no'),('plan_no'),('location'),('district'),
        ('land_use'),('land_area'),('property_description'),('purpose'),
        ('nature_of_development'),
        ('proposed_dev_value'),('annual_ground_rent'),
        ('development_charge'),('survey_processing_charges'),('term_years'),
        ('business_name'),('business_type'),('rc_number'),
        ('industry_type'),('product_type'),('environmental_clearance'),
        ('remarks'),('captured_by'),('updated_by'),('created_at'),('updated_at'),
        ('age'),('sex'),('marital_status'),('husband_name_address'),
        ('nationality'),('occupation'),('annual_income'),('prev_allocated'),
        ('prev_allocation_details'),('home_domicile'),
        ('residential_address'),('correspondence_address'),('business_address'),
        ('occupation_or_business'),('nature_of_commerce'),
        ('company_registered_under'),('registration_particulars'),
        ('business_location'),('annual_income_anticipation'),
        ('prev_land_purpose'),('intended_activities'),
        ('nature_of_occupation'),('annual_income_turnover'),
        ('number_of_employees'),('nature_of_industrial'),
        ('waste_disposal_requirements'),
        ('res_addr_plot'),('res_addr_street'),('res_addr_street_other'),
        ('res_addr_district'),('res_addr_district_other'),
        ('res_addr_lga'),('res_addr_state'),
        ('res_corr_plot'),('res_corr_street'),('res_corr_street_other'),
        ('res_corr_district'),('res_corr_district_other'),
        ('res_corr_lga'),('res_corr_state'),
        ('res_biz_plot'),('res_biz_street'),('res_biz_street_other'),
        ('res_biz_district'),('res_biz_district_other'),
        ('res_biz_lga'),('res_biz_state'),
        ('com_corr_plot'),('com_corr_street'),('com_corr_street_other'),
        ('com_corr_district'),('com_corr_district_other'),
        ('com_corr_lga'),('com_corr_state'),
        ('com_biz_plot'),('com_biz_street'),('com_biz_street_other'),
        ('com_biz_district'),('com_biz_district_other'),
        ('com_biz_lga'),('com_biz_state'),
        ('ind_corr_plot'),('ind_corr_street'),('ind_corr_street_other'),
        ('ind_corr_district'),('ind_corr_district_other'),
        ('ind_corr_lga'),('ind_corr_state'),
        ('ind_biz_plot'),('ind_biz_street'),('ind_biz_street_other'),
        ('ind_biz_district'),('ind_biz_district_other'),
        ('ind_biz_lga'),('ind_biz_state'),
        ('instrument_capture_id'),
        ('nature_of_agricultural'),
        ('agr_biz_plot'),('agr_biz_street'),('agr_biz_street_other'),
        ('agr_biz_district'),('agr_biz_district_other'),
        ('agr_biz_lga'),('agr_biz_state'),
        ('agr_corr_plot'),('agr_corr_street'),('agr_corr_street_other'),
        ('agr_corr_district'),('agr_corr_district_other'),
        ('agr_corr_lga'),('agr_corr_state'),
        ('passport_photo')
    ) AS v(col_name)
)
SELECT
    c.COLUMN_NAME      AS extra_column,
    c.DATA_TYPE         AS data_type,
    c.CHARACTER_MAXIMUM_LENGTH AS max_length,
    c.IS_NULLABLE       AS nullable
FROM INFORMATION_SCHEMA.COLUMNS c
LEFT JOIN expected e ON c.COLUMN_NAME = e.col_name
WHERE c.TABLE_NAME = 'oss_applications'
  AND e.col_name IS NULL
ORDER BY c.ORDINAL_POSITION;
GO

PRINT '';
PRINT '═══════════════════════════════════════════════════════════════';
PRINT ' VERIFICATION COMPLETE';
PRINT ' Check results above for MISSING or EXTRA columns.';
PRINT '═══════════════════════════════════════════════════════════════';
GO
