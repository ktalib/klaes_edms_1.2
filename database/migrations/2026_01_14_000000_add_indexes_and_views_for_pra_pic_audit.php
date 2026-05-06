<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $connection = DB::connection('sqlsrv');

        $statements = [
            "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_pra_created_by' AND object_id = OBJECT_ID('[dbo].[pra]'))\nBEGIN\n    CREATE NONCLUSTERED INDEX [idx_pra_created_by] ON [dbo].[pra] ([created_by]) INCLUDE ([created_at]);\nEND",
            "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_pra_created_at' AND object_id = OBJECT_ID('[dbo].[pra]'))\nBEGIN\n    CREATE NONCLUSTERED INDEX [idx_pra_created_at] ON [dbo].[pra] ([created_at]);\nEND",
            "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_pic_created_by' AND object_id = OBJECT_ID('[dbo].[pic]'))\nBEGIN\n    CREATE NONCLUSTERED INDEX [idx_pic_created_by] ON [dbo].[pic] ([created_by]) INCLUDE ([created_at]);\nEND",
            "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_pic_created_at' AND object_id = OBJECT_ID('[dbo].[pic]'))\nBEGIN\n    CREATE NONCLUSTERED INDEX [idx_pic_created_at] ON [dbo].[pic] ([created_at]);\nEND",
        ];

        foreach ($statements as $sql) {
            $connection->statement($sql);
        }

        $connection->statement(<<<'SQL'
IF OBJECT_ID(N'[dbo].[pra_created_by_strings]', N'V') IS NOT NULL
    DROP VIEW [dbo].[pra_created_by_strings];
SQL);

        $connection->statement(<<<'SQL'
CREATE VIEW [dbo].[pra_created_by_strings] AS
SELECT
    COALESCE(NULLIF(LTRIM(RTRIM(created_by)), ''), 'Unattributed') AS created_by_label,
    COUNT_BIG(*) AS total_records,
    MIN(created_at) AS first_created_at,
    MAX(created_at) AS last_created_at
FROM [dbo].[pra]
WHERE TRY_CONVERT(int, created_by) IS NULL
GROUP BY COALESCE(NULLIF(LTRIM(RTRIM(created_by)), ''), 'Unattributed');
SQL);

        $connection->statement(<<<'SQL'
IF OBJECT_ID(N'[dbo].[pic_created_by_strings]', N'V') IS NOT NULL
    DROP VIEW [dbo].[pic_created_by_strings];
SQL);

        $connection->statement(<<<'SQL'
CREATE VIEW [dbo].[pic_created_by_strings] AS
SELECT
    COALESCE(NULLIF(LTRIM(RTRIM(created_by)), ''), 'Unattributed') AS created_by_label,
    COUNT_BIG(*) AS total_records,
    MIN(created_at) AS first_created_at,
    MAX(created_at) AS last_created_at
FROM [dbo].[pic]
WHERE TRY_CONVERT(int, created_by) IS NULL
GROUP BY COALESCE(NULLIF(LTRIM(RTRIM(created_by)), ''), 'Unattributed');
SQL);
    }

    public function down(): void
    {
        $connection = DB::connection('sqlsrv');

        $drops = [
            "IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_pra_created_by' AND object_id = OBJECT_ID('[dbo].[pra]'))\n    DROP INDEX [idx_pra_created_by] ON [dbo].[pra];",
            "IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_pra_created_at' AND object_id = OBJECT_ID('[dbo].[pra]'))\n    DROP INDEX [idx_pra_created_at] ON [dbo].[pra];",
            "IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_pic_created_by' AND object_id = OBJECT_ID('[dbo].[pic]'))\n    DROP INDEX [idx_pic_created_by] ON [dbo].[pic];",
            "IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_pic_created_at' AND object_id = OBJECT_ID('[dbo].[pic]'))\n    DROP INDEX [idx_pic_created_at] ON [dbo].[pic];",
        ];

        foreach ($drops as $sql) {
            $connection->statement($sql);
        }

        $connection->statement(<<<'SQL'
IF OBJECT_ID(N'[dbo].[pra_created_by_strings]', N'V') IS NOT NULL
    DROP VIEW [dbo].[pra_created_by_strings];
SQL);

        $connection->statement(<<<'SQL'
IF OBJECT_ID(N'[dbo].[pic_created_by_strings]', N'V') IS NOT NULL
    DROP VIEW [dbo].[pic_created_by_strings];
SQL);
    }
};
