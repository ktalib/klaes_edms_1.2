-- =====================================================
-- PIC Table Schema Sync with PRA (COMPLETE VERSION)
-- =====================================================
-- This script adds ALL missing columns from the PRA table to the PIC table
-- Based on complete PRA schema with 86 columns
-- Columns are only added if they don't already exist (safe to re-run)
-- Generated: 2026-02-05 21:30
-- =====================================================

USE klaes;
GO

PRINT '========================================';
PRINT 'Starting PIC Table Schema Sync';
PRINT 'Syncing with PRA table (86 columns)';
PRINT '========================================';
GO

-- ==================== PROPERTY IDENTIFIERS ====================

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'caveat_id')
BEGIN PRINT 'Adding: caveat_id'; ALTER TABLE [dbo].[pic] ADD [caveat_id] INT NULL; END
ELSE PRINT 'Exists: caveat_id';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'fileno')
BEGIN PRINT 'Adding: fileno'; ALTER TABLE [dbo].[pic] ADD [fileno] NVARCHAR(255) NULL; END
ELSE PRINT 'Exists: fileno';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'temp_fileno')
BEGIN PRINT 'Adding: temp_fileno'; ALTER TABLE [dbo].[pic] ADD [temp_fileno] NVARCHAR(255) NULL; END
ELSE PRINT 'Exists: temp_fileno';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'oldKNNo')
BEGIN PRINT 'Adding: oldKNNo'; ALTER TABLE [dbo].[pic] ADD [oldKNNo] NVARCHAR(255) NULL; END
ELSE PRINT 'Exists: oldKNNo';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'related_file_number')
BEGIN PRINT 'Adding: related_file_number'; ALTER TABLE [dbo].[pic] ADD [related_file_number] NVARCHAR(255) NULL; END
ELSE PRINT 'Exists: related_file_number';
GO

-- ==================== PROPERTY DETAILS ====================

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'tp_no')
BEGIN PRINT 'Adding: tp_no'; ALTER TABLE [dbo].[pic] ADD [tp_no] NVARCHAR(255) NULL; END
ELSE PRINT 'Exists: tp_no';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'lpkn_no')
BEGIN PRINT 'Adding: lpkn_no'; ALTER TABLE [dbo].[pic] ADD [lpkn_no] NVARCHAR(255) NULL; END
ELSE PRINT 'Exists: lpkn_no';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'approved_plan_no')
BEGIN PRINT 'Adding: approved_plan_no'; ALTER TABLE [dbo].[pic] ADD [approved_plan_no] NVARCHAR(255) NULL; END
ELSE PRINT 'Exists: approved_plan_no';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'plot_size')
BEGIN PRINT 'Adding: plot_size'; ALTER TABLE [dbo].[pic] ADD [plot_size] NVARCHAR(255) NULL; END
ELSE PRINT 'Exists: plot_size';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'house_no')
BEGIN PRINT 'Adding: house_no'; ALTER TABLE [dbo].[pic] ADD [house_no] NVARCHAR(100) NULL; END
ELSE PRINT 'Exists: house_no';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'streetName')
BEGIN PRINT 'Adding: streetName'; ALTER TABLE [dbo].[pic] ADD [streetName] NVARCHAR(255) NULL; END
ELSE PRINT 'Exists: streetName';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'layout')
BEGIN PRINT 'Adding: layout'; ALTER TABLE [dbo].[pic] ADD [layout] NVARCHAR(255) NULL; END
ELSE PRINT 'Exists: layout';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'districtName')
BEGIN PRINT 'Adding: districtName'; ALTER TABLE [dbo].[pic] ADD [districtName] NVARCHAR(255) NULL; END
ELSE PRINT 'Exists: districtName';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'metric_sheet')
BEGIN PRINT 'Adding: metric_sheet'; ALTER TABLE [dbo].[pic] ADD [metric_sheet] NVARCHAR(255) NULL; END
ELSE PRINT 'Exists: metric_sheet';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'schedule')
BEGIN PRINT 'Adding: schedule'; ALTER TABLE [dbo].[pic] ADD [schedule] NVARCHAR(255) NULL; END
ELSE PRINT 'Exists: schedule';
GO

