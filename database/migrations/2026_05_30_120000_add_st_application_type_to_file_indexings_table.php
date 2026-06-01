<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('sqlsrv');

        if (!Schema::connection('sqlsrv')->hasTable('file_indexings')) {
            return;
        }

        if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'st_application_type')) {
            // Raw T-SQL: SQL Server does not support MySQL's AFTER clause.
            $conn->statement(
                "ALTER TABLE file_indexings ADD st_application_type NVARCHAR(20) NULL"
            );
        }

        // Filtered index helps the ST print-label load page.
        try {
            $conn->statement(
                "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_file_indexings_st_app_type' AND object_id = OBJECT_ID('file_indexings'))
                 CREATE INDEX idx_file_indexings_st_app_type ON file_indexings (st_application_type) WHERE st_application_type IS NOT NULL"
            );
        } catch (\Throwable $e) {
            // Filtered-index syntax may not be supported on this edition; non-fatal.
        }
    }

    public function down(): void
    {
        $conn = DB::connection('sqlsrv');

        if (!Schema::connection('sqlsrv')->hasTable('file_indexings')) {
            return;
        }

        try {
            $conn->statement(
                "IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'idx_file_indexings_st_app_type' AND object_id = OBJECT_ID('file_indexings'))
                 DROP INDEX idx_file_indexings_st_app_type ON file_indexings"
            );
        } catch (\Throwable $e) {
            // ignore
        }

        if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'st_application_type')) {
            $conn->statement("ALTER TABLE file_indexings DROP COLUMN st_application_type");
        }
    }
};
