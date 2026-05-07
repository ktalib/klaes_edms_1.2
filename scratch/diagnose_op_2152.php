<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$targetMls = 'RES-2026-2152';
$targetTemp = 'TEMP-38531';

echo "--- START DIAGNOSIS FOR $targetMls / $targetTemp ---\n\n";

// 1. Find Prop ID from Master
echo "[1] PropID_Master entries for $targetMls or $targetTemp:\n";
$master = DB::connection('sqlsrv')->table('PropID_Master')
    ->where('mlsFNo', $targetMls)
    ->orWhere('temp_fileno', $targetTemp)
    ->get();
print_r($master->toArray());

$propIds = $master->pluck('prop_id')->unique()->all();

if (empty($propIds)) {
    echo "No Prop IDs found in Master. Searching PRA/IC...\n";
    $praPropIds = DB::connection('sqlsrv')->table('pra')->where('mlsFNo', $targetMls)->orWhere('temp_fileno', $targetTemp)->pluck('prop_id')->unique();
    $icPropIds = DB::connection('sqlsrv')->table('instrument_capture')->where('mlsFNo', $targetMls)->orWhere('temp_fileno', $targetTemp)->pluck('prop_id')->unique();
    $propIds = $praPropIds->merge($icPropIds)->unique()->all();
}

echo "\nResolved Prop IDs: " . implode(', ', $propIds) . "\n";

foreach ($propIds as $propId) {
    echo "\n--- INVESTIGATING PROP_ID $propId ---\n";
    
    // Check pra table
    echo "\n[PRA records]:\n";
    $pra = DB::connection('sqlsrv')->table('pra')->where('prop_id', $propId)->get(['id', 'prop_id', 'temp_fileno', 'mlsFNo', 'party_1', 'party_2', 'instrument_type', 'plot_no', 'is_merger_op', 'merger_group_id', 'parent_prop_id']);
    print_r($pra->toArray());

    // Check instrument_capture table
    echo "\n[Instrument Capture records]:\n";
    $ic = DB::connection('sqlsrv')->table('instrument_capture')->where('prop_id', $propId)->get(['id', 'prop_id', 'temp_fileno', 'mlsFNo', 'party_1_name', 'party_2_name', 'instrument_type', 'plot_number', 'op_serial_number']);
    print_r($ic->toArray());
}

echo "\n--- END DIAGNOSIS ---\n";
