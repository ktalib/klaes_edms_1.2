-- Create table for storing recommended site plan sketch information
CREATE TABLE recommended_site_plans (
    id INT IDENTITY(1,1) PRIMARY KEY,
    application_id NVARCHAR(50) NOT NULL,
    recommended_file NVARCHAR(500) NOT NULL,
    description NVARCHAR(1000) NULL,
    status NVARCHAR(50) DEFAULT 'Uploaded',
    created_by INT NULL,
    uploaded_by INT NULL,
    created_at DATETIME2 DEFAULT GETDATE(),
    updated_at DATETIME2 DEFAULT GETDATE(),
    
    -- Index for faster lookups
    INDEX IX_recommended_site_plans_application_id (application_id)
);

-- Add comments for documentation
EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Stores recommended site plan sketch files for applications', 
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'recommended_site_plans';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'Application ID (can be primary or sub application)', 
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'recommended_site_plans',
    @level2type = N'COLUMN', @level2name = N'application_id';

EXEC sp_addextendedproperty 
    @name = N'MS_Description', 
    @value = N'File path to the uploaded recommended site plan sketch', 
    @level0type = N'SCHEMA', @level0name = N'dbo',
    @level1type = N'TABLE', @level1name = N'recommended_site_plans',
    @level2type = N'COLUMN', @level2name = N'recommended_file';