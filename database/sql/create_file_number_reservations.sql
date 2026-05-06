-- ============================================================================
-- File Number Reservation System - MS SQL Server Database Script
-- ============================================================================
-- Purpose: Create file_number_reservations table and related objects
-- Version: 1.0
-- Date: October 2, 2025
-- Database: Microsoft SQL Server (2016+)
-- ============================================================================

USE [your_database_name]; -- Change to your actual database name
GO

-- ============================================================================
-- STEP 1: Drop existing objects (if recreating)
-- ============================================================================

PRINT 'Checking for existing objects...';
GO

-- Drop foreign keys first
IF EXISTS (SELECT * FROM sys.foreign_keys WHERE name = 'FK_file_number_reservations_users')
BEGIN
    PRINT 'Dropping foreign key: FK_file_number_reservations_users';
    ALTER TABLE [dbo].[file_number_reservations] DROP CONSTRAINT [FK_file_number_reservations_users];
END
GO

IF EXISTS (SELECT * FROM sys.foreign_keys WHERE name = 'FK_file_number_reservations_mother_application_draft')
BEGIN
    PRINT 'Dropping foreign key: FK_file_number_reservations_mother_application_draft';
    ALTER TABLE [dbo].[file_number_reservations] DROP CONSTRAINT [FK_file_number_reservations_mother_application_draft];
END
GO

IF EXISTS (SELECT * FROM sys.foreign_keys WHERE name = 'FK_file_number_reservations_mother_applications')
BEGIN
    PRINT 'Dropping foreign key: FK_file_number_reservations_mother_applications';
    ALTER TABLE [dbo].[file_number_reservations] DROP CONSTRAINT [FK_file_number_reservations_mother_applications];
END
GO

-- Drop table if exists
IF OBJECT_ID('dbo.file_number_reservations', 'U') IS NOT NULL
BEGIN
    PRINT 'Dropping existing table: file_number_reservations';
    DROP TABLE [dbo].[file_number_reservations];
    PRINT '✓ Table dropped';
END
GO

-- ============================================================================
-- STEP 2: Create file_number_reservations table
-- ============================================================================

PRINT '';
PRINT 'Creating table: file_number_reservations...';
GO

CREATE TABLE [dbo].[file_number_reservations] (
    -- Primary Key
    [id] BIGINT IDENTITY(1,1) NOT NULL,
    
    -- File Number Details
    [file_number] NVARCHAR(100) NOT NULL,
    [land_use_type] NVARCHAR(50) NOT NULL,
    [serial_number] INT NOT NULL,
    [year] INT NOT NULL,
    
    -- Status Management
    [status] NVARCHAR(20) NOT NULL DEFAULT 'reserved',
    
    -- Associations
    [draft_id] UNIQUEIDENTIFIER NULL,
    [application_id] INT NULL,  -- Changed from BIGINT to INT to match mother_applications.id
    
    -- Ownership and Timing
    [reserved_by] BIGINT NULL,
    [reserved_at] DATETIME2(0) NOT NULL DEFAULT GETDATE(),
    [expires_at] DATETIME2(0) NOT NULL,
    [used_at] DATETIME2(0) NULL,
    [released_at] DATETIME2(0) NULL,
    
    -- Audit Trail
    [metadata] NVARCHAR(MAX) NULL,
    [client_ip] NVARCHAR(45) NULL,
    [user_agent] NVARCHAR(MAX) NULL,
    
    -- Timestamps
    [created_at] DATETIME2(0) NULL,
    [updated_at] DATETIME2(0) NULL,
    
    -- Constraints
    CONSTRAINT [PK_file_number_reservations] PRIMARY KEY CLUSTERED ([id] ASC),
    CONSTRAINT [UQ_file_number_reservations_file_number] UNIQUE NONCLUSTERED ([file_number] ASC),
    CONSTRAINT [CHK_file_number_reservations_status] CHECK ([status] IN ('reserved', 'used', 'released', 'expired'))
);
GO

PRINT '✓ Table created successfully';
GO

-- ============================================================================
-- STEP 3: Create indexes for performance
-- ============================================================================

PRINT '';
PRINT 'Creating indexes...';
GO

