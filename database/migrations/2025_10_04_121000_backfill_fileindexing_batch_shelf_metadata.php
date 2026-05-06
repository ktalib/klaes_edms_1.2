<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('fileindexing_batch')) {
            return;
        }

        DB::connection('sqlsrv')->statement("UPDATE fb
            SET shelf_label_id = COALESCE(fb.shelf_label_id, fb.start_shelf_id),
                full_label = COALESCE(fb.full_label, r.full_label)
            FROM fileindexing_batch fb
            LEFT JOIN Rack_Shelf_Labels r ON r.id = COALESCE(fb.shelf_label_id, fb.start_shelf_id)");
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('fileindexing_batch')) {
            return;
        }

        DB::connection('sqlsrv')->statement("UPDATE fileindexing_batch SET full_label = NULL WHERE 1 = 1");
        DB::connection('sqlsrv')->statement("UPDATE fileindexing_batch SET shelf_label_id = NULL WHERE 1 = 1");
    }
};
