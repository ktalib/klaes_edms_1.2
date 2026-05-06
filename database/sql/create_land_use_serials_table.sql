-- Migration: Create land_use_serials table for atomic file number generation
-- File: database/sql/create_land_use_serials_table.sql

CREATE TABLE [dbo].[land_use_serials] (
    id INT IDENTITY(1,1) PRIMARY KEY,
    land_use_type VARCHAR(50) NOT NULL,    -- 'COMMERCIAL', 'RESIDENTIAL', 'INDUSTRIAL', 'MIXED'
    prefix VARCHAR(20) NOT NULL,           -- 'ST-COM', 'ST-RES', 'ST-IND', 'ST-MIXED'
    year INT NOT NULL,                     -- e.g. 2025
    current_serial INT NOT NULL DEFAULT 0, -- last used serial number
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE(),
    
    -- Unique constraint to prevent duplicate entries per land_use and year
    CONSTRAINT UQ_land_use_year UNIQUE (land_use_type, year)
);

-- Initialize the table with current year data for all land use types
INSERT INTO [dbo].[land_use_serials] (land_use_type, prefix, year, current_serial)
VALUES 
    ('COMMERCIAL', 'ST-COM', 2025, 0),
    ('RESIDENTIAL', 'ST-RES', 2025, 0),
    ('INDUSTRIAL', 'ST-IND', 2025, 0),
    ('MIXED', 'ST-MIXED', 2025, 0);

-- Create stored procedure for atomic serial number generation
GO
CREATE PROCEDURE [dbo].[GetNextFileSerial]
    @LandUseType VARCHAR(50),
    @Year INT,
    @NextSerial INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRANSACTION;
    
    -- Get the current serial and increment it atomically
    UPDATE [dbo].[land_use_serials] 
    SET current_serial = current_serial + 1,
        updated_at = GETDATE()
    WHERE land_use_type = @LandUseType AND year = @Year;
    
    -- If no record exists for this land use and year, create it
    IF @@ROWCOUNT = 0
    BEGIN
        DECLARE @Prefix VARCHAR(20);
        SET @Prefix = CASE 
            WHEN @LandUseType = 'COMMERCIAL' THEN 'ST-COM'
            WHEN @LandUseType = 'RESIDENTIAL' THEN 'ST-RES'
            WHEN @LandUseType = 'INDUSTRIAL' THEN 'ST-IND'
            WHEN @LandUseType = 'MIXED' THEN 'ST-MIXED'
            ELSE 'ST-COM'
        END;
        
        INSERT INTO [dbo].[land_use_serials] (land_use_type, prefix, year, current_serial)
        VALUES (@LandUseType, @Prefix, @Year, 1);
        
        SET @NextSerial = 1;
    END
    ELSE
    BEGIN
        -- Get the new serial number
        SELECT @NextSerial = current_serial 
        FROM [dbo].[land_use_serials] 
        WHERE land_use_type = @LandUseType AND year = @Year;
    END
    
    COMMIT TRANSACTION;
END;
GO

-- Grant permissions (adjust as needed for your user)
-- GRANT EXECUTE ON [dbo].[GetNextFileSerial] TO [your_app_user];