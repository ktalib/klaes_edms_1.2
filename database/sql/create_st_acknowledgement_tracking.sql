-- Create a small tracking table for Acknowledgement Sheet generation/prints (SQL Server)
IF NOT EXISTS (
    SELECT * FROM sys.objects 
    WHERE object_id = OBJECT_ID(N'[dbo].[st_acknowledgement_tracking]')
      AND type in (N'U')
)
BEGIN
    CREATE TABLE [dbo].[st_acknowledgement_tracking] (
        [id] INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        [application_id] INT NOT NULL,
        [generated_at] DATETIME NOT NULL CONSTRAINT [DF_st_ackn_generated_at] DEFAULT (GETDATE()),
        [generated_by_user_id] INT NULL,
        [generated_by_user_name] NVARCHAR(255) NULL,
        [printed_count] INT NOT NULL CONSTRAINT [DF_st_ackn_printed_count] DEFAULT (0),
        [first_printed_at] DATETIME NULL,
        [last_printed_at] DATETIME NULL,
        [last_printed_by_user_id] INT NULL,
        [last_printed_by_user_name] NVARCHAR(255) NULL
    );

    CREATE UNIQUE INDEX [UX_st_ackn_application_id]
        ON [dbo].[st_acknowledgement_tracking]([application_id]);
END