-- Index on land_use_type for filtering
CREATE NONCLUSTERED INDEX [IX_file_number_reservations_land_use_type]
ON [dbo].[file_number_reservations] ([land_use_type] ASC);
GO
PRINT '✓ Created index: IX_file_number_reservations_land_use_type';

-- Index on year for filtering
CREATE NONCLUSTERED INDEX [IX_file_number_reservations_year]
ON [dbo].[file_number_reservations] ([year] ASC);
GO
PRINT '✓ Created index: IX_file_number_reservations_year';

-- Index on status for filtering
CREATE NONCLUSTERED INDEX [IX_file_number_reservations_status]
ON [dbo].[file_number_reservations] ([status] ASC);
GO
PRINT '✓ Created index: IX_file_number_reservations_status';

-- Composite index on land_use_type, year, and serial_number
CREATE NONCLUSTERED INDEX [IX_file_number_reservations_land_use_serial]
ON [dbo].[file_number_reservations] ([land_use_type] ASC, [year] ASC, [serial_number] ASC);
GO
PRINT '✓ Created index: IX_file_number_reservations_land_use_serial';

-- Composite index on status and expires_at (for cleanup queries)
CREATE NONCLUSTERED INDEX [IX_file_number_reservations_status_expiry]
ON [dbo].[file_number_reservations] ([status] ASC, [expires_at] ASC);
GO
PRINT '✓ Created index: IX_file_number_reservations_status_expiry';

-- Index on draft_id for lookups
CREATE NONCLUSTERED INDEX [IX_file_number_reservations_draft_id]
ON [dbo].[file_number_reservations] ([draft_id] ASC);
GO
PRINT '✓ Created index: IX_file_number_reservations_draft_id';

-- Index on application_id for lookups
CREATE NONCLUSTERED INDEX [IX_file_number_reservations_application_id]
ON [dbo].[file_number_reservations] ([application_id] ASC);
GO
PRINT '✓ Created index: IX_file_number_reservations_application_id';

-- Index on reserved_at for chronological queries
CREATE NONCLUSTERED INDEX [IX_file_number_reservations_reserved_at]
ON [dbo].[file_number_reservations] ([reserved_at] ASC);
GO
PRINT '✓ Created index: IX_file_number_reservations_reserved_at';

-- ============================================================================
-- STEP 4: Create foreign keys
-- ============================================================================

PRINT '';
PRINT 'Creating foreign keys...';
GO

-- Foreign key to users table
IF OBJECT_ID('dbo.users', 'U') IS NOT NULL
BEGIN
    ALTER TABLE [dbo].[file_number_reservations]
    ADD CONSTRAINT [FK_file_number_reservations_users]
    FOREIGN KEY ([reserved_by]) REFERENCES [dbo].[users]([id])
    ON DELETE SET NULL;
    PRINT '✓ Created foreign key: FK_file_number_reservations_users';
END
ELSE
BEGIN
    PRINT '⚠ Warning: users table not found, skipping FK creation';
END
GO

-- Foreign key to mother_application_draft table
IF OBJECT_ID('dbo.mother_application_draft', 'U') IS NOT NULL
BEGIN
    -- Check if draft_id column exists in mother_application_draft
    IF EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'mother_application_draft' 
        AND COLUMN_NAME = 'draft_id'
    )
    BEGIN
        ALTER TABLE [dbo].[file_number_reservations]
        ADD CONSTRAINT [FK_file_number_reservations_mother_application_draft]
        FOREIGN KEY ([draft_id]) REFERENCES [dbo].[mother_application_draft]([draft_id])
        ON DELETE CASCADE;
        PRINT '✓ Created foreign key: FK_file_number_reservations_mother_application_draft';
    END
    ELSE
    BEGIN
        PRINT '⚠ Warning: draft_id column not found in mother_application_draft, skipping FK creation';
    END
END
ELSE
BEGIN
    PRINT '⚠ Warning: mother_application_draft table not found, skipping FK creation';
END
GO

-- Foreign key to mother_applications table
IF OBJECT_ID('dbo.mother_applications', 'U') IS NOT NULL
BEGIN
    ALTER TABLE [dbo].[file_number_reservations]
    ADD CONSTRAINT [FK_file_number_reservations_mother_applications]
    FOREIGN KEY ([application_id]) REFERENCES [dbo].[mother_applications]([id])
    ON DELETE SET NULL;
    PRINT '✓ Created foreign key: FK_file_number_reservations_mother_applications';
