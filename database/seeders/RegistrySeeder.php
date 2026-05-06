<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RegistrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $registries = [
            ['name' => 'Lands Registry', 'code' => 'LANDS', 'description' => 'Main Lands Registry'],
            ['name' => 'Cadastral Registry', 'code' => 'CAD', 'description' => 'Cadastral/Survey Registry'],
            ['name' => 'DCIV Registry', 'code' => 'DCIV', 'description' => 'Deeds, Conveyance, Instrument & Verification'],
            ['name' => 'Secret Registry', 'code' => 'SECRET', 'description' => 'Confidential/Secret Registry'],
            ['name' => 'KANGIS Registry', 'code' => 'KANGIS', 'description' => 'Kano Geographic Information System'],
            ['name' => 'SLTR Registry', 'code' => 'SLTR', 'description' => 'Systematic Land Title Registration'],
            ['name' => 'ST Registry', 'code' => 'ST', 'description' => 'Sectional Titling Registry'],
            ['name' => 'Deeds Registry', 'code' => 'DEEDS', 'description' => 'Deeds Registry (Legacy/Special)'],
        ];

        foreach ($registries as $registry) {
            \App\Models\Registry::updateOrCreate(
                ['code' => $registry['code']],
                $registry
            );
        }
    }
}
