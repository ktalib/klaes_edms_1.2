<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MlsSerialControlSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $currentYear = date('Y');

        // Pre-populate with known manual records
        // These will be unlocked so users can initialize them via UI if needed
        $landUses = [
            ['land_use' => 'RES', 'last_serial' => 564],
            ['land_use' => 'COM', 'last_serial' => 76],
            ['land_use' => 'IND', 'last_serial' => 0],
            ['land_use' => 'AG', 'last_serial' => 0],
            ['land_use' => 'RES-RC', 'last_serial' => 0],
            ['land_use' => 'COM-RC', 'last_serial' => 0],
            ['land_use' => 'AG-RC', 'last_serial' => 0],
            ['land_use' => 'IND-RC', 'last_serial' => 0],
            ['land_use' => 'CON-RES', 'last_serial' => 0],
            ['land_use' => 'CON-COM', 'last_serial' => 0],
            ['land_use' => 'CON-IND', 'last_serial' => 0],
            ['land_use' => 'CON-AG', 'last_serial' => 0],
            ['land_use' => 'CON-RES-RC', 'last_serial' => 0],
            ['land_use' => 'CON-COM-RC', 'last_serial' => 0],
            ['land_use' => 'CON-AG-RC', 'last_serial' => 0],
        ];

        foreach ($landUses as $landUseData) {
            DB::connection('sqlsrv')->table('mls_serial_control')->updateOrInsert(
                [
                    'land_use' => $landUseData['land_use'],
                    'year' => $currentYear
                ],
                [
                    'last_serial' => $landUseData['last_serial'],
                    'is_initialized' => true,
                    'initialized_at' => now(),
                    'initialized_by' => 'System Migration',
                    'is_locked' => false, // Allow UI initialization
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }

        $this->command->info('MLS Serial Control records seeded successfully!');
        $this->command->info('RES initialized at serial 564');
        $this->command->info('COM initialized at serial 76');
        $this->command->info('Other land uses initialized at 0');
    }
}
