-- =============================================================================
-- RECEIVING OFFICERS TABLE: Restructure + Junk User Cleanup
-- =============================================================================
-- This script:
--   1. Adds officer detail columns to receiving_officers (first_name, last_name, etc.)
--   2. Drops the user_id column (no longer references users table)
--   3. Identifies junk users created by the old "Add Receiving Officer" modal
--   4. Provides SQL to migrate those records into receiving_officers
--   5. Provides SQL to deactivate/delete the junk users
-- =============================================================================

-- =============================================================================
-- STEP 1: ALTER receiving_officers table — add new columns
-- =============================================================================

-- Add first_name and last_name
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('receiving_officers') AND name = 'first_name')
BEGIN
    ALTER TABLE receiving_officers ADD first_name NVARCHAR(255) NULL;
END;

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('receiving_officers') AND name = 'last_name')
BEGIN
    ALTER TABLE receiving_officers ADD last_name NVARCHAR(255) NULL;
END;

-- Add optional contact/detail columns
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('receiving_officers') AND name = 'phone_number')
BEGIN
    ALTER TABLE receiving_officers ADD phone_number NVARCHAR(20) NULL;
END;

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('receiving_officers') AND name = 'email')
BEGIN
    ALTER TABLE receiving_officers ADD email NVARCHAR(255) NULL;
END;

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('receiving_officers') AND name = 'office_name')
BEGIN
    ALTER TABLE receiving_officers ADD office_name NVARCHAR(255) NULL;
END;

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('receiving_officers') AND name = 'designation')
BEGIN
    ALTER TABLE receiving_officers ADD designation NVARCHAR(255) NULL;
END;

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('receiving_officers') AND name = 'notes')
BEGIN
    ALTER TABLE receiving_officers ADD notes NVARCHAR(MAX) NULL;
END;

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('receiving_officers') AND name = 'updated_by')
BEGIN
    ALTER TABLE receiving_officers ADD updated_by BIGINT NULL;
END;

PRINT 'STEP 1 COMPLETE: New columns added to receiving_officers.';
GO

-- =============================================================================
-- STEP 2: Migrate existing receiving_officers rows that have user_id
--         Copy the user details (first_name, last_name) from the users table
-- =============================================================================

UPDATE ro
SET ro.first_name = u.first_name,
    ro.last_name  = u.last_name,
    ro.email      = u.email
FROM receiving_officers ro
INNER JOIN users u ON u.id = ro.user_id
WHERE ro.user_id IS NOT NULL
  AND ro.first_name IS NULL;

PRINT 'STEP 2 COMPLETE: Existing receiving_officers rows back-filled from users table.';
GO

-- =============================================================================
-- STEP 3: Identify junk users created by the Add Receiving Officer modal
--         These are users with @klaes.local emails, type='user', user_level='Low'
-- =============================================================================

-- REVIEW FIRST: See which users will be affected
SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.type,
       u.user_level, u.created_at, u.is_active,
       CASE WHEN ro.id IS NOT NULL THEN 'Already in receiving_officers' ELSE 'Not yet migrated' END AS migration_status
FROM users u
LEFT JOIN receiving_officers ro ON ro.user_id = u.id
WHERE u.email LIKE '%@klaes.local'
  AND u.type = 'user'
  AND u.user_level = 'Low'
ORDER BY u.created_at DESC;

PRINT 'STEP 3: Review the above results before proceeding to STEP 4.';
GO

-- =============================================================================
-- STEP 4: Migrate junk users → receiving_officers (those not already there)
--         RUN ONLY AFTER REVIEWING STEP 3 OUTPUT
-- =============================================================================

INSERT INTO receiving_officers (first_name, last_name, email, is_active, created_by, created_at, updated_at)
SELECT u.first_name, u.last_name, u.email, u.is_active, u.parent_id, u.created_at, GETDATE()
FROM users u
LEFT JOIN receiving_officers ro ON ro.user_id = u.id
WHERE u.email LIKE '%@klaes.local'
  AND u.type = 'user'
  AND u.user_level = 'Low'
  AND ro.id IS NULL;

PRINT 'STEP 4 COMPLETE: Junk users migrated to receiving_officers.';
GO

-- =============================================================================
-- STEP 5: Deactivate the junk users in the users table
--         (Soft-disable rather than hard delete for safety)
-- =============================================================================

UPDATE users
SET is_active = 0
WHERE email LIKE '%@klaes.local'
  AND type = 'user'
  AND user_level = 'Low'
  AND is_active = 1;

PRINT 'STEP 5 COMPLETE: Junk users deactivated in users table.';
GO

-- =============================================================================
-- STEP 6: Drop the user_id column from receiving_officers
--         (Run after confirming all data is migrated correctly)
-- =============================================================================

-- Drop any foreign key constraint on user_id first
DECLARE @fk_name NVARCHAR(256);
SELECT @fk_name = fk.name
FROM sys.foreign_keys fk
INNER JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.object_id
INNER JOIN sys.columns c ON c.object_id = fkc.parent_object_id AND c.column_id = fkc.parent_column_id
WHERE fk.parent_object_id = OBJECT_ID('receiving_officers')
  AND c.name = 'user_id';

IF @fk_name IS NOT NULL
BEGIN
    EXEC('ALTER TABLE receiving_officers DROP CONSTRAINT ' + @fk_name);
    PRINT 'Dropped FK constraint: ' + @fk_name;
END;

-- Drop any index on user_id
DECLARE @idx_name NVARCHAR(256);
SELECT TOP 1 @idx_name = i.name
FROM sys.indexes i
INNER JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
INNER JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
WHERE i.object_id = OBJECT_ID('receiving_officers')
  AND c.name = 'user_id'
  AND i.is_primary_key = 0;

IF @idx_name IS NOT NULL
BEGIN
    EXEC('DROP INDEX ' + @idx_name + ' ON receiving_officers');
    PRINT 'Dropped index: ' + @idx_name;
END;

-- Now drop the column
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('receiving_officers') AND name = 'user_id')
BEGIN
    ALTER TABLE receiving_officers DROP COLUMN user_id;
    PRINT 'STEP 6 COMPLETE: user_id column dropped from receiving_officers.';
END;
GO

-- =============================================================================
-- STEP 7: Add useful indexes on the new columns
-- =============================================================================

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('receiving_officers') AND name = 'IX_receiving_officers_name')
BEGIN
    CREATE INDEX IX_receiving_officers_name ON receiving_officers (first_name, last_name);
END;

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('receiving_officers') AND name = 'IX_receiving_officers_active')
BEGIN
    CREATE INDEX IX_receiving_officers_active ON receiving_officers (is_active);
END;

PRINT 'STEP 7 COMPLETE: Indexes created.';
GO

-- =============================================================================
-- OPTIONAL: Hard-delete the deactivated junk users
--           ONLY run this if you are sure no other tables reference these users
-- =============================================================================

/*
DELETE FROM users
WHERE email LIKE '%@klaes.local'
  AND type = 'user'
  AND user_level = 'Low'
  AND is_active = 0;

PRINT 'OPTIONAL: Junk users permanently deleted.';
*/
