-- ═══════════════════════════════════════════════════════════════════════════
-- SQL Setup for Plot Extension & Loss of Document Pages
-- Both features use the existing `oss_applications` table (SQL Server)
-- with `application_type` as the discriminator column.
-- ═══════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────
-- 1. Verify the oss_applications table exists with all required columns
-- ─────────────────────────────────────────────────────────────────────────

-- Check if table exists
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'oss_applications')
BEGIN
    CREATE TABLE oss_applications (
        id              BIGINT IDENTITY(1,1) PRIMARY KEY,
        application_type VARCHAR(50)   NULL,
        applicant_name   NVARCHAR(255) NULL,
        file_no          NVARCHAR(100) NULL,
        residential_address NVARCHAR(MAX) NULL,
        correspondence_address NVARCHAR(MAX) NULL,
        nationality      NVARCHAR(255) NULL,
        occupation       NVARCHAR(255) NULL,
        phone            NVARCHAR(50)  NULL,
        email            NVARCHAR(255) NULL,
        land_use         NVARCHAR(100) NULL,
        plot_no          NVARCHAR(100) NULL,
        plan_no          NVARCHAR(100) NULL,
        location         NVARCHAR(500) NULL,
        district         NVARCHAR(255) NULL,
        land_area        NVARCHAR(100) NULL,
        property_description NVARCHAR(1000) NULL,
        purpose          NVARCHAR(500) NULL,
        remarks          NVARCHAR(MAX) NULL,
        passport_photo   NVARCHAR(500) NULL,
        status           NVARCHAR(30)  DEFAULT 'pending',
        captured_by      BIGINT        NULL,
        updated_by       BIGINT        NULL,
        created_at       DATETIME2     NULL DEFAULT GETDATE(),
        updated_at       DATETIME2     NULL DEFAULT GETDATE()
    );
    PRINT 'Table oss_applications created.';
END
ELSE
BEGIN
    PRINT 'Table oss_applications already exists.';
END
GO

-- ─────────────────────────────────────────────────────────────────────────
-- 2. Add any missing columns that the pages depend on
-- ─────────────────────────────────────────────────────────────────────────

-- application_type (discriminator: 'plot-extension', 'loss-of-document', etc.)
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'oss_applications' AND COLUMN_NAME = 'application_type')
BEGIN
    ALTER TABLE oss_applications ADD application_type VARCHAR(50) NULL;
    PRINT 'Added column: application_type';
END
GO

-- phone
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'oss_applications' AND COLUMN_NAME = 'phone')
BEGIN
    ALTER TABLE oss_applications ADD phone NVARCHAR(50) NULL;
    PRINT 'Added column: phone';
END
GO

-- email
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'oss_applications' AND COLUMN_NAME = 'email')
BEGIN
    ALTER TABLE oss_applications ADD email NVARCHAR(255) NULL;
    PRINT 'Added column: email';
END
GO

-- nationality
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'oss_applications' AND COLUMN_NAME = 'nationality')
BEGIN
    ALTER TABLE oss_applications ADD nationality NVARCHAR(255) NULL;
    PRINT 'Added column: nationality';
END
GO

-- occupation
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'oss_applications' AND COLUMN_NAME = 'occupation')
BEGIN
    ALTER TABLE oss_applications ADD occupation NVARCHAR(255) NULL;
    PRINT 'Added column: occupation';
END
GO

-- residential_address
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'oss_applications' AND COLUMN_NAME = 'residential_address')
BEGIN
    ALTER TABLE oss_applications ADD residential_address NVARCHAR(MAX) NULL;
    PRINT 'Added column: residential_address';
END
GO

-- correspondence_address
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'oss_applications' AND COLUMN_NAME = 'correspondence_address')
BEGIN
    ALTER TABLE oss_applications ADD correspondence_address NVARCHAR(MAX) NULL;
    PRINT 'Added column: correspondence_address';
END
GO

-- passport_photo
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'oss_applications' AND COLUMN_NAME = 'passport_photo')
BEGIN
    ALTER TABLE oss_applications ADD passport_photo NVARCHAR(500) NULL;
    PRINT 'Added column: passport_photo';
END
GO

-- ─────────────────────────────────────────────────────────────────────────
-- 3. Add indexes for the new application types
-- ─────────────────────────────────────────────────────────────────────────

-- Index on application_type for filtered queries
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'idx_oss_applications_type' AND object_id = OBJECT_ID('oss_applications'))
BEGIN
    CREATE NONCLUSTERED INDEX idx_oss_applications_type
    ON oss_applications (application_type)
    INCLUDE (applicant_name, file_no, status, created_at);
    PRINT 'Created index: idx_oss_applications_type';
END
GO

-- Index on application_type + status (for filtered dashboards)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'idx_oss_app_type_status' AND object_id = OBJECT_ID('oss_applications'))
BEGIN
    CREATE NONCLUSTERED INDEX idx_oss_app_type_status
    ON oss_applications (application_type, status)
    INCLUDE (applicant_name, file_no, created_at);
    PRINT 'Created index: idx_oss_app_type_status';
END
GO

-- ─────────────────────────────────────────────────────────────────────────
-- 4. Verify application_type values for Plot Extension & Loss of Document
-- ─────────────────────────────────────────────────────────────────────────

-- Quick count check
SELECT
    application_type,
    COUNT(*) AS total_records,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_count
FROM oss_applications
WHERE application_type IN ('plot-extension', 'loss-of-document')
GROUP BY application_type;
GO

-- ─────────────────────────────────────────────────────────────────────────
-- 5. Sample insert statements (for testing)
-- ─────────────────────────────────────────────────────────────────────────

-- Sample Plot Extension
/*
INSERT INTO oss_applications (
    application_type, applicant_name, file_no, residential_address,
    correspondence_address, nationality, occupation, phone, email,
    land_use, plot_no, plan_no, location, remarks, status, created_at, updated_at
) VALUES (
    'plot-extension', 'MUSA IBRAHIM', 'RES-2024-0001',
    '12A, IBRAHIM TAIWO ROAD, NASSARAWA, KANO MUNICIPAL, KANO',
    '12A, IBRAHIM TAIWO ROAD, NASSARAWA, KANO MUNICIPAL, KANO',
    'Nigerian', 'Civil Servant', '08012345678', 'musa@example.com',
    'Residential', '123', 'KN/TP/456', 'Along Ibrahim Taiwo Road, Nassarawa GRA',
    'Request for plot extension on the northern boundary', 'pending',
    GETDATE(), GETDATE()
);

-- Sample Loss of Document
INSERT INTO oss_applications (
    application_type, applicant_name, file_no, residential_address,
    correspondence_address, nationality, occupation, phone, email,
    land_use, plot_no, plan_no, location, passport_photo, remarks, status,
    created_at, updated_at
) VALUES (
    'loss-of-document', 'AMINA SANI', 'RES-2023-0042',
    '5B, ZARIA ROAD, KOFAR KUDU, KANO MUNICIPAL, KANO',
    '5B, ZARIA ROAD, KOFAR KUDU, KANO MUNICIPAL, KANO',
    'Nigerian', 'Business Woman', '08098765432', 'amina.sani@example.com',
    'Commercial', '567', 'KN/TP/789', 'Zaria Road, Near Central Market',
    NULL, 'Certificate of Occupancy lost during office relocation', 'pending',
    GETDATE(), GETDATE()
);
*/

PRINT '═══════════════════════════════════════════════════════════════';
PRINT ' Plot Extension & Loss of Document SQL setup complete.';
PRINT ' Both use oss_applications table with application_type filter.';
PRINT '═══════════════════════════════════════════════════════════════';
GO
