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
        if (!Schema::connection('sqlsrv')->hasTable('physical_registries')) {
            Schema::connection('sqlsrv')->create('physical_registries', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Seed the physical registries table with default values
            $registries = [
                'Registry 1 - Land',
                'Registry 2 - Land',
                'Registry 3 - Land',
                'Registry 3_2 - Land',
                'Registry 1 - Deeds',
                'Registry 2 - Deeds',
                'Registry 1 - Cadastral',
                'Registry 2 - Cadastral',
                'KANGIS Registry',
                'SLTR Registry',
                'ST Registry',
                'DCIV Registry',
                'New Archive',
                'Other',
            ];

            $now = now();
            foreach ($registries as $registry) {
                DB::connection('sqlsrv')->table('physical_registries')->insert([
                    'name' => $registry,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('physical_registries');
    }
};
