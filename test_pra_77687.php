<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== PRA record id=77687 ===\n";
$pra = DB::connection('sqlsrv')->table('pra')->where('id', 77687)->first();
if ($pra) {
    foreach ((array) $pra as $k => $v) {
        echo "  {$k}: " . ($v ?? 'NULL') . "\n";
    }
    $propId = $pra->prop_id ?? null;
    echo "\n=== All PRA for prop_id={$propId} ===\n";
    if ($propId) {
        $all = DB::connection('sqlsrv')->table('pra')->where('prop_id', $propId)->orderBy('id')->get();
        foreach ($all as $r) {
            echo "  id={$r->id} | p1=" . ($r->party_1 ?? 'NULL')
                . " | p2=" . ($r->party_2 ?? 'NULL')
                . " | op_type=" . ($r->op_type ?? 'NULL')
                . " | land_use=" . ($r->land_use ?? 'NULL')
                . " | temp=" . ($r->temp_fileno ?? 'NULL')
                . " | mlsFNo=" . ($r->mlsFNo ?? 'NULL')
                . "\n";
        }
    }
} else {
    echo "  NOT FOUND\n";
}

// Also check mls_file_no columns
echo "\n=== mls_file_no columns ===\n";
$cols = DB::connection('sqlsrv')->select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'mls_file_no' ORDER BY ORDINAL_POSITION");
foreach ($cols as $c) {
    echo "  " . $c->COLUMN_NAME . "\n";
}
