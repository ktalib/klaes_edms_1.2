<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create fileindexing_batch table for tracking batch assignments
        if (!Schema::connection('sqlsrv')->hasTable('fileindexing_batch')) {
            Schema::connection('sqlsrv')->create('fileindexing_batch', function (Blueprint $table) {
                $table->id();
                $table->integer('batch_number')->unique()->comment('Sequential batch number');
                $table->integer('start_shelf_id')->comment('Starting Rack_Shelf_Labels.id for this batch');
                $table->integer('end_shelf_id')->comment('Ending Rack_Shelf_Labels.id for this batch');
                $table->integer('shelf_count')->default(100)->comment('Number of shelves in this batch');
                $table->integer('used_shelves')->default(0)->comment('Number of shelves currently used in this batch');
                $table->boolean('is_full')->default(false)->comment('Whether all shelves in this batch are used');
                $table->boolean('is_active')->default(true)->comment('Whether this batch is available for assignment');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                // Indexes for performance
                $table->index('batch_number');
                $table->index(['is_active', 'is_full']);
                $table->index(['start_shelf_id', 'end_shelf_id']);
            });
        }

        // Add batch_id column to file_indexings table if it doesn't exist
        if (Schema::connection('sqlsrv')->hasTable('file_indexings')) {
            Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
                if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'batch_id')) {
                    $table->unsignedBigInteger('batch_id')->nullable()->after('batch_no')->comment('Reference to fileindexing_batch.id');
                    $table->index('batch_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove batch_id column from file_indexings
        if (Schema::connection('sqlsrv')->hasTable('file_indexings')) {
            Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $table) {
                if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'batch_id')) {
                    $table->dropIndex(['batch_id']);
                    $table->dropColumn('batch_id');
                }
            });
        }

        // Drop fileindexing_batch table
        Schema::connection('sqlsrv')->dropIfExists('fileindexing_batch');
    }
};