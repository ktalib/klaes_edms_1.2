<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OfficesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = Carbon::now();

        $offices = [
            ['office_code' => 'RCP', 'office_name' => 'Reception', 'office_abbreviation' => 'RCP', 'department' => 'Customer Service'],
            ['office_code' => 'CCU', 'office_name' => 'Customer Care Unit', 'office_abbreviation' => 'CCU', 'department' => 'Customer Service'],
            ['office_code' => 'DCIV', 'office_name' => 'Department of Complaints & Investigation', 'office_abbreviation' => 'DCIV', 'department' => 'Legal'],
            ['office_code' => 'SUR', 'office_name' => 'Survey Department', 'office_abbreviation' => 'SUR', 'department' => 'Technical'],
            ['office_code' => 'LEG', 'office_name' => 'Legal Department', 'office_abbreviation' => 'LEG', 'department' => 'Legal'],
            ['office_code' => 'PPD', 'office_name' => 'Physical Planning Department', 'office_abbreviation' => 'PPD', 'department' => 'Technical'],
            ['office_code' => 'DIR', 'office_name' => 'Director\'s Office', 'office_abbreviation' => 'DIR', 'department' => 'Management'],
            ['office_code' => 'CRT', 'office_name' => 'Certificate Issuance', 'office_abbreviation' => 'CRT', 'department' => 'Operations'],
            ['office_code' => 'DAR', 'office_name' => 'Digital Archive', 'office_abbreviation' => 'DAR', 'department' => 'Records'],
            ['office_code' => 'FIN', 'office_name' => 'Finance Department', 'office_abbreviation' => 'FIN', 'department' => 'Finance'],
            ['office_code' => 'ICT', 'office_name' => 'ICT Department', 'office_abbreviation' => 'ICT', 'department' => 'Technical'],
            ['office_code' => 'REG1', 'office_name' => 'Lands Registry 1', 'office_abbreviation' => 'REG', 'department' => 'Records'],
            ['office_code' => 'REG2', 'office_name' => 'Lands Registry 2', 'office_abbreviation' => 'REG', 'department' => 'Records'],
            ['office_code' => 'SRE', 'office_name' => 'Sectional Titling Registry', 'office_abbreviation' => 'SRE', 'department' => 'Records'],
            ['office_code' => 'STR', 'office_name' => 'SLTR Registry', 'office_abbreviation' => 'STR', 'department' => 'Records'],
            ['office_code' => 'KRE', 'office_name' => 'KANGIS Registry', 'office_abbreviation' => 'KRE', 'department' => 'Records'],
            ['office_code' => 'DRER', 'office_name' => 'DCIV Registry', 'office_abbreviation' => 'DRE', 'department' => 'Records'],
            ['office_code' => 'CRE1', 'office_name' => 'Cadastral Registry 1', 'office_abbreviation' => 'CRE', 'department' => 'Records'],
            ['office_code' => 'CRY2', 'office_name' => 'Cadastral Registry 2', 'office_abbreviation' => 'CRY', 'department' => 'Records'],
            ['office_code' => 'DRE1', 'office_name' => 'Deeds Registry 1', 'office_abbreviation' => 'DRE', 'department' => 'Records'],
            ['office_code' => 'DRY2', 'office_name' => 'Deeds Registry 2', 'office_abbreviation' => 'DRY', 'department' => 'Records'],
            ['office_code' => 'DL', 'office_name' => 'DEPARTMENT OF LAND', 'office_abbreviation' => 'DL', 'department' => 'Land Administration'],
            ['office_code' => 'ST', 'office_name' => 'Sectional Titling', 'office_abbreviation' => 'ST', 'department' => 'ST'],
            ['office_code' => 'SLTR', 'office_name' => 'SLTR', 'office_abbreviation' => 'SLTR', 'department' => 'SLTR'],
            ['office_code' => 'DEEDS', 'office_name' => 'Deeds Department', 'office_abbreviation' => 'DEEDS', 'department' => 'Deeds'],
            ['office_code' => 'CAD', 'office_name' => 'Cadastral Department', 'office_abbreviation' => 'CAD', 'department' => 'Cadastral'],
            ['office_code' => 'HC', 'office_name' => 'Commissioner\'s Office', 'office_abbreviation' => 'HC', 'department' => 'Commissioner'],
            ['office_code' => 'PS', 'office_name' => 'Office of the Permanent Secretary', 'office_abbreviation' => 'PS', 'department' => 'PS'],
            ['office_code' => 'DAGS', 'office_name' => 'Director Admin and General', 'office_abbreviation' => 'DAGS', 'department' => 'DAGS'],
            ['office_code' => 'DG', 'office_name' => 'Director General, KANGIS', 'office_abbreviation' => 'DG', 'department' => 'DG'],
            ['office_code' => 'DAGSK', 'office_name' => 'Director Admin and General, KANGIS', 'office_abbreviation' => 'DAGS', 'department' => 'DAGS'],
            ['office_code' => 'PRS', 'office_name' => 'Department of Planning, Research and Statistics', 'office_abbreviation' => 'PRS', 'department' => 'PRS'],
        ];

        // Add timestamps to each office
        foreach ($offices as &$office) {
            $office['is_active'] = true;
            $office['created_at'] = $now;
            $office['updated_at'] = $now;
        }

        DB::connection('sqlsrv')->table('offices')->insert($offices);

        $this->command->info('Successfully seeded ' . count($offices) . ' offices into the database.');
    }
}
