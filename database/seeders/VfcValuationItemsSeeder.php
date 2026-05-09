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
        $items = [
            'Permanent',
            'Semi-Permanent',
            'Permanent GF/FF',
            'Warehouse',
            'Semi-Permanent GF/FF',
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

        foreach ($items as $item) {
            DB::connection('sqlsrv')->table('vfc_valuation_items')->updateOrInsert(
                ['name' => $item],
                ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
