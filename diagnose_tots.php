<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$tots = [
    'RES-2026-2112' => ['prop_id' => '80000839', 'temp_fileno' => 'TEMP-36854', 'op_serial' => '102',  'party_1' => 'INUWA SALEH',        'plot' => null,  'tp' => null],
    'RES-2026-2111' => ['prop_id' => '69578851', 'temp_fileno' => 'TEMP-36846', 'op_serial' => '283',  'party_1' => 'ALHAJI AMADU YAKUBU','plot' => '248', 'tp' => 'TP/K/338F'],
    'RES-2026-2109' => ['prop_id' => '69575565', 'temp_fileno' => 'TEMP-36816', 'op_serial' => '1628', 'party_1' => 'M RABIU ABDULLAHI KORE', 'plot' => '255', 'tp' => 'TP/UDB/98'],
];

foreach ($tots as $fileno => $tot) {
    echo "\n=== TOT: $fileno (prop_id: {$tot['prop_id']}) ===\n";

    // Check current OPs by prop_id
    $ops = DB::connection('sqlsrv')->table('instrument_capture')
        ->where('prop_id', $tot['prop_id'])
        ->where('instrument_type', 'Occupancy Permit (OP)')
        ->get();

    if ($ops->isEmpty()) {
        echo "  NO OP found in instrument_capture for prop_id {$tot['prop_id']}\n";
    } else {
        foreach ($ops as $op) {
            echo "  OP IC id:{$op->id} party2:{$op->party_2_name} plot:{$op->plot_number} serial:{$op->op_serial_number}\n";
        }
    }

    // Also check PRA for the OP
    $opPra = DB::connection('sqlsrv')->table('pra')
        ->where('prop_id', $tot['prop_id'])
        ->where('instrument_type', 'Occupancy Permit (OP)')
        ->get();

    foreach ($opPra as $op) {
        echo "  OP PRA id:{$op->id} party2:{$op->party_2} plot:{$op->plot_no} serial:{$op->op_serial_number}\n";
    }

    // Check by temp_fileno in instrument_capture
    $opByTemp = DB::connection('sqlsrv')->table('instrument_capture')
        ->where('temp_fileno', $tot['temp_fileno'])
        ->where('instrument_type', 'Occupancy Permit (OP)')
        ->get();

    foreach ($opByTemp as $op) {
        echo "  OP (by temp_fileno) IC id:{$op->id} prop_id:{$op->prop_id} party2:{$op->party_2_name} plot:{$op->plot_number}\n";
    }
}
