<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('physical_registries', function (Blueprint $table) {
            $table->string('registry_code', 4)->nullable()->after('name');
        });

        // Seed default codes for existing registries
        $defaults = [
            1  => 'R001', // Registry 1 - Land
            2  => 'R002', // Registry 2 - Land
            3  => 'R003', // Registry 3 - Land
            4  => 'R004', // Registry 1 - Deed
            5  => 'R005', // Registry 2 - Deeds
            6  => 'R006', // Registry 1 - Cadastral
            7  => 'R007', // Registry 2 - Cadastral
            8  => 'R008', // KANGIS Registry
            9  => 'R009', // SLTR Registry
            10 => 'R010', // ST Registry
            11 => 'R011', // DCIV Registry
            12 => 'R012', // New Archive
            13 => 'R013', // Other
            14 => 'R014', // Main Archive
            15 => 'R015', // Temporary Storage
            16 => 'R016', // Survey Registry
            17 => 'R017', // SIT Registry
            18 => 'R018', // Registry 3_2 - Land
        ];

        foreach ($defaults as $id => $code) {
            DB::connection('sqlsrv')->table('physical_registries')
                ->where('id', $id)
                ->update(['registry_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('physical_registries', function (Blueprint $table) {
            $table->dropColumn('registry_code');
        });
    }
};
