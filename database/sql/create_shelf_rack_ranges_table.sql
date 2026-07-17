-- shelf_rack_ranges
-- Physical shelf map: which file-number series and serial range sit on each
-- rack/shelf, per registry. Source: the "FileNo Combination" workbooks in
-- docs/data/shalf_racks, loaded via `php artisan shelf-racks:import`.
--
-- Mirrors database/migrations/2026_07_17_120000_create_shelf_rack_ranges_table.php
-- for environments where the SQL scripts are applied directly (SQL Server / klas).
--
-- NOTE: (registry_id, rack, shelf) is intentionally NOT unique. Two workbook sets
-- exist and disagree on 56 shelves -- the older "FileNo Combination_Rack *.xlsx"
-- set labels them CON-RES-*, while the newer "FileNo Combination_2_Rack *.xlsx"
-- set labels the same serial ranges RES-*. Both are retained and distinguished by
-- source_file / set_version rather than reconciled at load time.

IF OBJECT_ID('dbo.shelf_rack_ranges', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.shelf_rack_ranges (
        id              BIGINT IDENTITY(1,1) NOT NULL,

        -- physical_registries.id: 1 (Registry 1 - Land) or 3 (Registry 3 - Land).
        registry_id     BIGINT        NOT NULL,

        rack            NVARCHAR(10)  NOT NULL,
        shelf           SMALLINT      NOT NULL,
        rack_shelf      NVARCHAR(20)  NOT NULL,

        -- NULL where the workbook lists a shelf but leaves it unallocated.
        file_no         NVARCHAR(50)      NULL,

        -- Parsed from "SERIALNO RANGE"; serial_range keeps the raw cell text
        -- because the sheets are inconsistently spaced ("701 -800").
        serial_from     INT               NULL,
        serial_to       INT               NULL,
        serial_range    NVARCHAR(50)      NULL,

        -- Provenance. set_version 1 = "FileNo Combination_Rack *.xlsx",
        --             set_version 2 = "FileNo Combination_2_Rack *.xlsx".
        source_file     NVARCHAR(150) NOT NULL,
        set_version     TINYINT       NOT NULL,
        source_sn       INT               NULL,

        -- Best-effort link to Rack_Shelf_Labels.id; NULL for the 121 workbook
        -- labels with no row there yet. Deliberately no FK constraint.
        shelf_label_id  BIGINT            NULL,

        created_at      DATETIME          NULL,
        updated_at      DATETIME          NULL,

        CONSTRAINT PK_shelf_rack_ranges PRIMARY KEY CLUSTERED (id)
    );

    CREATE INDEX shelf_rack_ranges_registry_rack_shelf_idx
        ON dbo.shelf_rack_ranges (registry_id, rack, shelf);

    CREATE INDEX shelf_rack_ranges_file_no_idx
        ON dbo.shelf_rack_ranges (file_no);

    CREATE INDEX shelf_rack_ranges_rack_shelf_idx
        ON dbo.shelf_rack_ranges (rack_shelf);

    CREATE INDEX shelf_rack_ranges_shelf_label_idx
        ON dbo.shelf_rack_ranges (shelf_label_id);

    -- Idempotency key for the importer: a rack/shelf appears at most once per
    -- workbook (verified across all 41 files).
    CREATE UNIQUE INDEX shelf_rack_ranges_source_rack_shelf_unq
        ON dbo.shelf_rack_ranges (source_file, rack, shelf);
END
GO
