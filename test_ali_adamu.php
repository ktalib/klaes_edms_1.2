<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Find PRAs involving ALI ADAMU
echo "=== PRA records with ALI ADAMU ===\n";
$pras = DB::connection('sqlsrv')->table('pra')
    ->where(function ($q) {
        $q->where('party_1', 'like', '%ALI ADAMU%')
          ->orWhere('party_2', 'like', '%ALI ADAMU%')
          ->orWhere('Grantor', 'like', '%ALI ADAMU%')
          ->orWhere('Grantee', 'like', '%ALI ADAMU%');
    })
    ->orderBy('id')
    ->get();
foreach ($pras as $r) {
    echo "  id={$r->id} | prop_id=" . ($r->prop_id ?? 'NULL')
        . " | temp=" . ($r->temp_fileno ?? 'NULL')
        . " | p1=" . ($r->party_1 ?? 'NULL')
        . " | p2=" . ($r->party_2 ?? 'NULL')
        . " | op_type=" . ($r->op_type ?? 'NULL')
        . " | instrument=" . ($r->instrument_type ?? 'NULL')
        . " | mlsFNo=" . ($r->mlsFNo ?? 'NULL')
        . " | source_pra_id=" . ($r->source_pra_id ?? 'NULL')
        . " | plot=" . ($r->plot_no ?? 'NULL')
        . " | tp=" . ($r->tp_no ?? 'NULL')
        . "\n";
}

// Find PRAs for TEMP-11670
echo "\n=== PRA records with TEMP-11670 ===\n";
$pras2 = DB::connection('sqlsrv')->table('pra')
    ->where('temp_fileno', 'TEMP-11670')
    ->orderBy('id')
    ->get();
foreach ($pras2 as $r) {
    echo "  id={$r->id} | prop_id=" . ($r->prop_id ?? 'NULL')
        . " | p1=" . ($r->party_1 ?? 'NULL')
        . " | p2=" . ($r->party_2 ?? 'NULL')
        . " | op_type=" . ($r->op_type ?? 'NULL')
        . " | instrument=" . ($r->instrument_type ?? 'NULL')
        . "\n";
}

// Check mls_file_no records with ALI ADAMU
echo "\n=== mls_file_no records with ALI ADAMU ===\n";
$mls = DB::connection('sqlsrv')->table('mls_file_no')
    ->where('file_name', 'like', '%ALI ADAMU%')
    ->where('is_deleted', 0)
    ->orderBy('id')
    ->get();
foreach ($mls as $r) {
    echo "  id={$r->id} | full={$r->full_file_number} | name={$r->file_name}"
        . " | source=" . ($r->source ?? 'NULL')
        . " | sub=" . ($r->sub_source ?? 'NULL')
        . " | pra_id=" . ($r->source_pra_id ?? 'NULL')
        . " | ic_id=" . ($r->source_instrument_capture_id ?? 'NULL')
        . "\n";
}
