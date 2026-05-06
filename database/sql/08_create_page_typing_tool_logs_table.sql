/*
    Script: 08_create_page_typing_tool_logs_table.sql
    Purpose: Create the page_typing_tool_logs table for auditing viewer tool usage
    Database: SQL Server (KLAES GIS EDMS)
*/

IF OBJECT_ID(N'[dbo].[page_typing_tool_logs]', N'U') IS NOT NULL
BEGIN
    DROP TABLE [dbo].[page_typing_tool_logs];
END
GO

CREATE TABLE [dbo].[page_typing_tool_logs]
(
    [id] BIGINT IDENTITY(1, 1) NOT NULL PRIMARY KEY,
    [file_indexing_id] BIGINT NOT NULL,
    [scanning_id] BIGINT NULL,
    [file_path] NVARCHAR(255) NULL,
    [action] NVARCHAR(50) NOT NULL,
    [rotation] DECIMAL(6, 2) NOT NULL CONSTRAINT [DF_page_typing_tool_logs_rotation] DEFAULT (0),
    [scale] DECIMAL(8, 4) NOT NULL CONSTRAINT [DF_page_typing_tool_logs_scale] DEFAULT (1),
    [translate_x] DECIMAL(10, 2) NOT NULL CONSTRAINT [DF_page_typing_tool_logs_translate_x] DEFAULT (0),
    [translate_y] DECIMAL(10, 2) NOT NULL CONSTRAINT [DF_page_typing_tool_logs_translate_y] DEFAULT (0),
    [details] NVARCHAR(MAX) NULL,
    [performed_by] BIGINT NULL,
    [ip_address] NVARCHAR(45) NULL,
    [user_agent] NVARCHAR(500) NULL,
    [created_at] DATETIME2(0) NOT NULL CONSTRAINT [DF_page_typing_tool_logs_created_at] DEFAULT (SYSUTCDATETIME()),
    [updated_at] DATETIME2(0) NOT NULL CONSTRAINT [DF_page_typing_tool_logs_updated_at] DEFAULT (SYSUTCDATETIME())
);
GO

CREATE INDEX [IX_page_typing_tool_logs_file_indexing_id]
    ON [dbo].[page_typing_tool_logs] ([file_indexing_id]);
GO

CREATE INDEX [IX_page_typing_tool_logs_scanning_id]
    ON [dbo].[page_typing_tool_logs] ([scanning_id]);
GO

CREATE INDEX [IX_page_typing_tool_logs_action]
    ON [dbo].[page_typing_tool_logs] ([action]);
GO

CREATE INDEX [IX_page_typing_tool_logs_performed_by]
    ON [dbo].[page_typing_tool_logs] ([performed_by]);
GO

CREATE INDEX [IX_page_typing_tool_logs_created_at]
    ON [dbo].[page_typing_tool_logs] ([created_at]);
GO
