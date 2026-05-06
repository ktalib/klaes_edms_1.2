IF OBJECT_ID('dbo.for_information', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.for_information (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        serial_no NVARCHAR(255) NULL,
        ref_no NVARCHAR(255) NULL,
        title NVARCHAR(255) NULL,
        commencement_day NVARCHAR(255) NULL,
        signer_name NVARCHAR(255) NULL,
        signer_rank NVARCHAR(255) NULL,
        status NVARCHAR(50) NULL,
        print_count INT NOT NULL DEFAULT 0,
        user_id BIGINT NULL,
        file_number NVARCHAR(255) NULL,
        created_at DATETIME NULL,
        updated_at DATETIME NULL
    );
END;

-- Add file_number column if table already exists without it
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'for_information' AND COLUMN_NAME = 'file_number')
BEGIN
    ALTER TABLE dbo.for_information ADD file_number NVARCHAR(255) NULL;
END;
