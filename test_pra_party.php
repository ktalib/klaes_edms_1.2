<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::connection('sqlsrv')
    ->table('pra')
    ->where('prop_id', '71775')
    ->get();

foreach ($rows as $r) {
    echo "=== PRA record (id={$r->id}, prop_id={$r->prop_id}) ===\n";
    echo "party_1: " . ($r->party_1 ?? 'NULL') . "\n";
    echo "party_2: " . ($r->party_2 ?? 'NULL') . "\n";
    echo "file_no: " . ($r->file_no ?? 'NULL') . "\n";
    echo "land_use: " . ($r->land_use ?? 'NULL') . "\n";
    echo "plot_no: " . ($r->plot_no ?? 'NULL') . "\n";
    echo "location: " . ($r->location ?? $r->property_description ?? 'NULL') . "\n";
    echo "---\n";
}

// Also check instrument_capture for same prop_id
$ic = DB::connection('sqlsrv')
    ->table('instrument_capture')
    ->where('prop_id', '71775')
    ->get();

foreach ($ic as $r) {
    echo "=== instrument_capture (id={$r->id}, prop_id={$r->prop_id}) ===\n";
    echo "party_1_name: " . ($r->party_1_name ?? 'NULL') . "\n";
    echo "party_2_name: " . ($r->party_2_name ?? 'NULL') . "\n";
    echo "op_type: " . ($r->op_type ?? 'NULL') . "\n";
    echo "---\n";
}
