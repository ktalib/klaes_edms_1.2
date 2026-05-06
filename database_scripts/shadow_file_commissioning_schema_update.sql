-- SHADOW FILE COMMISSIONING - Physical Planning Module
-- Database Schema Update Script
-- Execute this script to add timestamp columns to fileNumber table

USE [KLAES_EDMS_LIVE]; -- Adjust database name as needed

-- Add timestamp columns to fileNumber table for Shadow File Commissioning
ALTER TABLE fileNumber
ADD 
    -- Lands Module Matching Timestamps
    pp_lands_date_matched DATE NULL,
    pp_lands_time_matched TIME NULL,
    
    -- ST Module Matching Timestamps  
    pp_st_date_matched DATE NULL,
    pp_st_time_matched TIME NULL,
    
    -- SLTR Module Matching Timestamps
    pp_sltr_date_matched DATE NULL,
    pp_sltr_time_matched TIME NULL;

-- Add comments for documentation
EXEC sp_addextendedproperty 
    @name = N'MS_Description',
    @value = N'Date when file was matched in Lands module under new Shadow File Commissioning architecture',
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'fileNumber',
    @level2type = N'COLUMN', @level2name = N'pp_lands_date_matched';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Time when file was matched in Lands module under new Shadow File Commissioning architecture',
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'fileNumber',
    @level2type = N'COLUMN', @level2name = N'pp_lands_time_matched';

EXEC sp_addextendedproperty 
    @name = N'MS_Description',
    @value = N'Date when file was matched in ST module under new Shadow File Commissioning architecture',
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'fileNumber',
    @level2type = N'COLUMN', @level2name = N'pp_st_date_matched';

EXEC sp_addextendedproperty 
    @name = N'MS_Description',
    @value = N'Time when file was matched in ST module under new Shadow File Commissioning architecture', 
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'fileNumber',
    @level2type = N'COLUMN', @level2name = N'pp_st_time_matched';

EXEC sp_addextendedproperty 
    @name = N'MS_Description',
    @value = N'Date when file was matched in SLTR module under new Shadow File Commissioning architecture',
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'fileNumber', 
    @level2type = N'COLUMN', @level2name = N'pp_sltr_date_matched';

EXEC sp_addextendedproperty 
    @name = N'MS_Description',
    @value = N'Time when file was matched in SLTR module under new Shadow File Commissioning architecture',
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'fileNumber',
    @level2type = N'COLUMN', @level2name = N'pp_sltr_time_matched';

PRINT 'Shadow File Commissioning database schema update completed successfully.';
PRINT 'New timestamp columns added to fileNumber table:';
PRINT '- pp_lands_date_matched, pp_lands_time_matched (Lands module)';
PRINT '- pp_st_date_matched, pp_st_time_matched (ST module)';
PRINT '- pp_sltr_date_matched, pp_sltr_time_matched (SLTR module)';