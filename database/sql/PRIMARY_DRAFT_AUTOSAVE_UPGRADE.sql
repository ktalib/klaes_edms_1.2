/*
    Upgrade script for the Primary Application draft autosave system.
    Adds metadata columns, indexes, and supporting tables for version history and collaborators.
*/

/* Ensure draft_id column exists and is indexed */
IF COL_LENGTH('dbo.mother_application_draft', 'draft_id') IS NULL
BEGIN
    EXEC('ALTER TABLE dbo.mother_application_draft ADD draft_id UNIQUEIDENTIFIER NULL;');
END;

IF COL_LENGTH('dbo.mother_application_draft', 'draft_id') IS NOT NULL
BEGIN
    EXEC('UPDATE dbo.mother_application_draft SET draft_id = NEWID() WHERE draft_id IS NULL;');
END;

IF EXISTS (
    SELECT 1
    FROM sys.columns
    WHERE object_id = OBJECT_ID('dbo.mother_application_draft')
      AND name = 'draft_id'
      AND is_nullable = 1
)
BEGIN
    EXEC('ALTER TABLE dbo.mother_application_draft ALTER COLUMN draft_id UNIQUEIDENTIFIER NOT NULL;');
END;

IF COL_LENGTH('dbo.mother_application_draft', 'draft_id') IS NOT NULL
   AND NOT EXISTS (
        SELECT 1
        FROM sys.default_constraints dc
        WHERE dc.parent_object_id = OBJECT_ID('dbo.mother_application_draft')
          AND COL_NAME(dc.parent_object_id, dc.parent_column_id) = 'draft_id'
    )
BEGIN
    EXEC('ALTER TABLE dbo.mother_application_draft ADD CONSTRAINT DF_mother_application_draft_draft_id DEFAULT NEWID() FOR draft_id;');
END;

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_mother_application_draft_draft_id')
BEGIN
    CREATE UNIQUE INDEX IX_mother_application_draft_draft_id
        ON dbo.mother_application_draft (draft_id);
END;

/* Link drafts to mother applications */
IF COL_LENGTH('dbo.mother_application_draft', 'application_id') IS NULL
BEGIN
    ALTER TABLE dbo.mother_application_draft
        ADD application_id INT NULL;
END;

DECLARE @applicationIdIsIndexable BIT = 0;

IF COL_LENGTH('dbo.mother_application_draft', 'application_id') IS NOT NULL
BEGIN
    IF EXISTS (
        SELECT 1
        FROM sys.columns c
        JOIN sys.types t ON c.user_type_id = t.user_type_id
        WHERE c.object_id = OBJECT_ID('dbo.mother_application_draft')
          AND c.name = 'application_id'
          AND (
                (t.name IN ('int', 'bigint', 'smallint', 'tinyint')) OR
                (t.name IN ('decimal', 'numeric', 'float', 'real')) OR
                (t.name IN ('money', 'smallmoney')) OR
                (t.name IN ('char', 'nchar', 'varchar', 'nvarchar') AND c.max_length <> -1)
              )
    )
    BEGIN
        SET @applicationIdIsIndexable = 1;
    END;
END;

IF @applicationIdIsIndexable = 1
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_mother_application_draft_application_id')
BEGIN
    CREATE INDEX IX_mother_application_draft_application_id
        ON dbo.mother_application_draft (application_id);
END
ELSE IF @applicationIdIsIndexable = 0
BEGIN
    PRINT 'Skipped index IX_mother_application_draft_application_id because application_id column type is not indexable.';
END;

/* Metadata for autosave */
IF COL_LENGTH('dbo.mother_application_draft', 'form_state') IS NULL
BEGIN
    ALTER TABLE dbo.mother_application_draft
        ADD form_state NVARCHAR(MAX) NULL;
END;

IF COL_LENGTH('dbo.mother_application_draft', 'progress_percent') IS NULL
BEGIN
    ALTER TABLE dbo.mother_application_draft
        ADD progress_percent DECIMAL(5,2) NULL;
END;

IF COL_LENGTH('dbo.mother_application_draft', 'last_completed_step') IS NULL
BEGIN
    ALTER TABLE dbo.mother_application_draft
        ADD last_completed_step INT NULL;
END;

IF COL_LENGTH('dbo.mother_application_draft', 'auto_save_frequency') IS NULL
BEGIN
    ALTER TABLE dbo.mother_application_draft
        ADD auto_save_frequency INT NULL;
END;

IF COL_LENGTH('dbo.mother_application_draft', 'is_locked') IS NULL
BEGIN
    EXEC('ALTER TABLE dbo.mother_application_draft ADD is_locked BIT NULL;');
END;

IF COL_LENGTH('dbo.mother_application_draft', 'is_locked') IS NOT NULL
BEGIN
    EXEC('UPDATE dbo.mother_application_draft SET is_locked = 0 WHERE is_locked IS NULL;');
END;

IF EXISTS (
    SELECT 1
    FROM sys.columns
    WHERE object_id = OBJECT_ID('dbo.mother_application_draft')
      AND name = 'is_locked'
      AND is_nullable = 1
)
BEGIN
    EXEC('ALTER TABLE dbo.mother_application_draft ALTER COLUMN is_locked BIT NOT NULL;');
END;

IF COL_LENGTH('dbo.mother_application_draft', 'is_locked') IS NOT NULL
   AND NOT EXISTS (
        SELECT 1
        FROM sys.default_constraints dc
        WHERE dc.parent_object_id = OBJECT_ID('dbo.mother_application_draft')
          AND COL_NAME(dc.parent_object_id, dc.parent_column_id) = 'is_locked'
    )
