<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('detailed_land_use', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('sqlsrv')->create('jsi_lut_purposes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('land_use_id');
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('land_use_id', 'jsi_lut_purposes_land_use_fk')
                ->references('id')
                ->on('detailed_land_use')
                ->onDelete('cascade');
        });

        Schema::connection('sqlsrv')->create('jsi_lut_road_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 500);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('sqlsrv')->create('jsi_lut_reservations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 700);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::connection('sqlsrv')->table('detailed_land_use')->insert([
            ['name' => 'Residential', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Commercial', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Agricultural', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Industrial', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $landUseIds = DB::connection('sqlsrv')
            ->table('detailed_land_use')
            ->pluck('id', 'name');

        $purposeSeedMap = [
            'Commercial' => [
                'COM-PFS',
                'COM-CNG',
                'COM-LPG',
                'COM-Warehouse',
                'COM-Shops & offices',
                'COM-Garage',
                'COM-Hotel',
                'COM-Car park',
                'COM-Institutional',
                'COM-plaza',
                'COM- SPORT CENTER',
                'COM- VIEWING CENTER',
                'COM-SHOPPING MALL',
                'OTHERS',
            ],
            'Agricultural' => [
                'AG-Crop production',
                'AG-Mix farming',
                'AG-Crop cultivation',
                'AG-Orchard',
                'AG-Poultry',
                'AG-Fishery',
                'OTHERS',
            ],
            'Industrial' => [
                'IND-Quarry',
                'IND-Small scale',
                'IND-Processing',
                'IND-Packaging',
                'IND-OIL MILL',
                'IND- RICE MILL',
                'OTHERS',
            ],
            'Residential' => [
                'RES-Estate',
                'RES-Layout',
            ],
        ];

        $purposeRows = [];
        foreach ($purposeSeedMap as $landUseName => $purposeNames) {
            $landUseId = $landUseIds[$landUseName] ?? null;
            if (!$landUseId) {
                continue;
            }

            foreach ($purposeNames as $purposeName) {
                $purposeRows[] = [
                    'land_use_id' => $landUseId,
                    'name' => $purposeName,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($purposeRows)) {
            DB::connection('sqlsrv')->table('jsi_lut_purposes')->insert($purposeRows);
        }

        DB::connection('sqlsrv')->table('jsi_lut_road_categories')->insert([
            ['name' => 'FEDERAL HIGHWAYS (Truck A) also know as Express Road', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'STATE ROADS (Truck B) also know as Arterial roads.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'LOCAL ROADS (Truck c) also know as collector Roads.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'RURAL ROADS (Feeder/Access road)', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'FEDERAL HIGHWAYS (100m Right of way/ 50m Road Reservation, with the exception of Urban areas ware 45m to 30m are served as the Road reservations.)', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'STATE ROADS (60m R.O.W and 30m Road Reservation)', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'LOCAL ROADS ( 30m R.O.W and 15m R.R)', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'RURAL ROADS (15m R.O.W / 7.5m R.R)', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection('sqlsrv')->table('jsi_lut_reservations')->insert([
            ['name' => 'FEDERAL HIGHWAYS (100m Right of way/ 50m Road Reservation, with the exception of Urban areas ware 45m to 30m are served as the Road reservations.)', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'STATE ROADS (60m R.O.W And 30m Road Reservation)', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'LOCAL ROADS ( 30m R.O.W and 15m R.R)', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'RURAL ROADS (15m R.O.W / 7.5m R.R)', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'HIGH TENSION LINE 330kv ( 60m R.O.W & 30m R.R) and LOWER VOLTAGE 132 KV/11KV ( 30m R.O.W/ 12m R.O.W)', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'RIVER, STREAM, POND (Minimum of 15m for river, 10-15m for pond, stream 15m)', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'RAILWAY LINE ( 60m R.O.W & 30m R.R)', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'PIPELINE ( 60m R.O.W & 30m R.R)', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'CATTLE TRACK ( 30m R.O.W & 15m R.R)', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'OTHER', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('jsi_lut_reservations');
        Schema::connection('sqlsrv')->dropIfExists('jsi_lut_road_categories');
        Schema::connection('sqlsrv')->dropIfExists('jsi_lut_purposes');
        Schema::connection('sqlsrv')->dropIfExists('detailed_land_use');
        Schema::connection('sqlsrv')->dropIfExists('jsi_lut_purposes');
        
    }
};
