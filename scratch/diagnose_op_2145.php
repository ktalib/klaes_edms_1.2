<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$targetMls = 'RES-2026-2145';
$targetTemp = 'TEMP-38329';
$propId = '58049';

echo "--- START DIAGNOSIS FOR $targetMls / $targetTemp (Prop ID $propId) ---\n\n";

// Check PropID_Master
echo "[1] PropID_Master entry for $propId:\n";
$master = DB::connection('sqlsrv')->table('PropID_Master')->where('prop_id', $propId)->get();
print_r($master->toArray());

// Check pra table
echo "\n[2] PRA records for $propId:\n";
$pra = DB::connection('sqlsrv')->table('pra')->where('prop_id', $propId)->get(['id', 'prop_id', 'temp_fileno', 'mlsFNo', 'party_1', 'party_2', 'instrument_type', 'plot_no', 'is_merger_op', 'merger_group_id', 'parent_prop_id']);
print_r($pra->toArray());

// Check instrument_capture table
echo "\n[3] Instrument Capture records for $propId:\n";
$ic = DB::connection('sqlsrv')->table('instrument_capture')->where('prop_id', $propId)->get(['id', 'prop_id', 'temp_fileno', 'mlsFNo', 'party_1_name', 'party_2_name', 'instrument_type', 'plot_number', 'op_serial_number']);
print_r($ic->toArray());

echo "\n--- END DIAGNOSIS ---\n";