END
ELSE
BEGIN
    PRINT '⚠ Warning: mother_applications table not found, skipping FK creation';
END
GO

-- ============================================================================
-- STEP 5: Add unique constraint to land_use_serials (if not exists)
-- ============================================================================

PRINT '';
PRINT 'Checking land_use_serials table...';
GO

IF OBJECT_ID('dbo.land_use_serials', 'U') IS NOT NULL
BEGIN
    -- Check if unique constraint already exists
    IF NOT EXISTS (
        SELECT 1 FROM sys.indexes 
        WHERE object_id = OBJECT_ID('dbo.land_use_serials') 
        AND name = 'UQ_land_use_serials_land_use_year'
    )
    BEGIN
        ALTER TABLE [dbo].[land_use_serials]
        ADD CONSTRAINT [UQ_land_use_serials_land_use_year] 
        UNIQUE NONCLUSTERED ([land_use_type], [year]);
        PRINT '✓ Created unique constraint on land_use_serials';
    END
    ELSE
    BEGIN
        PRINT '✓ Unique constraint already exists on land_use_serials';
    END
END
ELSE
BEGIN
    PRINT '⚠ Warning: land_use_serials table not found';
END
GO

-- ============================================================================
-- STEP 6: Create helper views (optional)
-- ============================================================================

PRINT '';
PRINT 'Creating helper views...';
GO

-- View for active reservations
IF OBJECT_ID('dbo.vw_active_reservations', 'V') IS NOT NULL
    DROP VIEW [dbo].[vw_active_reservations];
GO

CREATE VIEW [dbo].[vw_active_reservations]
AS
SELECT 
    fnr.id,
    fnr.file_number,
    fnr.land_use_type,
    fnr.serial_number,
    fnr.year,
    fnr.status,
    fnr.draft_id,
    fnr.reserved_at,
    fnr.expires_at,
    DATEDIFF(hour, GETDATE(), fnr.expires_at) as hours_until_expiry,
    ISNULL(u.first_name + ' ' + ISNULL(u.last_name, ISNULL(u.surname, '')), u.first_name) as reserved_by_name,
    u.email as reserved_by_email,
    fnr.created_at
FROM [dbo].[file_number_reservations] fnr
LEFT JOIN [dbo].[users] u ON fnr.reserved_by = u.id
WHERE fnr.status = 'reserved' 
AND fnr.expires_at >= GETDATE();
GO
PRINT '✓ Created view: vw_active_reservations';

-- View for expired reservations needing cleanup
IF OBJECT_ID('dbo.vw_expired_reservations', 'V') IS NOT NULL
    DROP VIEW [dbo].[vw_expired_reservations];
GO

CREATE VIEW [dbo].[vw_expired_reservations]
AS
SELECT 
    fnr.id,
    fnr.file_number,
    fnr.land_use_type,
    fnr.serial_number,
    fnr.year,
    fnr.status,
    fnr.draft_id,
    fnr.reserved_at,
    fnr.expires_at,
    DATEDIFF(day, fnr.expires_at, GETDATE()) as days_expired,
    ISNULL(u.first_name + ' ' + ISNULL(u.last_name, ISNULL(u.surname, '')), u.first_name) as reserved_by_name,
    fnr.created_at
FROM [dbo].[file_number_reservations] fnr
LEFT JOIN [dbo].[users] u ON fnr.reserved_by = u.id
WHERE fnr.status = 'reserved' 
AND fnr.expires_at < GETDATE();
GO
PRINT '✓ Created view: vw_expired_reservations';

-- View for reservation statistics
IF OBJECT_ID('dbo.vw_reservation_statistics', 'V') IS NOT NULL
    DROP VIEW [dbo].[vw_reservation_statistics];
GO

CREATE VIEW [dbo].[vw_reservation_statistics]
AS
SELECT 
    land_use_type,
    year,
    status,
    COUNT(*) as count,
    MIN(reserved_at) as first_reservation,
    MAX(reserved_at) as last_reservation,
    MIN(serial_number) as min_serial,
    MAX(serial_number) as max_serial
