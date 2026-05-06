<?php

namespace Database\Seeders;

use App\Models\Payroll\WorkstationPaymentStructure;
use App\Models\Payroll\WorkstationPaymentStructureAlias;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WorkstationPaymentStructureSeeder extends Seeder
{
    public function run(): void
    {
        $structures = [
            [
                'code' => 'monitors',
                'role_name' => 'Monitors',
                'work_days' => null,
                'base_salary' => 90000,
                'aliases' => [
                    'monitors', 'monitorsteam', 'monitoring', 'monitors7daysfullday', 'monitors7days', 'monitorsfullday',
                ],
            ],
            [
                'code' => 'scb',
                'role_name' => 'SCB',
                'work_days' => null,
                'base_salary' => 70000,
                'aliases' => [
                    'verificationteam', 'verification', 'scbteam', 'scbstaff',
                ],
            ],
            [
                'code' => 'indexers_5',
                'role_name' => 'Indexers',
                'work_days' => 5,
                'base_salary' => 60000,
                'aliases' => [
                    '5workdaysfullday', 'indexers5days', 'indexer5days', '5daysindexing',
                    '6workdaysfullday', 'sixworkdaysfullday', '6daysfullday', '6workdays', '6days',
                ],
            ],
            [
                'code' => 'indexers_7',
                'role_name' => 'Indexers',
                'work_days' => 7,
                'base_salary' => 70000,
                'aliases' => [
                    '7workdaysfullday', 'sevenworkdaysfullday', '7daysfullday', '7workdays',
                    '7workdaysindexers', 'indexers7days', 'sevenworkdaysindexers',
                ],
            ],
            [
                'code' => 'pra_pic_5',
                'role_name' => 'PRA/PIC',
                'work_days' => 5,
                'base_salary' => 50000,
                'aliases' => [
                    'pra', 'propertyrecordsassistant', 'propertyrecordsassistantpra', 'pic', 'publicinformationcenter', 'publicinformationcenterpic',
                    'dailyshiftstaff', 'dailyshift', 'shiftstaff',
                ],
            ],
            [
                'code' => 'pra_pic_7',
                'role_name' => 'PRA/PIC',
                'work_days' => 7,
                'base_salary' => 60000,
                'aliases' => [
                    'pra7days', 'pic7days', 'praweek', 'picweek',
                ],
            ],
            [
                'code' => 'blind_scanning_5',
                'role_name' => 'Blind Scanning/File Archiving',
                'work_days' => 5,
                'base_salary' => 25000,
                'aliases' => [
                    '4workingdaysshift', 'fourworkingdaysshift', 'weekdayonlyshift', '4workdays', '4daysshift',
                ],
            ],
            [
                'code' => 'blind_scanning_7',
                'role_name' => 'Blind Scanning/File Archiving',
                'work_days' => 7,
                'base_salary' => 50000,
                'aliases' => [
                    '6workdaysblindscanningshift', 'blindscanning', 'scanningblindshift', 'blindscanningshift', '6workdaysblindscanning',
                ],
            ],
            [
                'code' => 'blind_scanning_2',
                'role_name' => 'Blind Scanning',
                'work_days' => 2,
                'base_salary' => 12500,
                'aliases' => [
                    'weekendonstaff', 'weekendstaff', 'weekendonlystaff', 'weekendonly', 'weekend',
                ],
            ],
            [
                'code' => 'file_transfer_7',
                'role_name' => 'File Transfer',
                'work_days' => 7,
                'base_salary' => 50000,
                'aliases' => [
                    'filetransfer', 'filetransfer7days',
                ],
            ],
            [
                'code' => 'file_tracking_5',
                'role_name' => 'File Tracking/Front Desk',
                'work_days' => 5,
                'base_salary' => 50000,
                'aliases' => [
                    'filetracking', 'frontdesk', 'frontdesk5days',
                ],
            ],
        ];

        foreach ($structures as $structure) {
            $code = $this->normalize($structure['code']);
            $model = WorkstationPaymentStructure::query()->updateOrCreate(
                [
                    'code' => $code,
                    'work_days' => $structure['work_days'],
                    'effective_from' => $structure['effective_from'] ?? null,
                ],
                [
                    'role_name' => $structure['role_name'],
                    'base_salary' => $structure['base_salary'],
                    'effective_to' => $structure['effective_to'] ?? null,
                ]
            );

            $aliases = $structure['aliases'] ?? [];
            foreach ($aliases as $alias) {
                $normalized = $this->normalize($alias);
                WorkstationPaymentStructureAlias::query()->updateOrCreate(
                    ['normalized_alias' => $normalized],
                    [
                        'structure_id' => $model->id,
                        'alias' => $alias,
                    ]
                );
            }
        }
    }

    protected function normalize(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', '')
            ->value();
    }
}
