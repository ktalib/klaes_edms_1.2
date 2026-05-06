/*
PropID Master table and helper artifacts
- Purpose: single source of truth for prop_id to file-number mappings.
- Tables touched: PropID_Master (new), reference-only to file_history_staging, pra, pic, instrument_capture, deed_registrations, CofO_staging.
- Run against: SQL Server (sqlsrv) database used by KLAES.
*/

/* 1) Create PropID_Master if missing */
IF OBJECT_ID('dbo.PropID_Master', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.PropID_Master
    (
        id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_PropID_Master PRIMARY KEY,
        prop_id INT NOT NULL,
        primary_file_number NVARCHAR(255) NOT NULL,
        mlsFNo NVARCHAR(255) NULL,
        kangisFileNo NVARCHAR(255) NULL,
        NewKANGISFileno NVARCHAR(255) NULL,
        temp_fileno NVARCHAR(255) NULL,
        source_table NVARCHAR(128) NULL,
        source_record_id INT NULL,
        status NVARCHAR(32) NOT NULL CONSTRAINT DF_PropID_Master_status DEFAULT ('active'),
        notes NVARCHAR(512) NULL,
        created_at DATETIME2(0) NOT NULL CONSTRAINT DF_PropID_Master_created_at DEFAULT (SYSUTCDATETIME()),
        updated_at DATETIME2(0) NULL
    );
END;
GO

/* 2) Add normalization columns (idempotent) */
IF COL_LENGTH('dbo.PropID_Master', 'primary_file_number_norm') IS NULL
    ALTER TABLE dbo.PropID_Master ADD primary_file_number_norm AS UPPER(LTRIM(RTRIM(primary_file_number))) PERSISTED;
IF COL_LENGTH('dbo.PropID_Master', 'mlsFNo_norm') IS NULL
    ALTER TABLE dbo.PropID_Master ADD mlsFNo_norm AS UPPER(LTRIM(RTRIM(mlsFNo))) PERSISTED;
IF COL_LENGTH('dbo.PropID_Master', 'kangisFileNo_norm') IS NULL
    ALTER TABLE dbo.PropID_Master ADD kangisFileNo_norm AS UPPER(LTRIM(RTRIM(kangisFileNo))) PERSISTED;
IF COL_LENGTH('dbo.PropID_Master', 'NewKANGISFileno_norm') IS NULL
    ALTER TABLE dbo.PropID_Master ADD NewKANGISFileno_norm AS UPPER(LTRIM(RTRIM(NewKANGISFileno))) PERSISTED;
IF COL_LENGTH('dbo.PropID_Master', 'temp_fileno_norm') IS NULL
    ALTER TABLE dbo.PropID_Master ADD temp_fileno_norm AS UPPER(LTRIM(RTRIM(temp_fileno))) PERSISTED;
GO

/* 3) Uniqueness safeguards (idempotent) */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UQ_PropID_Master_prop_id' AND object_id = OBJECT_ID('dbo.PropID_Master'))
    ALTER TABLE dbo.PropID_Master ADD CONSTRAINT UQ_PropID_Master_prop_id UNIQUE (prop_id);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PropID_Master_primary_file_number' AND object_id = OBJECT_ID('dbo.PropID_Master'))
    CREATE UNIQUE INDEX UX_PropID_Master_primary_file_number ON dbo.PropID_Master(primary_file_number_norm);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PropID_Master_mlsFNo' AND object_id = OBJECT_ID('dbo.PropID_Master'))
    CREATE UNIQUE INDEX UX_PropID_Master_mlsFNo ON dbo.PropID_Master(mlsFNo_norm) WHERE mlsFNo IS NOT NULL;
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PropID_Master_kangisFileNo' AND object_id = OBJECT_ID('dbo.PropID_Master'))
    CREATE UNIQUE INDEX UX_PropID_Master_kangisFileNo ON dbo.PropID_Master(kangisFileNo_norm) WHERE kangisFileNo IS NOT NULL;
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PropID_Master_NewKANGISFileno' AND object_id = OBJECT_ID('dbo.PropID_Master'))
    CREATE UNIQUE INDEX UX_PropID_Master_NewKANGISFileno ON dbo.PropID_Master(NewKANGISFileno_norm) WHERE NewKANGISFileno IS NOT NULL;
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_PropID_Master_temp_fileno' AND object_id = OBJECT_ID('dbo.PropID_Master'))
    CREATE UNIQUE INDEX UX_PropID_Master_temp_fileno ON dbo.PropID_Master(temp_fileno_norm) WHERE temp_fileno IS NOT NULL;
GO

/* 4) Conflict view: highlights file numbers mapped to multiple prop_ids across sources */
CREATE OR ALTER VIEW dbo.vw_prop_id_conflicts
AS
WITH unioned AS (
    SELECT
        UPPER(LTRIM(RTRIM(COALESCE(fh.mlsFNo, fh.fileno, fh.kangisFileNo, fh.NewKANGISFileno, fh.temp_fileno)))) AS normalized_file_number,
        fh.prop_id,
        'file_history_staging' AS source_table,
        fh.id AS source_id
    FROM file_history_staging fh
    WHERE fh.prop_id IS NOT NULL

    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(COALESCE(pra.mlsFNo, pra.kangisFileNo, pra.NewKANGISFileno, pra.temp_fileno)))), pra.prop_id, 'pra', pra.id
    FROM pra
    WHERE pra.prop_id IS NOT NULL

    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(COALESCE(pic.mlsFNo, pic.kangisFileNo, pic.NewKANGISFileno, pic.temp_fileno)))), pic.prop_id, 'pic', pic.id
    FROM pic
    WHERE pic.prop_id IS NOT NULL

        UNION ALL
        SELECT UPPER(LTRIM(RTRIM(COALESCE(ic.mlsFNo, ic.kangisFileNo, ic.NewKANGISFileno, ic.temp_fileno)))), ic.prop_id, 'instrument_capture', ic.id
        FROM instrument_capture ic
        WHERE ic.prop_id IS NOT NULL
            AND (ic.is_deleted = 0 OR ic.is_deleted IS NULL)

        UNION ALL
        SELECT UPPER(LTRIM(RTRIM(COALESCE(dr.fileno, dr.parent_fileno)))), dr.prop_id, 'deed_registrations', dr.id
        FROM deed_registrations dr
        WHERE dr.prop_id IS NOT NULL

    UNION ALL
    SELECT UPPER(LTRIM(RTRIM(COALESCE(co.mlsFNo, co.kangisFileNo, co.NewKANGISFileno, co.np_fileno)))), co.prop_id, 'CofO_staging', co.id
    FROM CofO_staging co
    WHERE co.prop_id IS NOT NULL
),
dedup AS (
    SELECT DISTINCT normalized_file_number, prop_id, source_table
    FROM unioned
)
SELECT
    normalized_file_number,
    COUNT(DISTINCT prop_id) AS prop_id_count,
    STRING_AGG(CAST(prop_id AS NVARCHAR(32)), ',') WITHIN GROUP (ORDER BY prop_id) AS prop_ids,
    STRING_AGG(source_table, ',') WITHIN GROUP (ORDER BY source_table) AS sources
