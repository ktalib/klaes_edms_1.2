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
        Schema::connection('sqlsrv')->create('kangis_rack_shelf_labels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('rack', 10)->comment('Rack letter e.g. A, B, C');
            $table->string('shelf', 10)->comment('Shelf number e.g. 1, 2, 3');
            $table->string('full_label', 20)->unique()->comment('Combined rack+shelf e.g. A1');
            $table->boolean('is_used')->default(false);
            $table->string('counter', 20)->default('0')->comment('Number of files assigned to this label');
            $table->string('assigned', 255)->nullable()->comment('Assignment info e.g. KANGIS-KNML');
            $table->string('status', 30)->default('Available')->comment('Available|Occupied|Full');
            $table->string('reserved_by', 255)->nullable();
            $table->datetime('reserved_at')->nullable();
            $table->timestamps();

            $table->index(['rack', 'shelf']);
            $table->index('status');
        });

        // Seed A1–A100 through Z1–Z100 (2600 labels)
        $rows = [];
        $now = now()->format('Y-m-d H:i:s');
        foreach (range('A', 'Z') as $rack) {
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

                // Insert in chunks of 200
                if (count($rows) >= 200) {
                    DB::connection('sqlsrv')->table('kangis_rack_shelf_labels')->insert($rows);
                    $rows = [];
                }
            }
        }

        if (!empty($rows)) {
            DB::connection('sqlsrv')->table('kangis_rack_shelf_labels')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('kangis_rack_shelf_labels');
    }
};
