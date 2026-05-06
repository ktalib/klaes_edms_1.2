-- =====================================================================
-- User Activity Log System - Phase 1 Database Schema Enhancement
-- =====================================================================
-- Script: Enhance user_activity_logs and user_activity_log_settings tables
-- Created: November 10, 2025
-- Purpose: Add new columns to support session duration tracking, status 
--          management, file number integration, and performance indexing
-- =====================================================================

-- Set database context
USE [klaes];
GO

-- =====================================================================
-- PHASE 1A: Enhance user_activity_logs table
-- =====================================================================

-- Add duration_minutes column (Session duration in minutes)
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'user_activity_logs' AND COLUMN_NAME = 'duration_minutes'
)
BEGIN
    ALTER TABLE user_activity_logs
    ADD duration_minutes INT NULL
        CONSTRAINT DF_user_activity_logs_duration_minutes DEFAULT NULL;
    
    PRINT '✓ Added column: duration_minutes';
END
ELSE
    PRINT '⊘ Column duration_minutes already exists';
GO

-- Add status column (Online/Offline/Idle)
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'user_activity_logs' AND COLUMN_NAME = 'status'
)
BEGIN
    ALTER TABLE user_activity_logs
    ADD status VARCHAR(20) NOT NULL
        CONSTRAINT DF_user_activity_logs_status DEFAULT 'Online'
        CONSTRAINT CK_user_activity_logs_status CHECK (status IN ('Online', 'Offline', 'Idle'));
    
    PRINT '✓ Added column: status';
END
ELSE
    PRINT '⊘ Column status already exists';
GO

-- Add last_seen_at column (Last activity timestamp)
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'user_activity_logs' AND COLUMN_NAME = 'last_seen_at'
)
BEGIN
    ALTER TABLE user_activity_logs
    ADD last_seen_at DATETIME2(0) NULL
        CONSTRAINT DF_user_activity_logs_last_seen_at DEFAULT NULL;
    
    PRINT '✓ Added column: last_seen_at';
END
ELSE
    PRINT '⊘ Column last_seen_at already exists';
GO

-- Add related_file_number column (File/Application number integration)
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'user_activity_logs' AND COLUMN_NAME = 'related_file_number'
)
BEGIN
    ALTER TABLE user_activity_logs
    ADD related_file_number VARCHAR(50) NULL
        CONSTRAINT DF_user_activity_logs_related_file_number DEFAULT NULL;
    
    PRINT '✓ Added column: related_file_number';
END
ELSE
    PRINT '⊘ Column related_file_number already exists';
GO

-- Add test_control column (TEST/PRO environment separation)
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'user_activity_logs' AND COLUMN_NAME = 'test_control'
)
BEGIN
    ALTER TABLE user_activity_logs
    ADD test_control VARCHAR(10) NOT NULL
        CONSTRAINT DF_user_activity_logs_test_control DEFAULT 'PRO'
        CONSTRAINT CK_user_activity_logs_test_control CHECK (test_control IN ('TEST', 'PRO'));
    
    PRINT '✓ Added column: test_control';
END
ELSE
    PRINT '⊘ Column test_control already exists';
GO

-- Add indexed_at column (Indexing timestamp for reporting)
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'user_activity_logs' AND COLUMN_NAME = 'indexed_at'
)
BEGIN
    ALTER TABLE user_activity_logs
    ADD indexed_at DATETIME2(0) NULL
        CONSTRAINT DF_user_activity_logs_indexed_at DEFAULT NULL;
    
    PRINT '✓ Added column: indexed_at';
END
ELSE
    PRINT '⊘ Column indexed_at already exists';
GO

-- =====================================================================
-- PHASE 1B: Create indexes on user_activity_logs
-- =====================================================================