FROM dedup
WHERE normalized_file_number IS NOT NULL
GROUP BY normalized_file_number
HAVING COUNT(DISTINCT prop_id) > 1;
GO

  --5) Optional: seed PropID_Master from existing tables (manual execution recommended)
  
 ---  Uncomment the MERGE block below when ready to populate master data.

;WITH candidates AS (
    SELECT DISTINCT
        UPPER(LTRIM(RTRIM(COALESCE(fh.mlsFNo, fh.fileno)))) AS primary_file_number_norm,
        fh.prop_id,
        fh.mlsFNo,
        fh.kangisFileNo,
        fh.NewKANGISFileno,
        fh.temp_fileno,
        'file_history_staging' AS source_table,
        fh.id AS source_record_id
    FROM file_history_staging fh
    WHERE fh.prop_id IS NOT NULL

    UNION ALL
    SELECT DISTINCT
        UPPER(LTRIM(RTRIM(COALESCE(pra.mlsFNo, pra.kangisFileNo, pra.NewKANGISFileno, pra.temp_fileno)))),
        pra.prop_id,
        pra.mlsFNo,
        pra.kangisFileNo,
        pra.NewKANGISFileno,
        pra.temp_fileno,
        'pra',
        pra.id
    FROM pra
    WHERE pra.prop_id IS NOT NULL

    UNION ALL
    SELECT DISTINCT
        UPPER(LTRIM(RTRIM(COALESCE(pic.mlsFNo, pic.kangisFileNo, pic.NewKANGISFileno, pic.temp_fileno)))),
        pic.prop_id,
        pic.mlsFNo,
        pic.kangisFileNo,
        pic.NewKANGISFileno,
        pic.temp_fileno,
        'pic',
        pic.id
    FROM pic
    WHERE pic.prop_id IS NOT NULL

    UNION ALL
    SELECT DISTINCT
        UPPER(LTRIM(RTRIM(COALESCE(ic.mlsFNo, ic.kangisFileNo, ic.NewKANGISFileno, ic.temp_fileno)))),
        ic.prop_id,
        ic.mlsFNo,
        ic.kangisFileNo,
        ic.NewKANGISFileno,
        ic.temp_fileno,
        'instrument_capture',
        ic.id
    FROM instrument_capture ic
    WHERE ic.prop_id IS NOT NULL
      AND (ic.is_deleted = 0 OR ic.is_deleted IS NULL)

    UNION ALL
    SELECT DISTINCT
        UPPER(LTRIM(RTRIM(COALESCE(dr.fileno, dr.parent_fileno)))),
        dr.prop_id,
        dr.fileno AS mlsFNo,
        NULL AS kangisFileNo,
        NULL AS NewKANGISFileno,
        CASE WHEN UPPER(LTRIM(RTRIM(dr.fileno))) LIKE 'TEMP%' THEN dr.fileno ELSE NULL END AS temp_fileno,
        'deed_registrations',
        dr.id
    FROM deed_registrations dr
    WHERE dr.prop_id IS NOT NULL

    UNION ALL
    SELECT DISTINCT
        UPPER(LTRIM(RTRIM(COALESCE(co.mlsFNo, co.kangisFileNo, co.NewKANGISFileno, co.np_fileno)))),
        co.prop_id,
        co.mlsFNo,
        co.kangisFileNo,
        co.NewKANGISFileno,
        co.np_fileno AS temp_fileno,
        'CofO_staging',
        co.id
    FROM CofO_staging co
    WHERE co.prop_id IS NOT NULL
)
MERGE dbo.PropID_Master AS target
USING (
    SELECT *, ROW_NUMBER() OVER (PARTITION BY prop_id ORDER BY source_table, source_record_id DESC) AS rn
    FROM candidates
) AS src
ON target.prop_id = src.prop_id
WHEN NOT MATCHED AND src.rn = 1 THEN
    INSERT (prop_id, primary_file_number, mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno, source_table, source_record_id)
    VALUES (src.prop_id, src.primary_file_number_norm, src.mlsFNo, src.kangisFileNo, src.NewKANGISFileno, src.temp_fileno, src.source_table, src.source_record_id)
WHEN MATCHED AND src.rn = 1 THEN
    UPDATE SET target.primary_file_number = COALESCE(target.primary_file_number, src.primary_file_number_norm),
               target.mlsFNo = COALESCE(target.mlsFNo, src.mlsFNo),
               target.kangisFileNo = COALESCE(target.kangisFileNo, src.kangisFileNo),
               target.NewKANGISFileno = COALESCE(target.NewKANGISFileno, src.NewKANGISFileno),
               target.temp_fileno = COALESCE(target.temp_fileno, src.temp_fileno),
               target.source_table = src.source_table,
               target.source_record_id = src.source_record_id,
               target.updated_at = SYSUTCDATETIME();
GO
