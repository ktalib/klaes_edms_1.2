<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        // 1. Batches
        if (!Schema::connection('sqlsrv')->hasTable('dciv_print_label_batches')) {
            Schema::connection('sqlsrv')->create('dciv_print_label_batches', function (Blueprint $table) {
                $table->id();
                $table->string('batch_number', 50)->unique();
                $table->string('prefix', 20)->default('DCIV');
                $table->integer('sys_batch_no')->nullable();
                $table->integer('batch_size')->nullable();
                $table->integer('generated_count')->nullable();
                $table->string('status', 30)->default('pending');
                $table->string('full_label', 30)->nullable();
                $table->string('rack_primary', 5)->nullable();
                $table->string('rack_secondary', 5)->nullable();
                $table->integer('shelf_number')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamp('printed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });

            // Unique filtered index: one active batch per prefix+sys_batch_no
            DB::connection('sqlsrv')->statement("
                CREATE UNIQUE NONCLUSTERED INDEX uq_dciv_plb_prefix_sysbatch
                ON dciv_print_label_batches (prefix, sys_batch_no)
                WHERE sys_batch_no IS NOT NULL AND deleted_at IS NULL AND status <> 'pending'
            ");
        }

        // 2. Batch items
        if (!Schema::connection('sqlsrv')->hasTable('dciv_print_label_batch_items')) {
            Schema::connection('sqlsrv')->create('dciv_print_label_batch_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('batch_id');
                $table->unsignedBigInteger('file_indexing_id')->nullable();
                $table->string('file_number', 100)->nullable();
                $table->string('prefix', 20)->nullable();
                $table->string('file_title', 255)->nullable();
                $table->string('plot_number', 100)->nullable();
                $table->string('district', 100)->nullable();
                $table->string('lga', 100)->nullable();
                $table->string('land_use_type', 100)->nullable();
                $table->string('shelf_location', 100)->nullable();
                $table->integer('label_position')->nullable();
                $table->text('qr_code_data')->nullable();
                $table->string('barcode_data', 255)->nullable();
                $table->boolean('is_printed')->default(false);
                $table->timestamp('printed_at')->nullable();
                $table->timestamps();

                $table->foreign('batch_id')
                    ->references('id')
                    ->on('dciv_print_label_batches')
                    ->cascadeOnDelete();
            });
        }

        // 3. Rack/shelf labels (A1–Z100 = 2600 rows)
        if (!Schema::connection('sqlsrv')->hasTable('dciv_rack_shelf_labels')) {
            Schema::connection('sqlsrv')->create('dciv_rack_shelf_labels', function (Blueprint $table) {
                $table->id();
                $table->string('rack', 5)->nullable();
                $table->string('shelf', 10)->nullable();
                $table->string('full_label', 20)->nullable();
                $table->boolean('is_used')->default(false);
                $table->string('counter', 20)->default('0');
                $table->string('assigned', 30)->nullable();
                $table->string('status', 20)->default('Available');
                $table->unsignedBigInteger('reserved_by')->nullable();
                $table->timestamp('reserved_at')->nullable();
                $table->timestamps();
            });

            // Seed A1–Z100
            foreach (range('A', 'Z') as $rack) {
                $rows = [];
                $now = now()->format('Y-m-d H:i:s');
                for ($shelf = 1; $shelf <= 100; $shelf++) {
                    $rows[] = [
                        'rack'       => $rack,
                        'shelf'      => (string) $shelf,
                        'full_label' => $rack . $shelf,
                        'is_used'    => 0,
                        'counter'    => '0',
                        'status'     => 'Available',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::connection('sqlsrv')->table('dciv_rack_shelf_labels')->insert($rows);
            }
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('dciv_print_label_batch_items');
        Schema::connection('sqlsrv')->dropIfExists('dciv_print_label_batches');
        Schema::connection('sqlsrv')->dropIfExists('dciv_rack_shelf_labels');
    }
};
