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
        // ── Batches ────────────────────────────────────────────────────────────
        Schema::connection('sqlsrv')->create('cadastral_print_label_batches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('batch_number', 100)->unique();
            $table->unsignedInteger('batch_size')->default(0);
            $table->unsignedInteger('generated_count')->default(0);
            $table->string('label_format', 30)->default('standard')->comment('standard|compact|qr_code|30-in-1');
            $table->string('orientation', 20)->default('portrait')->comment('portrait|landscape');
            $table->string('rack_system', 30)->default('standard');
            $table->string('status', 30)->default('pending')->comment('pending|generated|printed|completed');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });

        // ── Batch Items ────────────────────────────────────────────────────────
        Schema::connection('sqlsrv')->create('cadastral_print_label_batch_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('batch_id');
            $table->unsignedBigInteger('file_indexing_id')->nullable();
            $table->string('file_number', 100)->nullable();
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
                  ->on('cadastral_print_label_batches')
                  ->onDelete('cascade');

            $table->index('batch_id');
            $table->index('file_number');
            $table->index('file_indexing_id');
        });

        // ── Rack / Shelf Labels (isolated copy, mirrors Rack_Shelf_Labels) ──────
        Schema::connection('sqlsrv')->create('cadastral_rack_shelf_labels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('rack', 10)->comment('Rack letter e.g. A, B, C');
            $table->string('shelf', 10)->comment('Shelf number e.g. 1, 2, 3');
            $table->string('full_label', 20)->unique()->comment('Combined rack+shelf e.g. A1');
            $table->boolean('is_used')->default(false);
            $table->integer('counter')->default(0)->comment('Number of files assigned to this label');
            $table->string('assigned', 255)->nullable()->comment('Assignment info e.g. registry|batch_no');
            $table->string('reserved_by', 255)->nullable();
            $table->datetime('reserved_at')->nullable();
            $table->timestamps();

            $table->index(['rack', 'shelf']);
        });

        // Seed A1–A100 through Z1–Z100 (2600 labels) to mirror the base rack/shelf set.
        $rows = [];
        $now = now()->format('Y-m-d H:i:s');
        foreach (range('A', 'Z') as $rack) {
            for ($shelf = 1; $shelf <= 100; $shelf++) {
                $rows[] = [
                    'rack'       => $rack,
                    'shelf'      => (string) $shelf,
                    'full_label' => $rack . $shelf,
                    'is_used'    => 0,
                    'counter'    => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($rows) >= 200) {
                    DB::connection('sqlsrv')->table('cadastral_rack_shelf_labels')->insert($rows);
                    $rows = [];
                }
            }
        }

        if (!empty($rows)) {
            DB::connection('sqlsrv')->table('cadastral_rack_shelf_labels')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('cadastral_print_label_batch_items');
        Schema::connection('sqlsrv')->dropIfExists('cadastral_print_label_batches');
        Schema::connection('sqlsrv')->dropIfExists('cadastral_rack_shelf_labels');
    }
};
