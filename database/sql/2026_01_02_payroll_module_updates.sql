-- =============================================
-- Script: Payroll Module Schema Updates
-- Description: Mirrors Laravel migrations for payroll and workstation payment structures.
-- Date: 2026-01-02
-- =============================================

USE [klas]; -- Replace with your production database name if different
GO

-- Add payroll metadata columns to users
IF COL_LENGTH('dbo.users', 'work_days_per_week') IS NULL
BEGIN
    ALTER TABLE dbo.users ADD work_days_per_week TINYINT NULL;
    PRINT 'Added work_days_per_week column to dbo.users';
END
ELSE
BEGIN
    PRINT 'Column work_days_per_week already exists on dbo.users';
END

IF COL_LENGTH('dbo.users', 'man_hours_per_day') IS NULL
BEGIN
    ALTER TABLE dbo.users ADD man_hours_per_day TINYINT NULL;
    PRINT 'Added man_hours_per_day column to dbo.users';
END
ELSE
BEGIN
    PRINT 'Column man_hours_per_day already exists on dbo.users';
END

IF COL_LENGTH('dbo.users', 'staff_type_category') IS NULL
BEGIN
    ALTER TABLE dbo.users ADD staff_type_category NVARCHAR(20) NULL;
    PRINT 'Added staff_type_category column to dbo.users';
END
ELSE
BEGIN
    PRINT 'Column staff_type_category already exists on dbo.users';
END
GO

-- Add workstation payment structure linkage columns to users
IF COL_LENGTH('dbo.users', 'workstation_payment_structure_id') IS NULL
BEGIN
    ALTER TABLE dbo.users ADD workstation_payment_structure_id BIGINT NULL;
    PRINT 'Added workstation_payment_structure_id column to dbo.users';
END
ELSE
BEGIN
    PRINT 'Column workstation_payment_structure_id already exists on dbo.users';
END

IF COL_LENGTH('dbo.users', 'base_salary_override') IS NULL
BEGIN
    ALTER TABLE dbo.users ADD base_salary_override DECIMAL(12, 2) NULL;
    PRINT 'Added base_salary_override column to dbo.users';
END
ELSE
BEGIN
    PRINT 'Column base_salary_override already exists on dbo.users';
END

IF COL_LENGTH('dbo.users', 'base_salary_source') IS NULL
BEGIN
    ALTER TABLE dbo.users ADD base_salary_source NVARCHAR(255) NULL;
    PRINT 'Added base_salary_source column to dbo.users';
END
ELSE
BEGIN
    PRINT 'Column base_salary_source already exists on dbo.users';
END
GO

IF COL_LENGTH('dbo.users', 'workstation_payment_structure_id') IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM sys.indexes
        WHERE name = 'users_workstation_structure_idx'
          AND object_id = OBJECT_ID('dbo.users')
    )
BEGIN
    CREATE NONCLUSTERED INDEX users_workstation_structure_idx
        ON dbo.users (workstation_payment_structure_id);
    PRINT 'Created users_workstation_structure_idx on dbo.users';
END
ELSE
BEGIN
    PRINT 'Index users_workstation_structure_idx already exists on dbo.users';
END
GO

