IF OBJECT_ID('dbo.survey_report_requests', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.survey_report_requests (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        serial_no NVARCHAR(255) NULL,
        director_cadastral_date DATE NULL,
        director_name NVARCHAR(255) NULL,
        director_rank NVARCHAR(255) NULL,
        director_signature NVARCHAR(255) NULL,
        rofo_no NVARCHAR(255) NULL,
        item_g_no NVARCHAR(255) NULL,
        plot_description NVARCHAR(MAX) NULL,
        survey_necessary NVARCHAR(255) NULL,
        beacon_numbers NVARCHAR(MAX) NULL,
        director_cadastral_approval_date DATE NULL,
        commissioner_date DATE NULL,
        status NVARCHAR(50) NULL,
        print_count INT NOT NULL DEFAULT 0,
        user_id BIGINT NULL,
        created_at DATETIME NULL,
        updated_at DATETIME NULL
    );
END;
