<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LandRofoSerialNoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $serialNumbers = [];
        $count = 0;
        
        while ($count < 50) {
            $number = str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            
            if (!in_array($number, $serialNumbers)) {
                $serialNumbers[] = $number;
                $count++;
            }
        }

        sort($serialNumbers);

        foreach ($serialNumbers as $serialNo) {
            \Illuminate\Support\Facades\DB::connection('sqlsrv')->table('land_rofo_serial_no')->insert([
                'serial_no' => $serialNo,
                'is_used' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
