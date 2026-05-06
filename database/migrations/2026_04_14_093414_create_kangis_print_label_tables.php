<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        // ── Batches ────────────────────────────────────────────────────────────
        Schema::connection('sqlsrv')->create('kangis_print_label_batches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('batch_number', 100)->unique();
            $table->string('prefix', 20)->nullable()->comment('KNML | MNKL | MLKN | KNGP');
            $table->unsignedInteger('batch_size')->default(0);
            $table->unsignedInteger('generated_count')->default(0);
            $table->string('status', 30)->default('pending')->comment('pending|generated|printed|completed');
            $table->string('full_label', 30)->nullable()->comment('Shelf label e.g. A1');
            $table->string('rack_primary', 10)->nullable();
            $table->string('rack_secondary', 10)->nullable();
            $table->unsignedInteger('shelf_number')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->index('prefix');
            $table->index('status');
            $table->index('created_at');
        });

        // ── Batch Items ────────────────────────────────────────────────────────
        Schema::connection('sqlsrv')->create('kangis_print_label_batch_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('batch_id');
            $table->unsignedBigInteger('file_indexing_id')->nullable();
            $table->string('file_number', 100)->nullable();
            $table->string('prefix', 20)->nullable();
            $table->string('file_title')->nullable();
            $table->string('plot_number', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('lga', 100)->nullable();
            $table->string('land_use_type', 100)->nullable();
            $table->string('shelf_location', 50)->nullable();
            $table->text('qr_code_data')->nullable();
            $table->string('barcode_data', 200)->nullable();
            $table->unsignedInteger('label_position')->default(1);
            $table->boolean('is_printed')->default(false);
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->foreign('batch_id')
                  ->references('id')
                  ->on('kangis_print_label_batches')
                  ->onDelete('cascade');

            $table->index('batch_id');
            $table->index('file_number');
            $table->index('prefix');
            $table->index('file_indexing_id');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('kangis_print_label_batch_items');
        Schema::connection('sqlsrv')->dropIfExists('kangis_print_label_batches');
    }
};
