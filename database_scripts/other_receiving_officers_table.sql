-- Create table for Other Receiving Officers
-- This table is separate from the users table to handle temporary/external receiving officers
-- who don't need full system access but can receive files in the tracker system

CREATE TABLE other_receiving_officers (
    id BIGINT IDENTITY(1,1) PRIMARY KEY,
    first_name NVARCHAR(255) NOT NULL,
    last_name NVARCHAR(255) NOT NULL,
    full_name AS (first_name + ' ' + last_name) PERSISTED,
    phone_number NVARCHAR(20) NULL,
    email NVARCHAR(255) NULL,
    office_name NVARCHAR(255) NULL,
    designation NVARCHAR(255) NULL,
    department NVARCHAR(255) NULL,
    notes NVARCHAR(MAX) NULL,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NULL,
    is_active BIT NOT NULL DEFAULT 1,
    created_at DATETIME2(0) NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME2(0) NOT NULL DEFAULT GETDATE(),
    
    -- Foreign key to users table for who created this officer record
    CONSTRAINT FK_other_receiving_officers_created_by 
        FOREIGN KEY (created_by) REFERENCES users(id),
    
    -- Foreign key to users table for who last updated this officer record
    CONSTRAINT FK_other_receiving_officers_updated_by 
        FOREIGN KEY (updated_by) REFERENCES users(id),
    
    -- Index for searching by name
    INDEX IX_other_receiving_officers_name (first_name, last_name),
    
    -- Index for active status
    INDEX IX_other_receiving_officers_active (is_active),
    
    -- Index for created by
    INDEX IX_other_receiving_officers_created_by (created_by),
);

-- Add trigger to automatically update the updated_at column
CREATE TRIGGER tr_other_receiving_officers_updated_at
ON other_receiving_officers
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE other_receiving_officers
    SET updated_at = GETDATE()
    WHERE id IN (SELECT id FROM inserted);
END;

-- Insert a comment about the table
EXEC sys.sp_addextendedproperty 
    @name = N'MS_Description',
    @value = N'Table to store receiving officers who are external to the system and do not need full user accounts. These are typically temporary or external officers who can receive files but do not need system login access.',
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'other_receiving_officers';