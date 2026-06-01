<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('st_print_label_batches')) {
            return;
        }

        if (!Schema::connection('sqlsrv')->hasColumn('st_print_label_batches', 'application_type')) {
            DB::connection('sqlsrv')->statement(
                "ALTER TABLE st_print_label_batches ADD application_type NVARCHAR(20) NULL"
            );
        }
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('st_print_label_batches')) {
            return;
        }

        if (Schema::connection('sqlsrv')->hasColumn('st_print_label_batches', 'application_type')) {
            DB::connection('sqlsrv')->statement(
                "ALTER TABLE st_print_label_batches DROP COLUMN application_type"
            );
        }
    }
};
