SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

/*
    Payroll schema install script for production deployment.
    Includes table creation, supporting indexes, and baseline seed data.
*/

------------------------------------------------------------
-- Extend users table with payroll profile columns
------------------------------------------------------------
IF COL_LENGTH('dbo.users', 'work_days_per_week') IS NULL
BEGIN
    ALTER TABLE dbo.users
        ADD work_days_per_week TINYINT NULL;
END;
GO

IF COL_LENGTH('dbo.users', 'man_hours_per_day') IS NULL
BEGIN
    ALTER TABLE dbo.users
        ADD man_hours_per_day TINYINT NULL;
END;
GO

IF COL_LENGTH('dbo.users', 'staff_type_category') IS NULL
BEGIN
    ALTER TABLE dbo.users
        ADD staff_type_category NVARCHAR(20) NULL;
END;
GO

------------------------------------------------------------
-- payroll_staff_types
------------------------------------------------------------
IF OBJECT_ID(N'dbo.payroll_staff_types', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.payroll_staff_types
    (
        id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_payroll_staff_types PRIMARY KEY,
        code NVARCHAR(20) NOT NULL UNIQUE,
        name NVARCHAR(255) NOT NULL,
        description NVARCHAR(255) NULL,
        is_payroll_eligible BIT NOT NULL CONSTRAINT DF_payroll_staff_types_is_payroll_eligible DEFAULT (0),
        created_at DATETIME2(0) NOT NULL CONSTRAINT DF_payroll_staff_types_created_at DEFAULT (SYSUTCDATETIME()),
        updated_at DATETIME2(0) NOT NULL CONSTRAINT DF_payroll_staff_types_updated_at DEFAULT (SYSUTCDATETIME())
    );
END;
GO

MERGE dbo.payroll_staff_types AS target
USING (VALUES
    (N'MDC', N'Mass Data Capture', N'Mass Data Capture workforce', 1),
    (N'MLPP', N'Ministry of Land and Physical Planning', N'Ministry staff (not payroll eligible)', 0)
) AS source(code, name, description, is_payroll_eligible)
    ON target.code = source.code
WHEN MATCHED THEN
    UPDATE SET
        target.name = source.name,
        target.description = source.description,
        target.is_payroll_eligible = source.is_payroll_eligible,
        target.updated_at = SYSUTCDATETIME()
WHEN NOT MATCHED BY TARGET THEN
    INSERT (code, name, description, is_payroll_eligible, created_at, updated_at)
    VALUES (source.code, source.name, source.description, source.is_payroll_eligible, SYSUTCDATETIME(), SYSUTCDATETIME());
GO

------------------------------------------------------------
-- payroll_periods
------------------------------------------------------------
IF OBJECT_ID(N'dbo.payroll_periods', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.payroll_periods
    (
        id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_payroll_periods PRIMARY KEY,
        period_code NVARCHAR(7) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        status NVARCHAR(32) NOT NULL CONSTRAINT DF_payroll_periods_status DEFAULT (N'draft'),
        locked_at DATETIME2(0) NULL,
        created_by BIGINT NULL,
        locked_by BIGINT NULL,
        created_at DATETIME2(0) NOT NULL CONSTRAINT DF_payroll_periods_created_at DEFAULT (SYSUTCDATETIME()),
        updated_at DATETIME2(0) NOT NULL CONSTRAINT DF_payroll_periods_updated_at DEFAULT (SYSUTCDATETIME())
    );

    CREATE UNIQUE INDEX IX_payroll_periods_period_code ON dbo.payroll_periods (period_code);
    CREATE INDEX IX_payroll_periods_dates ON dbo.payroll_periods (start_date, end_date);

    ALTER TABLE dbo.payroll_periods WITH CHECK
        ADD CONSTRAINT FK_payroll_periods_created_by
            FOREIGN KEY (created_by) REFERENCES dbo.users (id)
            ON DELETE SET NULL;

    ALTER TABLE dbo.payroll_periods WITH CHECK
        ADD CONSTRAINT FK_payroll_periods_locked_by
            FOREIGN KEY (locked_by) REFERENCES dbo.users (id)
            ON DELETE NO ACTION;
END;
GO

------------------------------------------------------------
-- payroll_attendance
------------------------------------------------------------
IF OBJECT_ID(N'dbo.payroll_attendance', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.payroll_attendance
    (
        id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_payroll_attendance PRIMARY KEY,
        period_id BIGINT NOT NULL,
        user_id BIGINT NOT NULL,
        department_id BIGINT NULL,
        login_days DECIMAL(6,2) NOT NULL CONSTRAINT DF_payroll_attendance_login_days DEFAULT (0),
        hours_worked DECIMAL(8,2) NOT NULL CONSTRAINT DF_payroll_attendance_hours_worked DEFAULT (0),
        overtime_hours DECIMAL(8,2) NOT NULL CONSTRAINT DF_payroll_attendance_overtime_hours DEFAULT (0),
        session_breakdown NVARCHAR(MAX) NULL,
        source_reference NVARCHAR(255) NULL,
        created_at DATETIME2(0) NOT NULL CONSTRAINT DF_payroll_attendance_created_at DEFAULT (SYSUTCDATETIME()),
        updated_at DATETIME2(0) NOT NULL CONSTRAINT DF_payroll_attendance_updated_at DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT UQ_payroll_attendance_period_user UNIQUE (period_id, user_id)
    );

    CREATE INDEX IX_payroll_attendance_period_department ON dbo.payroll_attendance (period_id, department_id);
    CREATE INDEX IX_payroll_attendance_department ON dbo.payroll_attendance (department_id);

    ALTER TABLE dbo.payroll_attendance WITH CHECK
        ADD CONSTRAINT FK_payroll_attendance_period
            FOREIGN KEY (period_id) REFERENCES dbo.payroll_periods (id)
            ON DELETE CASCADE;

    ALTER TABLE dbo.payroll_attendance WITH CHECK
        ADD CONSTRAINT FK_payroll_attendance_user
            FOREIGN KEY (user_id) REFERENCES dbo.users (id)
            ON DELETE CASCADE;

    ALTER TABLE dbo.payroll_attendance WITH CHECK
        ADD CONSTRAINT FK_payroll_attendance_department
            FOREIGN KEY (department_id) REFERENCES dbo.departments (id)
            ON DELETE SET NULL;
END;
GO

------------------------------------------------------------
-- payroll_salaries
------------------------------------------------------------
IF OBJECT_ID(N'dbo.payroll_salaries', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.payroll_salaries
    (
        id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_payroll_salaries PRIMARY KEY,
        period_id BIGINT NOT NULL,
        user_id BIGINT NOT NULL,
        base_salary DECIMAL(14,2) NOT NULL CONSTRAINT DF_payroll_salaries_base_salary DEFAULT (0),
        bonuses DECIMAL(14,2) NOT NULL CONSTRAINT DF_payroll_salaries_bonuses DEFAULT (0),
        extra_hours_value DECIMAL(14,2) NOT NULL CONSTRAINT DF_payroll_salaries_extra_hours DEFAULT (0),
        deductions DECIMAL(14,2) NOT NULL CONSTRAINT DF_payroll_salaries_deductions DEFAULT (0),
        net_salary DECIMAL(14,2) NOT NULL CONSTRAINT DF_payroll_salaries_net_salary DEFAULT (0),
        computed_at DATETIME2(0) NULL,
        calculation_status NVARCHAR(32) NOT NULL CONSTRAINT DF_payroll_salaries_calculation_status DEFAULT (N'pending'),
        note NVARCHAR(255) NULL,
        created_at DATETIME2(0) NOT NULL CONSTRAINT DF_payroll_salaries_created_at DEFAULT (SYSUTCDATETIME()),
        updated_at DATETIME2(0) NOT NULL CONSTRAINT DF_payroll_salaries_updated_at DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT UQ_payroll_salaries_period_user UNIQUE (period_id, user_id)
    );

    CREATE INDEX IX_payroll_salaries_net_salary ON dbo.payroll_salaries (net_salary);

    ALTER TABLE dbo.payroll_salaries WITH CHECK
        ADD CONSTRAINT FK_payroll_salaries_period
            FOREIGN KEY (period_id) REFERENCES dbo.payroll_periods (id)
            ON DELETE CASCADE;

    ALTER TABLE dbo.payroll_salaries WITH CHECK
        ADD CONSTRAINT FK_payroll_salaries_user
            FOREIGN KEY (user_id) REFERENCES dbo.users (id)
            ON DELETE CASCADE;
END;
GO

------------------------------------------------------------
-- payroll_rates
------------------------------------------------------------
IF OBJECT_ID(N'dbo.payroll_rates', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.payroll_rates
    (
        id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_payroll_rates PRIMARY KEY,
        user_id BIGINT NOT NULL,
        daily_rate DECIMAL(12,2) NOT NULL,
        shift_hours TINYINT NOT NULL,
        effective_date DATE NOT NULL,
        expires_at DATE NULL,
        created_by BIGINT NULL,
        updated_by BIGINT NULL,
        is_active BIT NOT NULL CONSTRAINT DF_payroll_rates_is_active DEFAULT (1),
        created_at DATETIME2(0) NOT NULL CONSTRAINT DF_payroll_rates_created_at DEFAULT (SYSUTCDATETIME()),
        updated_at DATETIME2(0) NOT NULL CONSTRAINT DF_payroll_rates_updated_at DEFAULT (SYSUTCDATETIME())
    );

    CREATE INDEX IX_payroll_rates_user_effective ON dbo.payroll_rates (user_id, effective_date);
    CREATE INDEX IX_payroll_rates_is_active ON dbo.payroll_rates (is_active);

    ALTER TABLE dbo.payroll_rates WITH CHECK
        ADD CONSTRAINT FK_payroll_rates_user
            FOREIGN KEY (user_id) REFERENCES dbo.users (id)
            ON DELETE CASCADE;

    ALTER TABLE dbo.payroll_rates WITH CHECK
        ADD CONSTRAINT FK_payroll_rates_created_by
            FOREIGN KEY (created_by) REFERENCES dbo.users (id)
            ON DELETE NO ACTION;

    ALTER TABLE dbo.payroll_rates WITH CHECK
        ADD CONSTRAINT FK_payroll_rates_updated_by
            FOREIGN KEY (updated_by) REFERENCES dbo.users (id)
            ON DELETE NO ACTION;
END;
GO

------------------------------------------------------------
-- payroll_audit_logs
------------------------------------------------------------
IF OBJECT_ID(N'dbo.payroll_audit_logs', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.payroll_audit_logs
    (
        id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_payroll_audit_logs PRIMARY KEY,
        period_id BIGINT NULL,
        user_id BIGINT NULL,
        actor_id BIGINT NULL,
        action NVARCHAR(64) NOT NULL,
        payload NVARCHAR(MAX) NULL,
        source NVARCHAR(64) NULL,
        created_at DATETIME2(0) NOT NULL CONSTRAINT DF_payroll_audit_logs_created_at DEFAULT (SYSUTCDATETIME()),
        updated_at DATETIME2(0) NOT NULL CONSTRAINT DF_payroll_audit_logs_updated_at DEFAULT (SYSUTCDATETIME())
    );

    CREATE INDEX IX_payroll_audit_logs_action ON dbo.payroll_audit_logs (action);
    CREATE INDEX IX_payroll_audit_logs_created_at ON dbo.payroll_audit_logs (created_at);

    ALTER TABLE dbo.payroll_audit_logs WITH CHECK
        ADD CONSTRAINT FK_payroll_audit_logs_period
            FOREIGN KEY (period_id) REFERENCES dbo.payroll_periods (id)
            ON DELETE SET NULL;

    ALTER TABLE dbo.payroll_audit_logs WITH CHECK
        ADD CONSTRAINT FK_payroll_audit_logs_user
            FOREIGN KEY (user_id) REFERENCES dbo.users (id)
            ON DELETE NO ACTION;

    ALTER TABLE dbo.payroll_audit_logs WITH CHECK
        ADD CONSTRAINT FK_payroll_audit_logs_actor
            FOREIGN KEY (actor_id) REFERENCES dbo.users (id)
            ON DELETE NO ACTION;
END;
GO
