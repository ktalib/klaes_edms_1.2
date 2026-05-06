<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Check if there's a gap - what prop_ids exist around 68922-68924
echo "=== PRA records around prop_id 68920-68930 ===\n";
$rows = DB::connection('sqlsrv')->table('pra')
    ->whereBetween('prop_id', [68920, 68930])
    ->orderBy('prop_id')
    ->orderBy('id')
    ->get(['id','prop_id','temp_fileno','party_2']);
foreach ($rows as $r) {
    echo "pra.id={$r->id} | prop_id={$r->prop_id} | temp=" . ($r->temp_fileno ?? 'NULL')
        . " | p2=" . ($r->party_2 ?? 'NULL') . "\n";
}

// Find the max prop_id in PRA
echo "\n=== Max prop_id in PRA ===\n";
$max = DB::connection('sqlsrv')->table('pra')->max('prop_id');
echo "Max prop_id: {$max}\n";

// Find the max temp_fileno number
echo "\n=== Max temp_fileno in PRA ===\n";
$maxTemp = DB::connection('sqlsrv')->table('pra')
    ->whereNotNull('temp_fileno')
    ->selectRaw("MAX(TRY_CONVERT(int, REPLACE(temp_fileno, 'TEMP-', ''))) as max_num")
    ->value('max_num');
echo "Max temp number: {$maxTemp}\n";
