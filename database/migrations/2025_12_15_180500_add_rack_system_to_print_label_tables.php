<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('print_label_batches', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('print_label_batches', 'rack_system')) {
                $table->string('rack_system', 32)->default('default')->after('orientation');
            }
        });

        Schema::connection('sqlsrv')->table('print_label_batch_items', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('print_label_batch_items', 'rack_system')) {
                $table->string('rack_system', 32)->nullable()->after('shelf_location');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->table('print_label_batches', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('print_label_batches', 'rack_system')) {
                $table->dropColumn('rack_system');
            }
        });

        Schema::connection('sqlsrv')->table('print_label_batch_items', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('print_label_batch_items', 'rack_system')) {
                $table->dropColumn('rack_system');
            }
        });
    }
};
