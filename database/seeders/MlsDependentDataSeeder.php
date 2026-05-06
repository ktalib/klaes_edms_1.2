<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LandUse;
use App\Models\Prefix;
use App\Models\Purpose;

class MlsDependentDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Clear existing data from prefix and purposes tables to avoid duplication/conflicts
        Prefix::truncate();
        Purpose::truncate();
        
        $landUses = LandUse::all();

        foreach ($landUses as $lu) {
            // Determine base code from name
            $name = strtoupper($lu->landuse); 
            $baseCode = '';

            if (str_contains($name, 'RESIDENTIAL')) $baseCode = 'RES';
            elseif (str_contains($name, 'COMMERCIAL')) $baseCode = 'COM';
            elseif (str_contains($name, 'INDUSTRIAL')) $baseCode = 'IND';
            elseif (str_contains($name, 'AGRICULTURAL')) $baseCode = 'AG';
            else $baseCode = substr($name, 0, 3);

            // Generate Prefixes
            $prefixes = [];
            // Standard
            $prefixes[] = $baseCode;
            // RC
            $prefixes[] = $baseCode . '-RC';
            // Conversion
            $prefixes[] = 'CON-' . $baseCode;
            // Recertification
            $prefixes[] = 'RC-' . $baseCode;

            foreach ($prefixes as $prefix) {
                Prefix::create([
                    'land_use_id' => $lu->id,
                    'prefix' => $prefix
                ]);
            }
        }

        // Static Purposes from Screenshot
        $purposesData = [
            ['landuseid' => 1, 'name' => 'RESIDENTIAL', 'code' => 'RES'],
            ['landuseid' => 4, 'name' => 'AGRICULTURAL', 'code' => 'AGR'],
            ['landuseid' => 2, 'name' => 'COMMERCIAL', 'code' => 'COM'],
            ['landuseid' => 2, 'name' => 'COMMERCIAL (HOTEL)', 'code' => 'COM_WH'],
            ['landuseid' => 2, 'name' => 'COMMERCIAL (OFFICES)', 'code' => 'COM_OFF'],
            ['landuseid' => 2, 'name' => 'COMMERCIAL (PETROL FILLING STATION)', 'code' => 'COM_PFS'],
            ['landuseid' => 2, 'name' => 'COMMERCIAL (RICE PROCESSING)', 'code' => 'COM_RP'],
            ['landuseid' => 2, 'name' => 'COMMERCIAL (SCHOOL)', 'code' => 'COM_SCH'],
            ['landuseid' => 2, 'name' => 'COMMERCIAL (SHOPS & PUBLIC CONVENIENCE)', 'code' => 'COM_SPC'],
            ['landuseid' => 2, 'name' => 'COMMERCIAL (SHOPS AND OFFICES)', 'code' => 'COM_SO'],
            ['landuseid' => 2, 'name' => 'COMMERCIAL (SHOPS)', 'code' => 'COM_SH'],
            ['landuseid' => 2, 'name' => 'COMMERCIAL (WAREHOUSE)', 'code' => 'COM_WH2'],
            ['landuseid' => 2, 'name' => 'COMMERCIAL (WORKSHOP AND OFFICES)', 'code' => 'COM_WO'],
            ['landuseid' => 2, 'name' => 'COMMERCIAL AND RESIDENTIAL', 'code' => 'COM_RES'],
            ['landuseid' => 3, 'name' => 'INDUSTRIAL', 'code' => 'IND'],
            ['landuseid' => 3, 'name' => 'INDUSTRIAL (SMALL SCALE)', 'code' => 'IND_SS'],
            ['landuseid' => 2, 'name' => 'RESIDENTIAL AND COMMERCIAL', 'code' => 'RES_COM'],
            ['landuseid' => 2, 'name' => 'RESIDENTIAL/COMMERCIAL', 'code' => 'RES_COM2'],
            ['landuseid' => 2, 'name' => 'RESIDENTIAL/COMMERCIAL LAYOUT', 'code' => 'RES_COM_L'],
        ];

        foreach ($purposesData as $data) {
            Purpose::create($data);
        }
    }
}