-- ==================== TRANSACTION INFO ====================

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'title_type')
BEGIN PRINT 'Adding: title_type'; ALTER TABLE [dbo].[pic] ADD [title_type] NVARCHAR(255) NULL; END
ELSE PRINT 'Exists: title_type';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'regNo')
BEGIN PRINT 'Adding: regNo'; ALTER TABLE [dbo].[pic] ADD [regNo] NVARCHAR(100) NULL; END
ELSE PRINT 'Exists: regNo';
GO

-- ==================== PARTY FIELDS ====================

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'party_1')
BEGIN PRINT 'Adding: party_1'; ALTER TABLE [dbo].[pic] ADD [party_1] NVARCHAR(500) NULL; END
ELSE PRINT 'Exists: party_1';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'party_2')
BEGIN PRINT 'Adding: party_2'; ALTER TABLE [dbo].[pic] ADD [party_2] NVARCHAR(500) NULL; END
ELSE PRINT 'Exists: party_2';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'party_3')
BEGIN PRINT 'Adding: party_3'; ALTER TABLE [dbo].[pic] ADD [party_3] NVARCHAR(500) NULL; END
ELSE PRINT 'Exists: party_3';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'party_4')
BEGIN PRINT 'Adding: party_4'; ALTER TABLE [dbo].[pic] ADD [party_4] NVARCHAR(500) NULL; END
ELSE PRINT 'Exists: party_4';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'Mortgagor_2')
BEGIN PRINT 'Adding: Mortgagor_2'; ALTER TABLE [dbo].[pic] ADD [Mortgagor_2] NVARCHAR(500) NULL; END
ELSE PRINT 'Exists: Mortgagor_2';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'Surrenderor_2')
BEGIN PRINT 'Adding: Surrenderor_2'; ALTER TABLE [dbo].[pic] ADD [Surrenderor_2] NVARCHAR(500) NULL; END
ELSE PRINT 'Exists: Surrenderor_2';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'Releasor')
BEGIN PRINT 'Adding: Releasor'; ALTER TABLE [dbo].[pic] ADD [Releasor] NVARCHAR(500) NULL; END
ELSE PRINT 'Exists: Releasor';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'Releasee')
BEGIN PRINT 'Adding: Releasee'; ALTER TABLE [dbo].[pic] ADD [Releasee] NVARCHAR(500) NULL; END
ELSE PRINT 'Exists: Releasee';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'Donor')
BEGIN PRINT 'Adding: Donor'; ALTER TABLE [dbo].[pic] ADD [Donor] NVARCHAR(500) NULL; END
ELSE PRINT 'Exists: Donor';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'Donee')
BEGIN PRINT 'Adding: Donee'; ALTER TABLE [dbo].[pic] ADD [Donee] NVARCHAR(500) NULL; END
ELSE PRINT 'Exists: Donee';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'Vendor')
BEGIN PRINT 'Adding: Vendor'; ALTER TABLE [dbo].[pic] ADD [Vendor] NVARCHAR(500) NULL; END
ELSE PRINT 'Exists: Vendor';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'Purchaser')
BEGIN PRINT 'Adding: Purchaser'; ALTER TABLE [dbo].[pic] ADD [Purchaser] NVARCHAR(500) NULL; END
ELSE PRINT 'Exists: Purchaser';
GO