FROM [dbo].[file_number_reservations]
GROUP BY land_use_type, year, status;
GO
PRINT '✓ Created view: vw_reservation_statistics';

-- ============================================================================
-- STEP 7: Create stored procedures (optional)
-- ============================================================================

PRINT '';
PRINT 'Creating stored procedures...';
GO

-- Procedure to get reservation statistics
IF OBJECT_ID('dbo.sp_GetReservationStatistics', 'P') IS NOT NULL
    DROP PROCEDURE [dbo].[sp_GetReservationStatistics];
GO

CREATE PROCEDURE [dbo].[sp_GetReservationStatistics]
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT 
        'Total Reservations' as metric,
        COUNT(*) as value
    FROM [dbo].[file_number_reservations]
    
    UNION ALL
    
    SELECT 
        'Active Reservations',
        COUNT(*)
    FROM [dbo].[file_number_reservations]
    WHERE status = 'reserved' AND expires_at >= GETDATE()
    
    UNION ALL
    
    SELECT 
        'Used Reservations',
        COUNT(*)
    FROM [dbo].[file_number_reservations]
    WHERE status = 'used'
    
    UNION ALL
    
    SELECT 
        'Released Reservations',
        COUNT(*)
    FROM [dbo].[file_number_reservations]
    WHERE status = 'released'
    
    UNION ALL
    
    SELECT 
        'Expired (Needs Cleanup)',
        COUNT(*)
    FROM [dbo].[file_number_reservations]
    WHERE status = 'reserved' AND expires_at < GETDATE()
    
    UNION ALL
    
    SELECT 
        'Expired (Cleaned)',
        COUNT(*)
    FROM [dbo].[file_number_reservations]
    WHERE status = 'expired';
END
GO
PRINT '✓ Created procedure: sp_GetReservationStatistics';

-- Procedure to cleanup expired reservations
IF OBJECT_ID('dbo.sp_CleanupExpiredReservations', 'P') IS NOT NULL
    DROP PROCEDURE [dbo].[sp_CleanupExpiredReservations];
GO

CREATE PROCEDURE [dbo].[sp_CleanupExpiredReservations]
    @DryRun BIT = 0  -- Set to 1 to preview what would be cleaned without making changes
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Find expired reservations
    DECLARE @ExpiredCount INT;
    
    SELECT @ExpiredCount = COUNT(*)
    FROM [dbo].[file_number_reservations]
    WHERE status = 'reserved' AND expires_at < GETDATE();
    
    IF @DryRun = 1
    BEGIN
        -- Preview mode - just show what would be cleaned
        SELECT 
            file_number,
            land_use_type,
            reserved_at,
            expires_at,
            DATEDIFF(day, expires_at, GETDATE()) as days_expired
        FROM [dbo].[file_number_reservations]
        WHERE status = 'reserved' AND expires_at < GETDATE()
        ORDER BY expires_at;
        
        PRINT CONCAT('Would mark ', @ExpiredCount, ' reservation(s) as expired');
    END
    ELSE
    BEGIN
        -- Actual cleanup
        BEGIN TRANSACTION;
        
        BEGIN TRY
            UPDATE [dbo].[file_number_reservations]
            SET status = 'expired',
                updated_at = GETDATE()
            WHERE status = 'reserved' AND expires_at < GETDATE();
            
            COMMIT TRANSACTION;
            
            PRINT CONCAT('Successfully marked ', @ExpiredCount, ' reservation(s) as expired');
        END TRY
        BEGIN CATCH
            ROLLBACK TRANSACTION;
            
            DECLARE @ErrorMessage NVARCHAR(4000) = ERROR_MESSAGE();
            RAISERROR(@ErrorMessage, 16, 1);
        END CATCH
    END
    
    RETURN @ExpiredCount;
END
GO
PRINT '✓ Created procedure: sp_CleanupExpiredReservations';

-- ============================================================================
-- STEP 8: Grant permissions (adjust as needed)
-- ============================================================================

PRINT '';
PRINT 'Setting permissions...';
GO

-- Grant permissions to application user role (adjust role name as needed)
-- Uncomment and modify as needed for your environment

