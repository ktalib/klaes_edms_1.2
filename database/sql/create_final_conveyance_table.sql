-- Create final_conveyance table
-- This table stores final conveyance documents for sectional titling applications

IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[final_conveyance]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[final_conveyance] (
        [id] bigint IDENTITY(1,1) NOT NULL,
        [application_id] bigint NOT NULL,
        [fileno] nvarchar(255) NULL,
        [agreement_content] ntext NULL,
        [generated_date] datetime2(0) NULL,
        [status] nvarchar(50) NOT NULL DEFAULT 'generated',
        [created_by] bigint NULL,
        [updated_by] bigint NULL,
        [created_at] datetime2(0) NULL,
        [updated_at] datetime2(0) NULL,
        CONSTRAINT [PK_final_conveyance] PRIMARY KEY CLUSTERED ([id] ASC),
        CONSTRAINT [FK_final_conveyance_mother_applications] FOREIGN KEY ([application_id]) 
            REFERENCES [dbo].[mother_applications] ([id]) ON DELETE CASCADE,
        CONSTRAINT [FK_final_conveyance_users_created] FOREIGN KEY ([created_by]) 
            REFERENCES [dbo].[users] ([id]) ON DELETE SET NULL,
        CONSTRAINT [FK_final_conveyance_users_updated] FOREIGN KEY ([updated_by]) 
            REFERENCES [dbo].[users] ([id]) ON DELETE SET NULL
    );
    
    -- Create indexes for better performance
    CREATE NONCLUSTERED INDEX [IX_final_conveyance_application_id] ON [dbo].[final_conveyance]
    (
        [application_id] ASC
    );
    
    CREATE NONCLUSTERED INDEX [IX_final_conveyance_fileno] ON [dbo].[final_conveyance]
    (
        [fileno] ASC
    );
    
    CREATE NONCLUSTERED INDEX [IX_final_conveyance_status] ON [dbo].[final_conveyance]
    (
        [status] ASC
    );
    
    PRINT 'Table final_conveyance created successfully with indexes';
END
ELSE
BEGIN
    PRINT 'Table final_conveyance already exists';
END

-- Add final_conveyance_generated and final_conveyance_generated_at columns to mother_applications if they don't exist
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'final_conveyance_generated')
BEGIN
    ALTER TABLE [dbo].[mother_applications] 
    ADD [final_conveyance_generated] bit NULL DEFAULT 0;
    PRINT 'Column final_conveyance_generated added to mother_applications';
END
ELSE
BEGIN
    PRINT 'Column final_conveyance_generated already exists in mother_applications';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[mother_applications]') AND name = 'final_conveyance_generated_at')
BEGIN
    ALTER TABLE [dbo].[mother_applications] 
    ADD [final_conveyance_generated_at] datetime2(0) NULL;
    PRINT 'Column final_conveyance_generated_at added to mother_applications';
END
ELSE
BEGIN
    PRINT 'Column final_conveyance_generated_at already exists in mother_applications';
END

PRINT 'Final Conveyance database setup completed successfully';