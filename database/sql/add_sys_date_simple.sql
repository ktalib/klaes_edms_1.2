-- Simple sys_date column addition script
USE klas;
GO

-- Add sys_date to mother_applications
IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'mother_applications' 
    AND COLUMN_NAME = 'sys_date'
)
BEGIN
    ALTER TABLE [dbo].[mother_applications] ADD [sys_date] DATETIME2(7) NULL;
    PRINT 'Added sys_date column to mother_applications';
END
GO

-- Update mother_applications records
UPDATE [dbo].[mother_applications] 
SET [sys_date] = DATEADD(SECOND, [id], '2024-01-01 00:00:00')
WHERE [sys_date] IS NULL;
PRINT 'Updated mother_applications records';
GO

-- Make mother_applications sys_date NOT NULL
ALTER TABLE [dbo].[mother_applications] ALTER COLUMN [sys_date] DATETIME2(7) NOT NULL;
PRINT 'Set mother_applications sys_date to NOT NULL';
GO

-- Add default constraint to mother_applications
IF NOT EXISTS (SELECT * FROM sys.default_constraints WHERE name = 'DF_mother_applications_sys_date')
BEGIN
    ALTER TABLE [dbo].[mother_applications] ADD CONSTRAINT DF_mother_applications_sys_date DEFAULT GETDATE() FOR [sys_date];
    PRINT 'Added default constraint to mother_applications';
END
GO

-- Add sys_date to subapplications
IF NOT EXISTS (
    SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'subapplications' 
    AND COLUMN_NAME = 'sys_date'
)
BEGIN
    ALTER TABLE [dbo].[subapplications] ADD [sys_date] DATETIME2(7) NULL;
    PRINT 'Added sys_date column to subapplications';
END
GO

-- Update subapplications records
UPDATE [dbo].[subapplications] 
SET [sys_date] = DATEADD(SECOND, [id], '2024-01-01 00:00:00')
WHERE [sys_date] IS NULL;
PRINT 'Updated subapplications records';
GO

-- Make subapplications sys_date NOT NULL
ALTER TABLE [dbo].[subapplications] ALTER COLUMN [sys_date] DATETIME2(7) NOT NULL;
PRINT 'Set subapplications sys_date to NOT NULL';
GO

-- Add default constraint to subapplications
IF NOT EXISTS (SELECT * FROM sys.default_constraints WHERE name = 'DF_subapplications_sys_date')
BEGIN
    ALTER TABLE [dbo].[subapplications] ADD CONSTRAINT DF_subapplications_sys_date DEFAULT GETDATE() FOR [sys_date];
    PRINT 'Added default constraint to subapplications';
END
GO

PRINT 'Completed sys_date column addition for both tables';