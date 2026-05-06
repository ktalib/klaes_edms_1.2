<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('fileindexing_batch')) {
            return;
        }

        Schema::connection('sqlsrv')->table('fileindexing_batch', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('fileindexing_batch', 'shelf_label_id')) {
                $table->integer('shelf_label_id')
                    ->nullable()
                    ->after('batch_number')
                    ->comment('Single shelf label assigned to this batch');
            }

            if (!Schema::connection('sqlsrv')->hasColumn('fileindexing_batch', 'full_label')) {
                $table->string('full_label', 255)
                    ->nullable()
                    ->after('shelf_label_id')
                    ->comment('Human readable shelf label reference');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('fileindexing_batch')) {
            return;
        }

        Schema::connection('sqlsrv')->table('fileindexing_batch', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('fileindexing_batch', 'full_label')) {
                $table->dropColumn('full_label');
            }

            if (Schema::connection('sqlsrv')->hasColumn('fileindexing_batch', 'shelf_label_id')) {
                $table->dropColumn('shelf_label_id');
            }
        });
    }
};