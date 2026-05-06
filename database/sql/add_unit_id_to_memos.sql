-- Add unit_id column to memos table to support SUA unit-specific memos
-- This allows multiple memos per mother application, one for each unit

ALTER TABLE memos 
ADD unit_id INT NULL;

-- Add index for better performance when querying by unit_id
CREATE INDEX IX_memos_unit_id ON memos(unit_id);

-- Add comment to the column for documentation
EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'References subapplications.id for SUA unit-specific memos', 
    @level0type = N'SCHEMA', @level0name = N'dbo', 
    @level1type = N'TABLE',  @level1name = N'memos', 
    @level2type = N'COLUMN', @level2name = N'unit_id';