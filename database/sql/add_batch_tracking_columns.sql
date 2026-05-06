-- Add batch tracking columns to file_indexings table
-- This script adds columns to track which files have been used in batch generation

USE [klas]
GO

-- Add batch_generated column (boolean, default false)
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'batch_generated')
BEGIN
    ALTER TABLE file_indexings ADD batch_generated BIT NOT NULL DEFAULT 0;
    PRINT 'Added batch_generated column';
END
ELSE
BEGIN
    PRINT 'batch_generated column already exists';
END

-- Add last_batch_id column (stores the batch ID that generated this file)
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'last_batch_id')
BEGIN
    ALTER TABLE file_indexings ADD last_batch_id VARCHAR(50) NULL;
    PRINT 'Added last_batch_id column';
END
ELSE
BEGIN
    PRINT 'last_batch_id column already exists';
END

-- Add batch_generated_at column (timestamp when batch was generated)
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'batch_generated_at')
BEGIN
    ALTER TABLE file_indexings ADD batch_generated_at DATETIME2(0) NULL;
    PRINT 'Added batch_generated_at column';
END
ELSE
BEGIN
    PRINT 'batch_generated_at column already exists';
END

-- Add batch_generated_by column (user ID who generated the batch)
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'batch_generated_by')
BEGIN
    ALTER TABLE file_indexings ADD batch_generated_by BIGINT NULL;
    PRINT 'Added batch_generated_by column';
END
ELSE
BEGIN
    PRINT 'batch_generated_by column already exists';
END

-- Create indexes for performance
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_file_indexings_batch_generated')
BEGIN
    CREATE INDEX IX_file_indexings_batch_generated ON file_indexings(batch_generated);
    PRINT 'Created index IX_file_indexings_batch_generated';
END
ELSE
BEGIN
    PRINT 'Index IX_file_indexings_batch_generated already exists';
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_file_indexings_last_batch_id')
BEGIN
    CREATE INDEX IX_file_indexings_last_batch_id ON file_indexings(last_batch_id);
    PRINT 'Created index IX_file_indexings_last_batch_id';
END
ELSE
BEGIN
    PRINT 'Index IX_file_indexings_last_batch_id already exists';
END

PRINT 'All batch tracking columns added successfully to file_indexings table!';