-- Create composite index for optimal query performance
-- Supports: Find users by status within date range, ordered by login time
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes 
    WHERE name = 'idx_activity_user_status_login'
)
BEGIN
    CREATE NONCLUSTERED INDEX [idx_activity_user_status_login]
    ON [user_activity_logs] (
        [user_id] ASC,
        [status] ASC,
        [login_time] ASC
    )
    WITH (FILLFACTOR = 90);
    
    PRINT '✓ Created composite index: idx_activity_user_status_login';
END
ELSE
    PRINT '⊘ Index idx_activity_user_status_login already exists';
GO

-- Create index on status column for status-based queries
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes 
    WHERE name = 'idx_activity_status'
)
BEGIN
    CREATE NONCLUSTERED INDEX [idx_activity_status]
    ON [user_activity_logs] ([status] ASC)
    WITH (FILLFACTOR = 90);
    
    PRINT '✓ Created index: idx_activity_status';
END
ELSE
    PRINT '⊘ Index idx_activity_status already exists';
GO

-- Create index on last_seen_at column for idle detection
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes 
    WHERE name = 'idx_activity_last_seen_at'
)
BEGIN
    CREATE NONCLUSTERED INDEX [idx_activity_last_seen_at]
    ON [user_activity_logs] ([last_seen_at] ASC)
    WITH (FILLFACTOR = 90);
    
    PRINT '✓ Created index: idx_activity_last_seen_at';
END
ELSE
    PRINT '⊘ Index idx_activity_last_seen_at already exists';
GO

-- Create index on test_control for environment separation
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes 
    WHERE name = 'idx_activity_test_control'
)
BEGIN
    CREATE NONCLUSTERED INDEX [idx_activity_test_control]
    ON [user_activity_logs] ([test_control] ASC)
    WITH (FILLFACTOR = 90);
    
    PRINT '✓ Created index: idx_activity_test_control';
END
ELSE
    PRINT '⊘ Index idx_activity_test_control already exists';
GO

-- =====================================================================
-- PHASE 1C: Enhance user_activity_log_settings table
-- =====================================================================

-- Add timezone column
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'user_activity_log_settings' AND COLUMN_NAME = 'timezone'
)
BEGIN
    ALTER TABLE user_activity_log_settings
    ADD timezone VARCHAR(50) NOT NULL
        CONSTRAINT DF_user_activity_log_settings_timezone DEFAULT 'UTC';
    
    PRINT '✓ Added column: timezone';
END
ELSE
    PRINT '⊘ Column timezone already exists';
GO

-- Add notes column
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'user_activity_log_settings' AND COLUMN_NAME = 'notes'
)
BEGIN
    ALTER TABLE user_activity_log_settings
    ADD notes TEXT NULL
        CONSTRAINT DF_user_activity_log_settings_notes DEFAULT NULL;
    
    PRINT '✓ Added column: notes';
END
ELSE
    PRINT '⊘ Column notes already exists';
GO

-- =====================================================================
-- PHASE 1D: Verify schema changes
-- =====================================================================

PRINT '';
PRINT '=== VERIFICATION REPORT ===';
PRINT '';

-- Check user_activity_logs columns
PRINT 'Columns in user_activity_logs table:';
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'user_activity_logs'
AND COLUMN_NAME IN ('duration_minutes', 'status', 'last_seen_at', 'related_file_number', 'test_control', 'indexed_at')
ORDER BY ORDINAL_POSITION;

PRINT '';
PRINT 'Indexes on user_activity_logs table:';
SELECT 
    name AS IndexName,
    type_desc AS IndexType
FROM sys.indexes
WHERE object_id = OBJECT_ID('user_activity_logs')
AND name LIKE 'idx_activity%'
ORDER BY name;

PRINT '';
PRINT 'Columns in user_activity_log_settings table:';
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'user_activity_log_settings'
AND COLUMN_NAME IN ('timezone', 'notes')
ORDER BY ORDINAL_POSITION;

PRINT '';
PRINT '=== SCHEMA ENHANCEMENT COMPLETE ===';
PRINT 'All new columns and indexes have been created successfully.';
PRINT 'Phase 1: Database Schema Enhancement is ready for Phase 2 (Eloquent Models).';
GO