/*
GRANT SELECT, INSERT, UPDATE ON [dbo].[file_number_reservations] TO [your_application_role];
GRANT SELECT ON [dbo].[vw_active_reservations] TO [your_application_role];
GRANT SELECT ON [dbo].[vw_expired_reservations] TO [your_application_role];
GRANT SELECT ON [dbo].[vw_reservation_statistics] TO [your_application_role];
GRANT EXECUTE ON [dbo].[sp_GetReservationStatistics] TO [your_application_role];
GRANT EXECUTE ON [dbo].[sp_CleanupExpiredReservations] TO [your_application_role];
PRINT '✓ Permissions granted';
*/

PRINT '⚠ Permissions not configured - adjust STEP 8 as needed for your environment';
GO

-- ============================================================================
-- STEP 9: Verification
-- ============================================================================

PRINT '';
PRINT '============================================================================';
PRINT 'VERIFICATION';
PRINT '============================================================================';
GO

-- Verify table exists
IF OBJECT_ID('dbo.file_number_reservations', 'U') IS NOT NULL
    PRINT '✓ Table: file_number_reservations exists';
ELSE
    PRINT '✗ ERROR: Table not created!';
GO

-- Count columns
DECLARE @ColumnCount INT;
SELECT @ColumnCount = COUNT(*) 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'file_number_reservations';
PRINT CONCAT('✓ Columns: ', @ColumnCount, ' columns created');
GO

-- Count indexes
DECLARE @IndexCount INT;
SELECT @IndexCount = COUNT(*) 
FROM sys.indexes 
WHERE object_id = OBJECT_ID('dbo.file_number_reservations')
AND name IS NOT NULL;
PRINT CONCAT('✓ Indexes: ', @IndexCount, ' indexes created');
GO

-- Count foreign keys
DECLARE @FKCount INT;
SELECT @FKCount = COUNT(*) 
FROM sys.foreign_keys 
WHERE parent_object_id = OBJECT_ID('dbo.file_number_reservations');
PRINT CONCAT('✓ Foreign Keys: ', @FKCount, ' foreign keys created');
GO

-- ============================================================================
-- STEP 10: Sample queries for testing
-- ============================================================================

PRINT '';
PRINT '============================================================================';
PRINT 'SAMPLE QUERIES';
PRINT '============================================================================';
PRINT '';
PRINT '-- View all reservations:';
PRINT 'SELECT * FROM file_number_reservations ORDER BY reserved_at DESC;';
PRINT '';
PRINT '-- View active reservations:';
PRINT 'SELECT * FROM vw_active_reservations;';
PRINT '';
PRINT '-- View expired reservations:';
PRINT 'SELECT * FROM vw_expired_reservations;';
PRINT '';
PRINT '-- Get statistics:';
PRINT 'EXEC sp_GetReservationStatistics;';
PRINT '';
PRINT '-- Preview cleanup (dry run):';
PRINT 'EXEC sp_CleanupExpiredReservations @DryRun = 1;';
PRINT '';
PRINT '-- Actually cleanup expired:';
PRINT 'EXEC sp_CleanupExpiredReservations @DryRun = 0;';
PRINT '';
GO

-- ============================================================================
-- COMPLETION MESSAGE
-- ============================================================================

PRINT '';
PRINT '============================================================================';
PRINT 'INSTALLATION COMPLETE!';
PRINT '============================================================================';
PRINT '';
PRINT 'Summary:';
PRINT '  ✓ Table created: file_number_reservations';
PRINT '  ✓ Indexes created for optimal performance';
PRINT '  ✓ Foreign keys configured (where applicable)';
PRINT '  ✓ Helper views created';
PRINT '  ✓ Stored procedures created';
PRINT '';
PRINT 'Next Steps:';
PRINT '  1. Review permissions in STEP 8 and adjust as needed';
PRINT '  2. Test using sample queries shown above';
PRINT '  3. Run the Laravel migration if using Laravel framework';
PRINT '  4. Configure scheduled cleanup job (daily recommended)';
PRINT '  5. Monitor reservation activity and adjust expiry time if needed';
PRINT '';
PRINT 'Documentation: See FILE_NUMBER_RESERVATION_SYSTEM.md';
PRINT '';
GO
