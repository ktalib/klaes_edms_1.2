<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== mls_file_no for RES-2026-1669 (id=12094) ===\n";
$mls = DB::connection('sqlsrv')->table('mls_file_no')->where('id', 12094)->first();
if ($mls) {
    foreach ((array) $mls as $k => $v) {
        echo "  {$k}: " . ($v ?? 'NULL') . "\n";
    }
} else {
    echo "  NOT FOUND\n";
}

// Get prop_id from mls record
$propId = $mls->prop_id ?? null;
$tempFileno = $mls->temp_fileno ?? null;

echo "\n=== PRA records for prop_id={$propId} ===\n";
if ($propId) {
    $pras = DB::connection('sqlsrv')->table('pra')
        ->where('prop_id', $propId)
        ->orderBy('id')
        ->get();
    foreach ($pras as $r) {
        echo "  pra.id={$r->id} | prop_id={$r->prop_id} | temp=" . ($r->temp_fileno ?? 'NULL')
            . " | p1=" . ($r->party_1 ?? 'NULL')
            . " | p2=" . ($r->party_2 ?? 'NULL')
            . " | mlsFNo=" . ($r->mlsFNo ?? 'NULL')
            . " | op_type=" . ($r->op_type ?? 'NULL')
            . " | land_use=" . ($r->land_use ?? 'NULL')
            . "\n";
    }
}

echo "\n=== PRA records for temp_fileno={$tempFileno} ===\n";
if ($tempFileno) {
    $pras2 = DB::connection('sqlsrv')->table('pra')
        ->where('temp_fileno', $tempFileno)
        ->orderBy('id')
        ->get();
    foreach ($pras2 as $r) {
        echo "  pra.id={$r->id} | prop_id={$r->prop_id} | temp=" . ($r->temp_fileno ?? 'NULL')
            . " | p1=" . ($r->party_1 ?? 'NULL')
            . " | p2=" . ($r->party_2 ?? 'NULL')
            . " | mlsFNo=" . ($r->mlsFNo ?? 'NULL')
            . " | op_type=" . ($r->op_type ?? 'NULL')
            . "\n";
    }
}

// Also check instrument_capture
echo "\n=== instrument_capture for prop_id={$propId} ===\n";
if ($propId) {
    $ics = DB::connection('sqlsrv')->table('instrument_capture')
        ->where('prop_id', $propId)
        ->orderBy('id')
        ->get(['id','prop_id','temp_fileno','mlsFNo','party_1_name','party_2_name','op_type','instrument_type']);
    foreach ($ics as $r) {
        echo "  ic.id={$r->id} | prop_id={$r->prop_id} | temp=" . ($r->temp_fileno ?? 'NULL')
            . " | mlsFNo=" . ($r->mlsFNo ?? 'NULL')
            . " | p1=" . ($r->party_1_name ?? 'NULL')
            . " | p2=" . ($r->party_2_name ?? 'NULL')
            . " | op_type=" . ($r->op_type ?? 'NULL')
            . " | instr_type=" . ($r->instrument_type ?? 'NULL')
            . "\n";
    }
}
