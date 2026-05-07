<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$propId = '58049';
$targetTempFile = 'TEMP-38329';

echo "--- START DIAGNOSIS FOR PROP_ID $propId ---\n\n";

// 1. Check PropID_Master
echo "[1] PropID_Master entries for $propId:\n";
$master = DB::connection('sqlsrv')->table('PropID_Master')->where('prop_id', $propId)->get();
print_r($master->toArray());

// 2. Check pra table
echo "\n[2] PRA records for $propId:\n";
$pra = DB::connection('sqlsrv')->table('pra')->where('prop_id', $propId)->get();
print_r($pra->toArray());

// 3. Check instrument_capture table
echo "\n[3] Instrument Capture records for $propId:\n";
$ic = DB::connection('sqlsrv')->table('instrument_capture')->where('prop_id', $propId)->get();
print_r($ic->toArray());

// 4. Check for temp_fileno overlaps
echo "\n[4] Records with temp_fileno $targetTempFile:\n";
$icOverlap = DB::connection('sqlsrv')->table('instrument_capture')->where('temp_fileno', $targetTempFile)->get(['id', 'prop_id', 'party_1_name', 'party_2_name', 'instrument_type']);
$praOverlap = DB::connection('sqlsrv')->table('pra')->where('temp_fileno', $targetTempFile)->get(['id', 'prop_id', 'party_1', 'party_2', 'instrument_type', 'merger_group_id', 'is_merger_op']);
echo "IC Overlaps:\n"; print_r($icOverlap->toArray());
echo "PRA Overlaps:\n"; print_r($praOverlap->toArray());

// 5. Investigate Merger Group
$mergerGroupId = $pra->pluck('merger_group_id')->filter()->unique()->first();
if ($mergerGroupId) {
    echo "\n[5] Investigating Merger Group: $mergerGroupId\n";
    $groupRows = DB::connection('sqlsrv')->table('pra')->where('merger_group_id', $mergerGroupId)->get(['id', 'prop_id', 'temp_fileno', 'mlsFNo', 'party_1', 'party_2', 'instrument_type', 'is_merger_op']);
    print_r($groupRows->toArray());
} else {
    echo "\n[5] No merger_group_id found for this prop_id in PRA.\n";
}

// 6. Check Sequence
echo "\n[6] Current Temp Fileno Sequence:\n";
$seq = DB::connection('sqlsrv')->table('temp_fileno_sequence')->orderByDesc('id')->first();
print_r($seq);

echo "\n--- END DIAGNOSIS ---\n";
