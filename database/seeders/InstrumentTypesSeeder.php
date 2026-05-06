<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InstrumentTypesSeeder extends Seeder
{
    public function run()
    {
        $instrumentTypes = [
            [
                'InstrumentName' => 'Assignment',
                'Description' => 'Transfer of rights from one party to another',
                'IsActive' => 1
            ],
            [
                'InstrumentName' => 'Sublease',
                'Description' => 'A lease granted by a lessee to a third party',
                'IsActive' => 1
            ],
            [
                'InstrumentName' => 'Gift',
                'Description' => 'Transfer of property without consideration',
                'IsActive' => 1
            ],
            [
                'InstrumentName' => 'Mortgage',
                'Description' => 'Security interest in real property',
                'IsActive' => 1
            ],
            [
                'InstrumentName' => 'Lease',
                'Description' => 'Contractual arrangement for use of property',
                'IsActive' => 1
            ],
            [
                'InstrumentName' => 'Power of Attorney',
                'Description' => 'Legal authorization to act on behalf of another',
                'IsActive' => 1
            ],
            [
                'InstrumentName' => 'Deed of Assignment',
                'Description' => 'Formal document transferring property rights',
                'IsActive' => 1
            ],
            [
                'InstrumentName' => 'Certificate of Occupancy',
                'Description' => 'Document evidencing right to occupy land',
                'IsActive' => 1
            ],
            [
                'InstrumentName' => 'Right of Occupancy',
                'Description' => 'Statutory right to occupy customary land',
                'IsActive' => 1
            ],
            [
                'InstrumentName' => 'Court Order',
                'Description' => 'Judicial directive regarding property',
                'IsActive' => 1
            ]
        ];

        foreach ($instrumentTypes as $type) {
            DB::connection('sqlsrv')->table('InstrumentTypes')->insert($type);
        }
    }
}
