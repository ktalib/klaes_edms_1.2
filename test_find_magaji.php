<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Find the correct prop_id for MAGAJI AMINU from instrument_capture
echo "=== instrument_capture records for MAGAJI AMINU ===\n";
$icRows = DB::connection('sqlsrv')
    ->table('instrument_capture')
    ->where('party_2_name', 'LIKE', '%MAGAJI AMINU%')
    ->orderByDesc('id')
    ->limit(10)
    ->get();

foreach ($icRows as $r) {
    echo "ic.id={$r->id} | prop_id=" . ($r->prop_id ?? 'NULL')
        . " | temp_fileno=" . ($r->temp_fileno ?? 'NULL')
        . " | party_1=" . ($r->party_1_name ?? 'NULL')
        . " | party_2=" . ($r->party_2_name ?? 'NULL')
        . " | op_type=" . ($r->op_type ?? 'NULL')
        . "\n";
}

// Also check PRA for MAGAJI AMINU
echo "\n=== PRA records for MAGAJI AMINU ===\n";
$praRows = DB::connection('sqlsrv')
    ->table('pra')
    ->where('party_2', 'LIKE', '%MAGAJI AMINU%')
    ->orderByDesc('id')
    ->limit(10)
    ->get();

foreach ($praRows as $r) {
    echo "pra.id={$r->id} | prop_id=" . ($r->prop_id ?? 'NULL')
        . " | temp_fileno=" . ($r->temp_fileno ?? 'NULL')
        . " | party_1=" . ($r->party_1 ?? 'NULL')
        . " | party_2=" . ($r->party_2 ?? 'NULL')
        . " | mlsFNo=" . ($r->mlsFNo ?? 'NULL')
        . "\n";
}

// Check temp_fileno_register for MAGAJI AMINU's actual temp file
echo "\n=== temp_fileno_register where party matches MAGAJI AMINU ===\n";
$tfRows = DB::connection('sqlsrv')
    ->table('temp_fileno_register')
    ->where('FileName', 'LIKE', '%MAGAJI AMINU%')
    ->orderByDesc('id')
    ->limit(10)
    ->get();

foreach ($tfRows as $r) {
    echo "tf.id={$r->id} | prop_id=" . ($r->prop_id ?? 'NULL')
        . " | temp_fileno=" . ($r->temp_fileno ?? 'NULL')
        . " | FileName=" . ($r->FileName ?? 'NULL')
        . "\n";
}
