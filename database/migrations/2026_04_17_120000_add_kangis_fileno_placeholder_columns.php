<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // file_indexings table
        if (!$this->columnExists('file_indexings', 'kangis_fileno_placeholder')) {
            DB::statement("ALTER TABLE file_indexings ADD kangis_fileno_placeholder NVARCHAR(100) NULL");
        }
        if (!$this->columnExists('file_indexings', 'kangis_fileno_resolved')) {
            DB::statement("ALTER TABLE file_indexings ADD kangis_fileno_resolved NVARCHAR(120) NULL");
        }

        // fileNumber table
        if (!$this->columnExists('fileNumber', 'kangis_fileno_placeholder')) {
            DB::statement("ALTER TABLE fileNumber ADD kangis_fileno_placeholder NVARCHAR(100) NULL");
        }
        if (!$this->columnExists('fileNumber', 'kangis_fileno_resolved')) {
            DB::statement("ALTER TABLE fileNumber ADD kangis_fileno_resolved NVARCHAR(120) NULL");
        }
    }

    public function down(): void
    {
        foreach (['file_indexings', 'fileNumber'] as $table) {
            foreach (['kangis_fileno_placeholder', 'kangis_fileno_resolved'] as $column) {
                if ($this->columnExists($table, $column)) {
                    DB::statement("ALTER TABLE {$table} DROP COLUMN {$column}");
                }
            }
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) DB::connection('sqlsrv')
            ->table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->exists();
    }
};
