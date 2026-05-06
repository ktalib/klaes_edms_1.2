/*
    Full SQL Server script for JSI lookup tables (production-safe, idempotent).
    - Creates tables if missing
    - Adds FK if missing
    - Upserts seed data
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRY
    BEGIN TRAN;

    /* ================================================================
       1) detailed_land_use
       ================================================================ */
    IF OBJECT_ID('dbo.detailed_land_use', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.detailed_land_use (
            id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
            name NVARCHAR(100) NOT NULL,
            is_active BIT NOT NULL CONSTRAINT DF_detailed_land_use_is_active DEFAULT (1),
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        );

        CREATE UNIQUE INDEX UX_detailed_land_use_name ON dbo.detailed_land_use(name);
    END;

    /* ================================================================
       2) jsi_lut_purposes
       ================================================================ */
    IF OBJECT_ID('dbo.jsi_lut_purposes', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.jsi_lut_purposes (
            id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
            land_use_id BIGINT NOT NULL,
            name NVARCHAR(150) NOT NULL,
            is_active BIT NOT NULL CONSTRAINT DF_jsi_lut_purposes_is_active DEFAULT (1),
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        );

        CREATE INDEX IX_jsi_lut_purposes_land_use_id ON dbo.jsi_lut_purposes(land_use_id);
    END;

    IF NOT EXISTS (
        SELECT 1
        FROM sys.foreign_keys
        WHERE name = 'jsi_lut_purposes_land_use_fk'
          AND parent_object_id = OBJECT_ID('dbo.jsi_lut_purposes')
    )
    BEGIN
        ALTER TABLE dbo.jsi_lut_purposes
        ADD CONSTRAINT jsi_lut_purposes_land_use_fk
            FOREIGN KEY (land_use_id)
            REFERENCES dbo.detailed_land_use(id)
            ON DELETE CASCADE;
    END;

    /* ================================================================
       3) jsi_lut_road_categories
       ================================================================ */
    IF OBJECT_ID('dbo.jsi_lut_road_categories', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.jsi_lut_road_categories (
            id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
            name NVARCHAR(500) NOT NULL,
            is_active BIT NOT NULL CONSTRAINT DF_jsi_lut_road_categories_is_active DEFAULT (1),
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        );
    END;

    /* ================================================================
       4) jsi_lut_reservations
       ================================================================ */
    IF OBJECT_ID('dbo.jsi_lut_reservations', 'U') IS NULL
    BEGIN
        CREATE TABLE dbo.jsi_lut_reservations (
            id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
            name NVARCHAR(700) NOT NULL,
            is_active BIT NOT NULL CONSTRAINT DF_jsi_lut_reservations_is_active DEFAULT (1),
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        );
    END;

    DECLARE @now DATETIME = GETDATE();

    /* ================================================================
       Seed: detailed_land_use
       ================================================================ */
    ;WITH src AS (
        SELECT CAST('Residential' AS NVARCHAR(100)) AS name UNION ALL
        SELECT 'Commercial' UNION ALL
        SELECT 'Agricultural' UNION ALL
        SELECT 'Industrial'
    )
    MERGE dbo.detailed_land_use AS tgt
    USING src
      ON tgt.name = src.name
    WHEN MATCHED THEN
      UPDATE SET tgt.is_active = 1, tgt.updated_at = @now
    WHEN NOT MATCHED THEN
      INSERT (name, is_active, created_at, updated_at)
      VALUES (src.name, 1, @now, @now);

    /* ================================================================
       Seed: jsi_lut_purposes
       ================================================================ */
    IF OBJECT_ID('tempdb..#purpose_seed') IS NOT NULL DROP TABLE #purpose_seed;
    CREATE TABLE #purpose_seed (
        land_use_name NVARCHAR(100) NOT NULL,
        purpose_name NVARCHAR(150) NOT NULL
    );

    INSERT INTO #purpose_seed (land_use_name, purpose_name) VALUES
    ('Commercial','COM-PFS'),
    ('Commercial','COM-CNG'),
    ('Commercial','COM-LPG'),
    ('Commercial','COM-Warehouse'),
    ('Commercial','COM-Shops & offices'),
    ('Commercial','COM-Garage'),
    ('Commercial','COM-Hotel'),
    ('Commercial','COM-Car park'),
    ('Commercial','COM-Institutional'),
    ('Commercial','COM-plaza'),
    ('Commercial','COM- SPORT CENTER'),
    ('Commercial','COM- VIEWING CENTER'),
    ('Commercial','COM-SHOPPING MALL'),
    ('Commercial','OTHERS'),

    ('Agricultural','AG-Crop production'),
    ('Agricultural','AG-Mix farming'),
    ('Agricultural','AG-Crop cultivation'),
    ('Agricultural','AG-Orchard'),
    ('Agricultural','AG-Poultry'),
    ('Agricultural','AG-Fishery'),
    ('Agricultural','OTHERS'),

    ('Industrial','IND-Quarry'),
    ('Industrial','IND-Small scale'),
    ('Industrial','IND-Processing'),
    ('Industrial','IND-Packaging'),
    ('Industrial','IND-OIL MILL'),
    ('Industrial','IND- RICE MILL'),
    ('Industrial','OTHERS'),

    ('Residential','RES-Estate'),
    ('Residential','RES-Layout');

    ;WITH src AS (
        SELECT d.id AS land_use_id, p.purpose_name AS name
        FROM #purpose_seed p
        INNER JOIN dbo.detailed_land_use d ON d.name = p.land_use_name
    )
    MERGE dbo.jsi_lut_purposes AS tgt
    USING src
      ON tgt.land_use_id = src.land_use_id AND tgt.name = src.name
    WHEN MATCHED THEN
      UPDATE SET tgt.is_active = 1, tgt.updated_at = @now
    WHEN NOT MATCHED THEN
      INSERT (land_use_id, name, is_active, created_at, updated_at)
      VALUES (src.land_use_id, src.name, 1, @now, @now);

    /* ================================================================
       Seed: jsi_lut_road_categories
       ================================================================ */
    ;WITH src AS (
        SELECT CAST('FEDERAL HIGHWAYS (Truck A) also know as Express Road' AS NVARCHAR(500)) AS name UNION ALL
        SELECT 'STATE ROADS (Truck B) also know as Arterial roads.' UNION ALL
        SELECT 'LOCAL ROADS (Truck c) also know as collector Roads.' UNION ALL
        SELECT 'RURAL ROADS (Feeder/Access road)' UNION ALL
        SELECT 'FEDERAL HIGHWAYS (100m Right of way/ 50m Road Reservation, with the exception of Urban areas ware 45m to 30m are served as the Road reservations.)' UNION ALL
        SELECT 'STATE ROADS (60m R.O.W and 30m Road Reservation)' UNION ALL
        SELECT 'LOCAL ROADS ( 30m R.O.W and 15m R.R)' UNION ALL
        SELECT 'RURAL ROADS (15m R.O.W / 7.5m R.R)'
    )
    MERGE dbo.jsi_lut_road_categories AS tgt
    USING src
      ON tgt.name = src.name
    WHEN MATCHED THEN
      UPDATE SET tgt.is_active = 1, tgt.updated_at = @now
    WHEN NOT MATCHED THEN
      INSERT (name, is_active, created_at, updated_at)
      VALUES (src.name, 1, @now, @now);

    /* ================================================================
       Seed: jsi_lut_reservations
       ================================================================ */
    ;WITH src AS (
        SELECT CAST('FEDERAL HIGHWAYS (100m Right of way/ 50m Road Reservation, with the exception of Urban areas ware 45m to 30m are served as the Road reservations.)' AS NVARCHAR(700)) AS name UNION ALL
        SELECT 'STATE ROADS (60m R.O.W And 30m Road Reservation)' UNION ALL
        SELECT 'LOCAL ROADS ( 30m R.O.W and 15m R.R)' UNION ALL
        SELECT 'RURAL ROADS (15m R.O.W / 7.5m R.R)' UNION ALL
        SELECT 'HIGH TENSION LINE 330kv ( 60m R.O.W & 30m R.R) and LOWER VOLTAGE 132 KV/11KV ( 30m R.O.W/ 12m R.O.W)' UNION ALL
        SELECT 'RIVER, STREAM, POND (Minimum of 15m for river, 10-15m for pond, stream 15m)' UNION ALL
        SELECT 'RAILWAY LINE ( 60m R.O.W & 30m R.R)' UNION ALL
        SELECT 'PIPELINE ( 60m R.O.W & 30m R.R)' UNION ALL
        SELECT 'CATTLE TRACK ( 30m R.O.W & 15m R.R)' UNION ALL
        SELECT 'OTHER'
    )
    MERGE dbo.jsi_lut_reservations AS tgt
    USING src
      ON tgt.name = src.name
    WHEN MATCHED THEN
      UPDATE SET tgt.is_active = 1, tgt.updated_at = @now
    WHEN NOT MATCHED THEN
      INSERT (name, is_active, created_at, updated_at)
      VALUES (src.name, 1, @now, @now);

    COMMIT TRAN;

    SELECT 'SUCCESS' AS status, 'Lookup tables created/updated successfully' AS message;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRAN;

    SELECT
        'ERROR' AS status,
        ERROR_NUMBER() AS error_number,
        ERROR_MESSAGE() AS error_message,
        ERROR_LINE() AS error_line;
END CATCH;

/* Quick verification */
SELECT 'detailed_land_use' AS table_name, COUNT(*) AS row_count FROM dbo.detailed_land_use
UNION ALL
SELECT 'jsi_lut_purposes', COUNT(*) FROM dbo.jsi_lut_purposes
UNION ALL
SELECT 'jsi_lut_road_categories', COUNT(*) FROM dbo.jsi_lut_road_categories
UNION ALL
SELECT 'jsi_lut_reservations', COUNT(*) FROM dbo.jsi_lut_reservations;