-- ==================== DATE FIELDS ====================

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'date_recommended')
BEGIN PRINT 'Adding: date_recommended'; ALTER TABLE [dbo].[pic] ADD [date_recommended] DATE NULL; END
ELSE PRINT 'Exists: date_recommended';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'date_approved')
BEGIN PRINT 'Adding: date_approved'; ALTER TABLE [dbo].[pic] ADD [date_approved] DATE NULL; END
ELSE PRINT 'Exists: date_approved';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'lease_begins')
BEGIN PRINT 'Adding: lease_begins'; ALTER TABLE [dbo].[pic] ADD [lease_begins] DATE NULL; END
ELSE PRINT 'Exists: lease_begins';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'lease_expires')
BEGIN PRINT 'Adding: lease_expires'; ALTER TABLE [dbo].[pic] ADD [lease_expires] DATE NULL; END
ELSE PRINT 'Exists: lease_expires';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'date_expired')
BEGIN PRINT 'Adding: date_expired'; ALTER TABLE [dbo].[pic] ADD [date_expired] DATE NULL; END
ELSE PRINT 'Exists: date_expired';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'assignment_date')
BEGIN PRINT 'Adding: assignment_date'; ALTER TABLE [dbo].[pic] ADD [assignment_date] DATE NULL; END
ELSE PRINT 'Exists: assignment_date';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'surrender_date')
BEGIN PRINT 'Adding: surrender_date'; ALTER TABLE [dbo].[pic] ADD [surrender_date] DATE NULL; END
ELSE PRINT 'Exists: surrender_date';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'revoked_date')
BEGIN PRINT 'Adding: revoked_date'; ALTER TABLE [dbo].[pic] ADD [revoked_date] DATE NULL; END
ELSE PRINT 'Exists: revoked_date';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'deeds_date')
BEGIN PRINT 'Adding: deeds_date'; ALTER TABLE [dbo].[pic] ADD [deeds_date] DATE NULL; END
ELSE PRINT 'Exists: deeds_date';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'deeds_time')
BEGIN PRINT 'Adding: deeds_time'; ALTER TABLE [dbo].[pic] ADD [deeds_time] VARCHAR(10) NULL; END
ELSE PRINT 'Exists: deeds_time';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'date_created')
BEGIN PRINT 'Adding: date_created'; ALTER TABLE [dbo].[pic] ADD [date_created] DATETIME NULL; END
ELSE PRINT 'Exists: date_created';
GO

-- ==================== CAVEAT FIELDS ====================

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'is_caveated')
BEGIN PRINT 'Adding: is_caveated'; ALTER TABLE [dbo].[pic] ADD [is_caveated] BIT NULL DEFAULT 0; END
ELSE PRINT 'Exists: is_caveated';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'caveated_comment')
BEGIN PRINT 'Adding: caveated_comment'; ALTER TABLE [dbo].[pic] ADD [caveated_comment] NVARCHAR(MAX) NULL; END
ELSE PRINT 'Exists: caveated_comment';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'regranted_from')
BEGIN PRINT 'Adding: regranted_from'; ALTER TABLE [dbo].[pic] ADD [regranted_from] NVARCHAR(255) NULL; END
ELSE PRINT 'Exists: regranted_from';
GO

-- ==================== MIGRATION & METADATA ====================

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'source')
BEGIN PRINT 'Adding: source'; ALTER TABLE [dbo].[pic] ADD [source] NVARCHAR(255) NULL; END
ELSE PRINT 'Exists: source';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'migration_source')
BEGIN PRINT 'Adding: migration_source'; ALTER TABLE [dbo].[pic] ADD [migration_source] NVARCHAR(255) NULL; END
ELSE PRINT 'Exists: migration_source';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'date_migrated')
BEGIN PRINT 'Adding: date_migrated'; ALTER TABLE [dbo].[pic] ADD [date_migrated] DATETIME NULL; END
ELSE PRINT 'Exists: date_migrated';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'migrated_by')
BEGIN PRINT 'Adding: migrated_by'; ALTER TABLE [dbo].[pic] ADD [migrated_by] NVARCHAR(255) NULL; END
ELSE PRINT 'Exists: migrated_by';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'updated_by')
BEGIN PRINT 'Adding: updated_by'; ALTER TABLE [dbo].[pic] ADD [updated_by] INT NULL; END
ELSE PRINT 'Exists: updated_by';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'test_control')
BEGIN PRINT 'Adding: test_control'; ALTER TABLE [dbo].[pic] ADD [test_control] NVARCHAR(50) NULL; END
ELSE PRINT 'Exists: test_control';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'comments')
BEGIN PRINT 'Adding: comments'; ALTER TABLE [dbo].[pic] ADD [comments] NVARCHAR(MAX) NULL; END
ELSE PRINT 'Exists: comments';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'remarks')
BEGIN PRINT 'Adding: remarks'; ALTER TABLE [dbo].[pic] ADD [remarks] NVARCHAR(MAX) NULL; END
ELSE PRINT 'Exists: remarks';
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' AND COLUMN_NAME = 'is_mapped')
BEGIN PRINT 'Adding: is_mapped'; ALTER TABLE [dbo].[pic] ADD [is_mapped] BIT NULL DEFAULT 0; END
ELSE PRINT 'Exists: is_mapped';
GO

-- ==========================================
PRINT '========================================';
PRINT 'PIC Table Schema Sync COMPLETED!';
PRINT 'All PRA columns now available in PIC';
PRINT '========================================';
GO
