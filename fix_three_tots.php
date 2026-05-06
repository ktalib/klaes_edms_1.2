<?php
/**
 * Fix 3 TOTs with wrong OPs:
 *
 * RES-2026-2112 (prop_id: 80000839): TOT Party1=INUWA SALEH, OP has ISAH BALA/Plot 98
 * RES-2026-2111 (prop_id: 69578851): TOT Party1=ALHAJI AMADU YAKUBU/Plot 248, OP has ABBA ALIYU IDRIS/Plot 8548
 * RES-2026-2109 (prop_id: 69575565): TOT Party1=M RABIU ABDULLAHI KORE/Plot 255, OP has NUSAIBA AHMAD/Plot 1187B
 *
 * Pattern: each TOT's prop_id belongs to an unrelated OP in instrument_capture.
 * Fix: isolate each wrong OP, create a correct OP matching the TOT's party1/plot data.
 */
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\PropertyIdAllocationService;

$db = DB::connection('sqlsrv');
$service = app()->make(PropertyIdAllocationService::class);

$cases = [
    [
        'fileno'       => 'RES-2026-2112',
        'tot_id'       => null, // find by mlsFNo
        'wrong_op_ic'  => 16291,
        'tot_prop_id'  => '80000839',
        'party_2'      => 'INUWA SALEH',
        'plot'         => null,
        'district'     => null,
        'description'  => null,
        'tp_no'        => null,
        'op_type'      => 'OP Resettlement',
        'op_serial'    => '102',
        'land_use'     => 'RESIDENTIAL',
        'temp_fileno'  => 'TEMP-36854',
    ],
    [
        'fileno'       => 'RES-2026-2111',
        'tot_id'       => 139737,
        'wrong_op_ic'  => 15594,
        'tot_prop_id'  => '69578851',
        'party_2'      => 'ALHAJI AMADU YAKUBU',
        'plot'         => '248',
        'district'     => 'KUYAN TA INNA',
        'description'  => 'Plot 248, KUYAN TA INNA, Kumbotso, Kano',
        'tp_no'        => 'TP/K/338F',
        'op_type'      => 'OP Resettlement',
        'op_serial'    => '283',
        'land_use'     => 'RESIDENTIAL',
        'temp_fileno'  => 'TEMP-36846',
    ],
    [
        'fileno'       => 'RES-2026-2109',
        'tot_id'       => 139730,
        'wrong_op_ic'  => 5341,
        'tot_prop_id'  => '69575565',
        'party_2'      => 'M RABIU ABDULLAHI KORE',
        'plot'         => '255',
        'district'     => 'NAIBAWA',
        'description'  => 'Plot 255, NAIBAWA, Kumbotso, Kano',
        'tp_no'        => 'TP/UDB/98',
        'op_type'      => 'OP Resettlement',
        'op_serial'    => '1628',
        'land_use'     => 'RESIDENTIAL',
        'temp_fileno'  => 'TEMP-36816',
    ],
];

foreach ($cases as $case) {
    echo "\n=== Fixing TOT: {$case['fileno']} ===\n";

    // Find TOT if id not provided
    if (!$case['tot_id']) {
        $tot = $db->table('pra')->where('mlsFNo', $case['fileno'])->first();
        if (!$tot) {
            echo "  TOT not found!\n";
            continue;
        }
        $case['tot_id'] = $tot->id;
    }

    // 1. Isolate the wrong OP - give it a new prop_id
    $newSeqId = $db->table('temp_fileno_sequence')->insertGetId([
        'created_at' => now(),
        'updated_at' => now(),
        'is_used' => 1
    ]);
    $isolatedTempFileNo = 'TEMP-' . str_pad((string)$newSeqId, 7, '0', STR_PAD_LEFT);
    $isolatedPropId = $service->allocateOrRetrievePropId(null, $isolatedTempFileNo);

    $db->table('instrument_capture')->where('id', $case['wrong_op_ic'])->update([
        'temp_fileno' => $isolatedTempFileNo,
        'prop_id'     => $isolatedPropId,
        'updated_at'  => now()
    ]);
    echo "  Isolated wrong OP (ID: {$case['wrong_op_ic']}) to prop_id $isolatedPropId\n";

    // 2. Create the correct OP for this TOT's lineage
    $newParentId = $service->allocateOrRetrievePropId(null, $case['temp_fileno'] . '-OP');
    $icId = $db->table('instrument_capture')->insertGetId([
        'instrument_type'     => 'Occupancy Permit (OP)',
        'op_type'             => $case['op_type'],
        'op_serial_number'    => $case['op_serial'],
        'registration_number' => '0/0/0',
        'volume_no'           => '0',
        'page_no'             => '0',
        'serial_no'           => '0',
        'party_1_name'        => 'KANO STATE GOVERNMENT',
        'party_2_name'        => $case['party_2'],
        'plot_number'         => $case['plot'],
        'district'            => $case['district'],
        'property_description'=> $case['description'],
        'property_location'   => $case['description'],
        'land_use'            => $case['land_use'],
        'land_use_id'         => '1',
        'purpose'             => $case['land_use'],
        'tp_no'               => $case['tp_no'],
        'temp_fileno'         => $case['temp_fileno'],
        'prop_id'             => $newParentId,
        'created_at'          => now(),
        'updated_at'          => now(),
        'created_by'          => '101524',
        'is_deed_registered'  => 1
    ]);
    echo "  Created correct OP (IC ID: $icId, prop_id: $newParentId)\n";

    // 3. Update the TOT to link to the new parent
    $db->table('pra')->where('id', $case['tot_id'])->update([
        'parent_prop_id' => $newParentId,
        'updated_at'     => now()
    ]);
    echo "  Linked TOT (pra id: {$case['tot_id']}) to parent_prop_id $newParentId\n";
}

echo "\nAll fixes complete.\n";
