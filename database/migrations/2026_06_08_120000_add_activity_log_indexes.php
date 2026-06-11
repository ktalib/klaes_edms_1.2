<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Indexes that speed up the File Indexing Activity Log.
 *
 * The report counts transactions per file number across the staging tables and
 * joins related rows by file_indexing_id. Without these indexes those lookups
 * fall back to full table scans, which is what makes the page slow on production.
 *
 *   - file_history_staging: no index on any file-number column
 *   - CofO_staging: only mlsFNo was indexed
 *   - file_indexing_links: only the PK on id existed
 */
return new class extends Migration {
    private array $indexes = [
        ['file_history_staging', 'idx_fhs_mlsFNo', 'mlsFNo'],
        ['file_history_staging', 'idx_fhs_fileno', 'fileno'],
        ['file_history_staging', 'idx_fhs_kangisFileNo', 'kangisFileNo'],
        ['file_history_staging', 'idx_fhs_NewKANGISFileno', 'NewKANGISFileno'],
        ['CofO_staging', 'idx_cofo_fileno', 'fileno'],
        ['CofO_staging', 'idx_cofo_kangisFileNo', 'kangisFileNo'],
        ['CofO_staging', 'idx_cofo_NewKANGISFileno', 'NewKANGISFileno'],
        ['file_indexing_links', 'idx_fil_file_indexing_id', 'file_indexing_id'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $index, $column]) {
            DB::connection('sqlsrv')->statement(
                "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = '{$index}' AND object_id = OBJECT_ID('[dbo].[{$table}]'))
                 AND EXISTS (SELECT 1 FROM sys.columns WHERE name = '{$column}' AND object_id = OBJECT_ID('[dbo].[{$table}]'))
                 CREATE NONCLUSTERED INDEX [{$index}] ON [dbo].[{$table}] ([{$column}]);"
            );
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [$table, $index, $column]) {
            DB::connection('sqlsrv')->statement(
                "IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = '{$index}' AND object_id = OBJECT_ID('[dbo].[{$table}]'))
                 DROP INDEX [{$index}] ON [dbo].[{$table}];"
            );
        }
    }
};