BEGIN
    EXEC('ALTER TABLE dbo.mother_application_draft ADD CONSTRAINT DF_mother_application_draft_is_locked DEFAULT 0 FOR is_locked;');
END;

IF COL_LENGTH('dbo.mother_application_draft', 'locked_by') IS NULL
BEGIN
    ALTER TABLE dbo.mother_application_draft
        ADD locked_by INT NULL;
END;

IF COL_LENGTH('dbo.mother_application_draft', 'locked_at') IS NULL
BEGIN
    ALTER TABLE dbo.mother_application_draft
        ADD locked_at DATETIME NULL;
END;

IF COL_LENGTH('dbo.mother_application_draft', 'version') IS NULL
BEGIN
    EXEC('ALTER TABLE dbo.mother_application_draft ADD version INT NULL;');
END;

IF COL_LENGTH('dbo.mother_application_draft', 'version') IS NOT NULL
BEGIN
    EXEC('UPDATE dbo.mother_application_draft SET version = 1 WHERE version IS NULL;');
END;

IF EXISTS (
    SELECT 1
    FROM sys.columns
    WHERE object_id = OBJECT_ID('dbo.mother_application_draft')
      AND name = 'version'
      AND is_nullable = 1
)
BEGIN
    EXEC('ALTER TABLE dbo.mother_application_draft ALTER COLUMN version INT NOT NULL;');
END;

IF COL_LENGTH('dbo.mother_application_draft', 'version') IS NOT NULL
   AND NOT EXISTS (
        SELECT 1
        FROM sys.default_constraints dc
        WHERE dc.parent_object_id = OBJECT_ID('dbo.mother_application_draft')
          AND COL_NAME(dc.parent_object_id, dc.parent_column_id) = 'version'
    )
BEGIN
    EXEC('ALTER TABLE dbo.mother_application_draft ADD CONSTRAINT DF_mother_application_draft_version DEFAULT 1 FOR version;');
END;

IF COL_LENGTH('dbo.mother_application_draft', 'last_saved_by') IS NULL
BEGIN
    ALTER TABLE dbo.mother_application_draft
        ADD last_saved_by INT NULL;
END;

IF COL_LENGTH('dbo.mother_application_draft', 'last_saved_at') IS NULL
BEGIN
    ALTER TABLE dbo.mother_application_draft
        ADD last_saved_at DATETIME NULL;
END;

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_mother_application_draft_last_saved_at')
BEGIN
    CREATE INDEX IX_mother_application_draft_last_saved_at
        ON dbo.mother_application_draft (last_saved_at);
END;

IF COL_LENGTH('dbo.mother_application_draft', 'analytics') IS NULL
BEGIN
    ALTER TABLE dbo.mother_application_draft
        ADD analytics NVARCHAR(MAX) NULL;
END;

IF COL_LENGTH('dbo.mother_application_draft', 'collaborators') IS NULL
BEGIN
    ALTER TABLE dbo.mother_application_draft
        ADD collaborators NVARCHAR(MAX) NULL;
END;

IF COL_LENGTH('dbo.mother_application_draft', 'last_error') IS NULL
BEGIN
    ALTER TABLE dbo.mother_application_draft
        ADD last_error NVARCHAR(1000) NULL;
END;
IF OBJECT_ID('tempdb..#CheckColumnExists') IS NOT NULL
BEGIN
    DROP TABLE #CheckColumnExists;
END;

CREATE TABLE #CheckColumnExists (
    ColumnExists BIT
);

DECLARE @columnName SYSNAME = 'np_file_no';

IF COL_LENGTH('dbo.mother_application_draft', @columnName) IS NULL
BEGIN
    ALTER TABLE dbo.mother_application_draft
        ADD np_file_no NVARCHAR(100) NULL;
END;

INSERT INTO #CheckColumnExists
SELECT CASE WHEN COL_LENGTH('dbo.mother_application_draft', @columnName) IS NOT NULL THEN 1 ELSE 0 END;

IF EXISTS (SELECT 1 FROM #CheckColumnExists WHERE ColumnExists = 1)
BEGIN
    UPDATE dbo.mother_application_draft
    SET np_file_no = JSON_VALUE(form_state, '$.np_fileno')
    WHERE ISNULL(np_file_no, '') = ''
      AND form_state IS NOT NULL
      AND ISJSON(form_state) = 1;

    IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_mother_application_draft_np_file_no')
    BEGIN
        CREATE INDEX IX_mother_application_draft_np_file_no
            ON dbo.mother_application_draft (np_file_no);
    END;
END;

DROP TABLE #CheckColumnExists;

/* Support tables for version history */
IF OBJECT_ID('dbo.mother_application_draft_versions', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.mother_application_draft_versions (
        id INT IDENTITY(1,1) PRIMARY KEY,
        draft_id UNIQUEIDENTIFIER NOT NULL,
        version INT NOT NULL,
        snapshot NVARCHAR(MAX) NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE()
    );

    CREATE INDEX IX_madv_draft_id ON dbo.mother_application_draft_versions (draft_id, version DESC);
END;

/* Collaborator mapping */
IF OBJECT_ID('dbo.mother_application_draft_collaborators', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.mother_application_draft_collaborators (
        id INT IDENTITY(1,1) PRIMARY KEY,
        draft_id UNIQUEIDENTIFIER NOT NULL,
        user_id INT NOT NULL,
        invited_by INT NULL,
        role NVARCHAR(50) NULL,
        accepted_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE()
    );

    CREATE INDEX IX_madc_draft_id ON dbo.mother_application_draft_collaborators (draft_id);
    CREATE INDEX IX_madc_user_id ON dbo.mother_application_draft_collaborators (user_id);
END;

PRINT 'Primary application draft tables upgraded successfully.';
