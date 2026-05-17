<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VfcValuationItemsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $structureTypes = [
            'Permanent',
            'Semi-Permanent',
            'Permanent GF/FF',
            'Warehouse',
            'Semi-Permanent GF/FF',
            'Proposed Building'
        ];

        $completionStages = [
            'Building',
            'Complete',
            'Liatel',
            'Roof Level'
        ];

        $otherItems = [
            'Sandcare Wall',
            'Mud Wall',
            'Interlock Court yard',
            'Pavement',
            'Mass concrete pavement',
            'Borehole',
            'Open well',
            'Soackaway',
            'Pitlatrine',
            'Under ground Reservoir',
            'Cornstalk Fence',
            'Round Hut',
            'Granary (Rumbu)',
            'Pigeon nest',
            'Animal Shed',
            'Corrugated Iron Shed',
            'Reservoir',
            'Fish pond',
            'Poultry cage',
            'Wire mesh',
            'Fuel pump/tanks',
            'CIS shed',
            'Tukuba',
            'DPC',
            'Others'
        ];

        foreach ($structureTypes as $item) {
            DB::connection('sqlsrv')->table('vfc_valuation_items')->updateOrInsert(
                ['name' => $item, 'type' => 'StructureType'],
                ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        foreach ($completionStages as $item) {
            DB::connection('sqlsrv')->table('vfc_valuation_items')->updateOrInsert(
                ['name' => $item, 'type' => 'CompletionStage'],
                ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        foreach ($otherItems as $item) {
            DB::connection('sqlsrv')->table('vfc_valuation_items')->updateOrInsert(
                ['name' => $item, 'type' => 'Other'],
                ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // Distinct "Other" choices for all types
        DB::connection('sqlsrv')->table('vfc_valuation_items')->updateOrInsert(
            ['name' => 'Other', 'type' => 'StructureType'],
            ['is_active' => true, 'updated_at' => now()]
        );
        DB::connection('sqlsrv')->table('vfc_valuation_items')->updateOrInsert(
            ['name' => 'Other', 'type' => 'CompletionStage'],
            ['is_active' => true, 'updated_at' => now()]
        );
        DB::connection('sqlsrv')->table('vfc_valuation_items')->updateOrInsert(
            ['name' => 'Other', 'type' => 'Other'],
            ['is_active' => true, 'updated_at' => now()]
        );
    }
}
