<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Delete the duplicate (77852), keep 77851
DB::connection('sqlsrv')->table('pra')->where('id', 77852)->delete();
echo "Deleted PRA 77852 (duplicate)\n";

// Verify chain
echo "\n=== PRA chain for prop_id=71682 ===\n";
$chain = DB::connection('sqlsrv')->table('pra')->where('prop_id', 71682)->orderBy('id')->get();
foreach ($chain as $r) {
    echo "  id={$r->id} | p1={$r->party_1} | p2={$r->party_2}"
        . " | instrument=" . ($r->instrument_type ?? 'NULL')
        . " | op_type=" . ($r->op_type ?? 'NULL')
        . " | source_pra_id=" . ($r->source_pra_id ?? 'NULL')
        . " | mlsFNo=" . ($r->mlsFNo ?? 'NULL')
        . "\n";
}
