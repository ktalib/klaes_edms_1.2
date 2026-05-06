<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== instrument_capture for prop_id 68922, 68923, 68924 ===\n";
$rows = DB::connection('sqlsrv')->table('instrument_capture')
    ->whereIn('prop_id', ['68922','68923','68924'])
    ->orderBy('prop_id')
    ->get(['id','prop_id','temp_fileno','party_1_name','party_2_name','op_type']);
foreach ($rows as $r) {
    echo "ic.id={$r->id} | prop_id={$r->prop_id} | temp=" . ($r->temp_fileno ?? 'NULL')
        . " | p1=" . ($r->party_1_name ?? 'NULL')
        . " | p2=" . ($r->party_2_name ?? 'NULL')
        . " | op_type=" . ($r->op_type ?? 'NULL') . "\n";
}

echo "\n=== All PRA for prop_id 68922, 68923, 68924 ===\n";
$pras = DB::connection('sqlsrv')->table('pra')
    ->whereIn('prop_id', ['68922','68923','68924'])
    ->orderBy('id')
    ->get(['id','prop_id','temp_fileno','party_1','party_2','mlsFNo']);
foreach ($pras as $r) {
    echo "pra.id={$r->id} | prop_id={$r->prop_id} | temp=" . ($r->temp_fileno ?? 'NULL')
        . " | p1=" . ($r->party_1 ?? 'NULL')
        . " | p2=" . ($r->party_2 ?? 'NULL')
        . " | mlsFNo=" . ($r->mlsFNo ?? 'NULL') . "\n";
}
