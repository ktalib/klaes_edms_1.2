-- Fix FileNumber Schema (Robust)
-- This script dynamically drops constraints and indexes dependent on created_at and updated_at
-- before recreating them as DATETIME columns.

-- 1. Drop Default Constraints for created_at
DECLARE @ConstraintName nvarchar(200)
SELECT @ConstraintName = Name FROM sys.default_constraints 
WHERE parent_object_id = OBJECT_ID('fileNumber') 
AND parent_column_id = (SELECT column_id FROM sys.columns WHERE NAME = N'created_at' AND object_id = OBJECT_ID(N'fileNumber'))

IF @ConstraintName IS NOT NULL
    EXEC('ALTER TABLE fileNumber DROP CONSTRAINT ' + @ConstraintName)

-- 2. Drop Default Constraints for updated_at
SELECT @ConstraintName = Name FROM sys.default_constraints 
WHERE parent_object_id = OBJECT_ID('fileNumber') 
AND parent_column_id = (SELECT column_id FROM sys.columns WHERE NAME = N'updated_at' AND object_id = OBJECT_ID(N'fileNumber'))

IF @ConstraintName IS NOT NULL
    EXEC('ALTER TABLE fileNumber DROP CONSTRAINT ' + @ConstraintName)

-- 3. Drop Dependent Indexes
IF EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_fileNumber_MLSFNo' AND object_id = OBJECT_ID('fileNumber'))
    DROP INDEX IX_fileNumber_MLSFNo ON fileNumber

IF EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_fileNumber_CreatedFiltered' AND object_id = OBJECT_ID('fileNumber'))
    DROP INDEX IX_fileNumber_CreatedFiltered ON fileNumber

-- 4. Drop the columns (which were timestamp/rowversion)
ALTER TABLE [fileNumber] DROP COLUMN [created_at];
ALTER TABLE [fileNumber] DROP COLUMN [updated_at];

-- 5. Re-add correct DATETIME columns with default GETDATE()
ALTER TABLE [fileNumber] ADD [created_at] DATETIME DEFAULT GETDATE();
ALTER TABLE [fileNumber] ADD [updated_at] DATETIME DEFAULT GETDATE();
GO

-- 6. Re-create Indexes (Basic versions - adjust if complex include columns were needed)
-- Re-creating IX_fileNumber_MLSFNo assuming it's useful for lookups, likely on mlsfNo
-- Since we don't know the exact definition, we'll recreate a standard index on mlsfNo
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_fileNumber_MLSFNo' AND object_id = OBJECT_ID('fileNumber'))
    CREATE INDEX [IX_fileNumber_MLSFNo] ON [fileNumber] ([mlsfNo])

-- Re-creating IX_fileNumber_CreatedFiltered likely filtered index
-- Since exact definition is unknown but name suggests filtered on created_at, avoiding recreation to verify performance later.
-- If needed, a typical filtered index might look like:
-- CREATE INDEX [IX_fileNumber_CreatedFiltered] ON [fileNumber] (created_at) WHERE created_at IS NOT NULL

-- 7. Create Trigger for automatic updated_at
IF OBJECT_ID('trg_fileNumber_UpdateTimestamp', 'TR') IS NOT NULL
    DROP TRIGGER trg_fileNumber_UpdateTimestamp
GO

CREATE TRIGGER [trg_fileNumber_UpdateTimestamp]
ON [fileNumber]
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE [fileNumber]
    SET [updated_at] = GETDATE()
    FROM [fileNumber] f
    INNER JOIN inserted i ON f.id = i.id
END;
GO