-- Create payroll_staff_types table
IF OBJECT_ID('dbo.payroll_staff_types', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.payroll_staff_types (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        code NVARCHAR(20) NOT NULL,
        name NVARCHAR(255) NOT NULL,
        description NVARCHAR(255) NULL,
        is_payroll_eligible BIT NOT NULL CONSTRAINT DF_payroll_staff_types_is_payroll_eligible DEFAULT 0,
        created_at DATETIME2(0) NULL,
        updated_at DATETIME2(0) NULL,
        CONSTRAINT UQ_payroll_staff_types_code UNIQUE (code)
    );
    PRINT 'Created dbo.payroll_staff_types table';
END
ELSE
BEGIN
    PRINT 'Table dbo.payroll_staff_types already exists';
END
GO

IF OBJECT_ID('dbo.payroll_staff_types', 'U') IS NOT NULL
BEGIN
    IF NOT EXISTS (SELECT 1 FROM dbo.payroll_staff_types WHERE code = 'MDC')
    BEGIN
        INSERT INTO dbo.payroll_staff_types (code, name, description, is_payroll_eligible, created_at, updated_at)
        VALUES ('MDC', 'Mass Data Capture', 'Mass Data Capture workforce', 1, GETDATE(), GETDATE());
        PRINT 'Inserted payroll_staff_types row for MDC';
    END
    ELSE
    BEGIN
        PRINT 'Payroll staff type MDC already present';
    END

    IF NOT EXISTS (SELECT 1 FROM dbo.payroll_staff_types WHERE code = 'MLPP')
    BEGIN
        INSERT INTO dbo.payroll_staff_types (code, name, description, is_payroll_eligible, created_at, updated_at)
        VALUES ('MLPP', 'Ministry of Land and Physical Planning', 'Ministry staff (not payroll eligible)', 0, GETDATE(), GETDATE());
        PRINT 'Inserted payroll_staff_types row for MLPP';
    END
    ELSE
    BEGIN
        PRINT 'Payroll staff type MLPP already present';
    END
END
GO

-- Create payroll_periods table
IF OBJECT_ID('dbo.payroll_periods', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.payroll_periods (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        period_code NVARCHAR(7) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        status NVARCHAR(32) NOT NULL CONSTRAINT DF_payroll_periods_status DEFAULT 'draft',
        locked_at DATETIME2(0) NULL,
        created_by BIGINT NULL,
        locked_by BIGINT NULL,
        created_at DATETIME2(0) NULL,
        updated_at DATETIME2(0) NULL,
        CONSTRAINT UQ_payroll_periods_period_code UNIQUE (period_code),
        CONSTRAINT FK_payroll_periods_created_by FOREIGN KEY (created_by) REFERENCES dbo.users(id) ON DELETE SET NULL,
        CONSTRAINT FK_payroll_periods_locked_by FOREIGN KEY (locked_by) REFERENCES dbo.users(id)
    );
    PRINT 'Created dbo.payroll_periods table';
END
ELSE
BEGIN
    PRINT 'Table dbo.payroll_periods already exists';
END
GO

IF OBJECT_ID('dbo.payroll_periods', 'U') IS NOT NULL
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM sys.indexes WHERE name = 'IX_payroll_periods_start_end' AND object_id = OBJECT_ID('dbo.payroll_periods')
    )
    BEGIN
        CREATE NONCLUSTERED INDEX IX_payroll_periods_start_end
            ON dbo.payroll_periods (start_date, end_date);
        PRINT 'Created IX_payroll_periods_start_end index';
    END
    ELSE
    BEGIN
        PRINT 'Index IX_payroll_periods_start_end already exists';
    END
END
GO

-- Create payroll_attendance table
IF OBJECT_ID('dbo.payroll_attendance', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.payroll_attendance (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        period_id BIGINT NOT NULL,
        user_id BIGINT NOT NULL,
        department_id BIGINT NULL,
        login_days DECIMAL(6,2) NOT NULL CONSTRAINT DF_payroll_attendance_login_days DEFAULT 0,
        hours_worked DECIMAL(8,2) NOT NULL CONSTRAINT DF_payroll_attendance_hours_worked DEFAULT 0,
        overtime_hours DECIMAL(8,2) NOT NULL CONSTRAINT DF_payroll_attendance_overtime_hours DEFAULT 0,
        session_breakdown NVARCHAR(MAX) NULL,
        source_reference NVARCHAR(255) NULL,
        created_at DATETIME2(0) NULL,
        updated_at DATETIME2(0) NULL,
        CONSTRAINT UQ_payroll_attendance_period_user UNIQUE (period_id, user_id),
        CONSTRAINT FK_payroll_attendance_period FOREIGN KEY (period_id) REFERENCES dbo.payroll_periods(id) ON DELETE CASCADE,
        CONSTRAINT FK_payroll_attendance_user FOREIGN KEY (user_id) REFERENCES dbo.users(id) ON DELETE CASCADE,
        CONSTRAINT FK_payroll_attendance_department FOREIGN KEY (department_id) REFERENCES dbo.departments(id) ON DELETE SET NULL
    );
    PRINT 'Created dbo.payroll_attendance table';
END
ELSE
BEGIN
    PRINT 'Table dbo.payroll_attendance already exists';
END
GO

IF OBJECT_ID('dbo.payroll_attendance', 'U') IS NOT NULL
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM sys.indexes WHERE name = 'IX_payroll_attendance_period_department' AND object_id = OBJECT_ID('dbo.payroll_attendance')
    )
    BEGIN
        CREATE NONCLUSTERED INDEX IX_payroll_attendance_period_department
            ON dbo.payroll_attendance (period_id, department_id);
        PRINT 'Created IX_payroll_attendance_period_department index';
    END
    ELSE
    BEGIN
        PRINT 'Index IX_payroll_attendance_period_department already exists';
    END

    IF NOT EXISTS (
        SELECT 1 FROM sys.indexes WHERE name = 'IX_payroll_attendance_department' AND object_id = OBJECT_ID('dbo.payroll_attendance')
    )
    BEGIN
        CREATE NONCLUSTERED INDEX IX_payroll_attendance_department
            ON dbo.payroll_attendance (department_id);
        PRINT 'Created IX_payroll_attendance_department index';
    END
    ELSE
    BEGIN
        PRINT 'Index IX_payroll_attendance_department already exists';
    END
END
GO

-- Create payroll_salaries table
IF OBJECT_ID('dbo.payroll_salaries', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.payroll_salaries (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        period_id BIGINT NOT NULL,
        user_id BIGINT NOT NULL,
        base_salary DECIMAL(14,2) NOT NULL CONSTRAINT DF_payroll_salaries_base_salary DEFAULT 0,
        bonuses DECIMAL(14,2) NOT NULL CONSTRAINT DF_payroll_salaries_bonuses DEFAULT 0,
        extra_hours_value DECIMAL(14,2) NOT NULL CONSTRAINT DF_payroll_salaries_extra_hours DEFAULT 0,
        deductions DECIMAL(14,2) NOT NULL CONSTRAINT DF_payroll_salaries_deductions DEFAULT 0,
        net_salary DECIMAL(14,2) NOT NULL CONSTRAINT DF_payroll_salaries_net_salary DEFAULT 0,
        computed_at DATETIME2(0) NULL,
        calculation_status NVARCHAR(32) NOT NULL CONSTRAINT DF_payroll_salaries_status DEFAULT 'pending',
        note NVARCHAR(255) NULL,
        created_at DATETIME2(0) NULL,
        updated_at DATETIME2(0) NULL,
        CONSTRAINT UQ_payroll_salaries_period_user UNIQUE (period_id, user_id),
        CONSTRAINT FK_payroll_salaries_period FOREIGN KEY (period_id) REFERENCES dbo.payroll_periods(id) ON DELETE CASCADE,
        CONSTRAINT FK_payroll_salaries_user FOREIGN KEY (user_id) REFERENCES dbo.users(id) ON DELETE CASCADE
    );
    PRINT 'Created dbo.payroll_salaries table';
END
ELSE
BEGIN
    PRINT 'Table dbo.payroll_salaries already exists';
END
GO

IF OBJECT_ID('dbo.payroll_salaries', 'U') IS NOT NULL
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM sys.indexes WHERE name = 'IX_payroll_salaries_net_salary' AND object_id = OBJECT_ID('dbo.payroll_salaries')
    )
    BEGIN
        CREATE NONCLUSTERED INDEX IX_payroll_salaries_net_salary
            ON dbo.payroll_salaries (net_salary);
        PRINT 'Created IX_payroll_salaries_net_salary index';
    END
    ELSE
    BEGIN
        PRINT 'Index IX_payroll_salaries_net_salary already exists';
    END
END
GO

-- Create payroll_rates table
IF OBJECT_ID('dbo.payroll_rates', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.payroll_rates (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        user_id BIGINT NOT NULL,
        daily_rate DECIMAL(12,2) NOT NULL,
        shift_hours TINYINT NOT NULL,
        effective_date DATE NOT NULL,
        expires_at DATE NULL,
        created_by BIGINT NULL,
        updated_by BIGINT NULL,
        is_active BIT NOT NULL CONSTRAINT DF_payroll_rates_is_active DEFAULT 1,
        created_at DATETIME2(0) NULL,
        updated_at DATETIME2(0) NULL,
        CONSTRAINT FK_payroll_rates_user FOREIGN KEY (user_id) REFERENCES dbo.users(id) ON DELETE CASCADE,
        CONSTRAINT FK_payroll_rates_created_by FOREIGN KEY (created_by) REFERENCES dbo.users(id),
        CONSTRAINT FK_payroll_rates_updated_by FOREIGN KEY (updated_by) REFERENCES dbo.users(id)
    );
    PRINT 'Created dbo.payroll_rates table';
END
ELSE
BEGIN
    PRINT 'Table dbo.payroll_rates already exists';
END
GO

IF OBJECT_ID('dbo.payroll_rates', 'U') IS NOT NULL
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM sys.indexes WHERE name = 'IX_payroll_rates_user_effective_date' AND object_id = OBJECT_ID('dbo.payroll_rates')
    )
    BEGIN
        CREATE NONCLUSTERED INDEX IX_payroll_rates_user_effective_date
            ON dbo.payroll_rates (user_id, effective_date);
        PRINT 'Created IX_payroll_rates_user_effective_date index';
    END
    ELSE
    BEGIN
        PRINT 'Index IX_payroll_rates_user_effective_date already exists';
    END

    IF NOT EXISTS (
        SELECT 1 FROM sys.indexes WHERE name = 'IX_payroll_rates_is_active' AND object_id = OBJECT_ID('dbo.payroll_rates')
    )
    BEGIN
        CREATE NONCLUSTERED INDEX IX_payroll_rates_is_active
            ON dbo.payroll_rates (is_active);
        PRINT 'Created IX_payroll_rates_is_active index';
    END
    ELSE
    BEGIN
        PRINT 'Index IX_payroll_rates_is_active already exists';
    END
END
GO

-- Create payroll_audit_logs table
IF OBJECT_ID('dbo.payroll_audit_logs', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.payroll_audit_logs (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        period_id BIGINT NULL,
        user_id BIGINT NULL,
        actor_id BIGINT NULL,
        action NVARCHAR(64) NOT NULL,
        payload NVARCHAR(MAX) NULL,
        source NVARCHAR(64) NULL,
        created_at DATETIME2(0) NULL,
        updated_at DATETIME2(0) NULL,
        CONSTRAINT FK_payroll_audit_logs_period FOREIGN KEY (period_id) REFERENCES dbo.payroll_periods(id) ON DELETE SET NULL,
        CONSTRAINT FK_payroll_audit_logs_user FOREIGN KEY (user_id) REFERENCES dbo.users(id),
        CONSTRAINT FK_payroll_audit_logs_actor FOREIGN KEY (actor_id) REFERENCES dbo.users(id)
    );
    PRINT 'Created dbo.payroll_audit_logs table';
END
ELSE
BEGIN
    PRINT 'Table dbo.payroll_audit_logs already exists';
END
GO

IF OBJECT_ID('dbo.payroll_audit_logs', 'U') IS NOT NULL
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM sys.indexes WHERE name = 'IX_payroll_audit_logs_action' AND object_id = OBJECT_ID('dbo.payroll_audit_logs')
    )
    BEGIN
        CREATE NONCLUSTERED INDEX IX_payroll_audit_logs_action
            ON dbo.payroll_audit_logs (action);
        PRINT 'Created IX_payroll_audit_logs_action index';
    END
    ELSE
    BEGIN
        PRINT 'Index IX_payroll_audit_logs_action already exists';
    END

    IF NOT EXISTS (
        SELECT 1 FROM sys.indexes WHERE name = 'IX_payroll_audit_logs_created_at' AND object_id = OBJECT_ID('dbo.payroll_audit_logs')
    )
    BEGIN
        CREATE NONCLUSTERED INDEX IX_payroll_audit_logs_created_at
            ON dbo.payroll_audit_logs (created_at);
        PRINT 'Created IX_payroll_audit_logs_created_at index';
    END
    ELSE
    BEGIN
        PRINT 'Index IX_payroll_audit_logs_created_at already exists';
    END
END
GO

-- Create workstation_payment_structures table
IF OBJECT_ID('dbo.workstation_payment_structures', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.workstation_payment_structures (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        code NVARCHAR(255) NOT NULL,
        role_name NVARCHAR(255) NOT NULL,
        work_days TINYINT NULL,
        base_salary DECIMAL(12,2) NOT NULL,
        effective_from DATE NULL,
        effective_to DATE NULL,
        created_by BIGINT NULL,
        updated_by BIGINT NULL,
        created_at DATETIME2(0) NULL,
        updated_at DATETIME2(0) NULL,
        CONSTRAINT UQ_workstation_payment_structures_code_days_from UNIQUE (code, work_days, effective_from)
    );
    PRINT 'Created dbo.workstation_payment_structures table';
END
ELSE
BEGIN
    PRINT 'Table dbo.workstation_payment_structures already exists';
END
GO

-- Create workstation_payment_structure_aliases table
IF OBJECT_ID('dbo.workstation_payment_structure_aliases', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.workstation_payment_structure_aliases (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        structure_id BIGINT NOT NULL,
        alias NVARCHAR(255) NOT NULL,
        normalized_alias NVARCHAR(255) NOT NULL,
        created_at DATETIME2(0) NULL,
        updated_at DATETIME2(0) NULL,
        CONSTRAINT UQ_workstation_structure_aliases_normalized UNIQUE (normalized_alias),
        CONSTRAINT FK_workstation_structure_aliases_structure FOREIGN KEY (structure_id)
            REFERENCES dbo.workstation_payment_structures(id) ON DELETE CASCADE
    );
    PRINT 'Created dbo.workstation_payment_structure_aliases table';
END
ELSE
BEGIN
    PRINT 'Table dbo.workstation_payment_structure_aliases already exists';
END
GO

-- Seed workstation payment structures and aliases
IF OBJECT_ID('dbo.workstation_payment_structures', 'U') IS NOT NULL
BEGIN
    DECLARE @now DATETIME2(0) = GETDATE();

    DECLARE @structures TABLE (
        code NVARCHAR(255),
        role_name NVARCHAR(255),
        work_days TINYINT NULL,
        base_salary DECIMAL(12,2)
    );

    INSERT INTO @structures (code, role_name, work_days, base_salary)
    VALUES
        ('monitors', 'Monitors', NULL, 90000),
        ('scb', 'SCB', NULL, 70000),
        ('indexers5', 'Indexers', 5, 60000),
        ('indexers7', 'Indexers', 7, 70000),
        ('prapic5', 'PRA/PIC', 5, 50000),
        ('prapic7', 'PRA/PIC', 7, 60000),
        ('blindscanning5', 'Blind Scanning/File Archiving', 5, 25000),
        ('blindscanning7', 'Blind Scanning/File Archiving', 7, 50000),
        ('blindscanning2', 'Blind Scanning', 2, 12500),
        ('filetransfer7', 'File Transfer', 7, 50000),
        ('filetracking5', 'File Tracking/Front Desk', 5, 50000);

    DECLARE @structureMap TABLE (
        code NVARCHAR(255),
        work_days TINYINT NULL,
        id BIGINT
    );

    MERGE dbo.workstation_payment_structures AS target
    USING (SELECT code, role_name, work_days, base_salary FROM @structures) AS source
        ON target.code = source.code
       AND ISNULL(target.work_days, -1) = ISNULL(source.work_days, -1)
       AND target.effective_from IS NULL
    WHEN MATCHED THEN
        UPDATE SET
            target.role_name = source.role_name,
            target.base_salary = source.base_salary,
            target.updated_at = @now
    WHEN NOT MATCHED THEN
        INSERT (code, role_name, work_days, base_salary, effective_from, effective_to, created_at, updated_at)
        VALUES (source.code, source.role_name, source.work_days, source.base_salary, NULL, NULL, @now, @now)
    OUTPUT inserted.code, inserted.work_days, inserted.id
    INTO @structureMap (code, work_days, id);

    DECLARE @aliases TABLE (
        code NVARCHAR(255),
        alias NVARCHAR(255),
        normalized_alias NVARCHAR(255)
    );

    INSERT INTO @aliases (code, alias, normalized_alias)
    VALUES
        ('monitors', 'monitors', 'monitors'),
        ('monitors', 'monitorsteam', 'monitorsteam'),
        ('monitors', 'monitoring', 'monitoring'),
        ('monitors', 'monitors7daysfullday', 'monitors7daysfullday'),
        ('monitors', 'monitors7days', 'monitors7days'),
        ('monitors', 'monitorsfullday', 'monitorsfullday'),
        ('scb', 'verificationteam', 'verificationteam'),
        ('scb', 'verification', 'verification'),
        ('scb', 'scbteam', 'scbteam'),
        ('scb', 'scbstaff', 'scbstaff'),
        ('indexers5', '5workdaysfullday', '5workdaysfullday'),
        ('indexers5', 'indexers5days', 'indexers5days'),
        ('indexers5', 'indexer5days', 'indexer5days'),
        ('indexers5', '5daysindexing', '5daysindexing'),
        ('indexers5', '6workdaysfullday', '6workdaysfullday'),
        ('indexers5', 'sixworkdaysfullday', 'sixworkdaysfullday'),
        ('indexers5', '6daysfullday', '6daysfullday'),
        ('indexers5', '6workdays', '6workdays'),
        ('indexers5', '6days', '6days'),
        ('indexers7', '7workdaysfullday', '7workdaysfullday'),
        ('indexers7', 'sevenworkdaysfullday', 'sevenworkdaysfullday'),
        ('indexers7', '7daysfullday', '7daysfullday'),
        ('indexers7', '7workdays', '7workdays'),
        ('indexers7', '7workdaysindexers', '7workdaysindexers'),
        ('indexers7', 'indexers7days', 'indexers7days'),
        ('indexers7', 'sevenworkdaysindexers', 'sevenworkdaysindexers'),
        ('prapic5', 'pra', 'pra'),
        ('prapic5', 'propertyrecordsassistant', 'propertyrecordsassistant'),
        ('prapic5', 'propertyrecordsassistantpra', 'propertyrecordsassistantpra'),
        ('prapic5', 'pic', 'pic'),
        ('prapic5', 'publicinformationcenter', 'publicinformationcenter'),
        ('prapic5', 'publicinformationcenterpic', 'publicinformationcenterpic'),
        ('prapic5', 'dailyshiftstaff', 'dailyshiftstaff'),
        ('prapic5', 'dailyshift', 'dailyshift'),
        ('prapic5', 'shiftstaff', 'shiftstaff'),
        ('prapic7', 'pra7days', 'pra7days'),
        ('prapic7', 'pic7days', 'pic7days'),
        ('prapic7', 'praweek', 'praweek'),
        ('prapic7', 'picweek', 'picweek'),
        ('blindscanning5', '4workingdaysshift', '4workingdaysshift'),
        ('blindscanning5', 'fourworkingdaysshift', 'fourworkingdaysshift'),
        ('blindscanning5', 'weekdayonlyshift', 'weekdayonlyshift'),
        ('blindscanning5', '4workdays', '4workdays'),
        ('blindscanning5', '4daysshift', '4daysshift'),
        ('blindscanning7', '6workdaysblindscanningshift', '6workdaysblindscanningshift'),
        ('blindscanning7', 'blindscanning', 'blindscanning'),
        ('blindscanning7', 'scanningblindshift', 'scanningblindshift'),
        ('blindscanning7', 'blindscanningshift', 'blindscanningshift'),
        ('blindscanning7', '6workdaysblindscanning', '6workdaysblindscanning'),
        ('blindscanning2', 'weekendonstaff', 'weekendonstaff'),
        ('blindscanning2', 'weekendstaff', 'weekendstaff'),
        ('blindscanning2', 'weekendonlystaff', 'weekendonlystaff'),
        ('blindscanning2', 'weekendonly', 'weekendonly'),
        ('blindscanning2', 'weekend', 'weekend'),
        ('filetransfer7', 'filetransfer', 'filetransfer'),
        ('filetransfer7', 'filetransfer7days', 'filetransfer7days'),
        ('filetracking5', 'filetracking', 'filetracking'),
        ('filetracking5', 'frontdesk', 'frontdesk'),
        ('filetracking5', 'frontdesk5days', 'frontdesk5days');

    INSERT INTO dbo.workstation_payment_structure_aliases (structure_id, alias, normalized_alias, created_at, updated_at)
    SELECT map.id, a.alias, a.normalized_alias, @now, @now
    FROM @aliases AS a
    INNER JOIN @structureMap AS map ON map.code = a.code
    WHERE NOT EXISTS (
        SELECT 1 FROM dbo.workstation_payment_structure_aliases existing
        WHERE existing.normalized_alias = a.normalized_alias
    );

    PRINT 'Seeded workstation payment structures and aliases';
END
ELSE
BEGIN
    PRINT 'Skipping workstation payment structure seeding because tables are missing';
END
GO

PRINT 'Payroll module schema script completed successfully.';
GO