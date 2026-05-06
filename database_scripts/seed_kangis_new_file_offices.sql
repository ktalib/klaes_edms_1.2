-- =============================================================================
-- Seed: New KANGIS File (KN Series) Workflow Offices
-- Purpose: Ensure the 6-step KRE → CS → VET → GEO → PROD → DG → KRE
--          offices exist in the [offices] table on SQL Server.
-- Idempotent: Uses IF NOT EXISTS checks — safe to run multiple times.
-- =============================================================================

-- CS  — Commission / Customer Service
IF NOT EXISTS (SELECT 1 FROM [offices] WHERE [office_code] = 'CS')
BEGIN
    INSERT INTO [offices] ([office_code], [office_name], [office_abbreviation], [department], [is_active], [created_at], [updated_at])
    VALUES ('CS', 'Commission Section', 'CS', 'Customer Service', 1, GETDATE(), GETDATE());
END;

-- VET — Vetting Section
IF NOT EXISTS (SELECT 1 FROM [offices] WHERE [office_code] = 'VET')
BEGIN
    INSERT INTO [offices] ([office_code], [office_name], [office_abbreviation], [department], [is_active], [created_at], [updated_at])
    VALUES ('VET', 'Vetting Section', 'VET', 'Technical', 1, GETDATE(), GETDATE());
END;

-- GEO — GIS / Geo-referencing Section
IF NOT EXISTS (SELECT 1 FROM [offices] WHERE [office_code] = 'GEO')
BEGIN
    INSERT INTO [offices] ([office_code], [office_name], [office_abbreviation], [department], [is_active], [created_at], [updated_at])
    VALUES ('GEO', 'GIS / Geo-referencing Section', 'GEO', 'Technical', 1, GETDATE(), GETDATE());
END;

-- PROD — Production Section
IF NOT EXISTS (SELECT 1 FROM [offices] WHERE [office_code] = 'PROD')
BEGIN
    INSERT INTO [offices] ([office_code], [office_name], [office_abbreviation], [department], [is_active], [created_at], [updated_at])
    VALUES ('PROD', 'Production Section', 'PROD', 'Operations', 1, GETDATE(), GETDATE());
END;

-- KRE and DG already exist (see OfficesTableSeeder), but guard anyway
IF NOT EXISTS (SELECT 1 FROM [offices] WHERE [office_code] = 'KRE')
BEGIN
    INSERT INTO [offices] ([office_code], [office_name], [office_abbreviation], [department], [is_active], [created_at], [updated_at])
    VALUES ('KRE', 'KANGIS Registry', 'KRE', 'Records', 1, GETDATE(), GETDATE());
END;

IF NOT EXISTS (SELECT 1 FROM [offices] WHERE [office_code] = 'DG')
BEGIN
    INSERT INTO [offices] ([office_code], [office_name], [office_abbreviation], [department], [is_active], [created_at], [updated_at])
    VALUES ('DG', 'Director General, KANGIS', 'DG', 'DG', 1, GETDATE(), GETDATE());
END;

PRINT 'New KANGIS workflow offices seeded successfully.';
