<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Check PRA 76196 (previously fixed)
echo "=== PRA record id=76196 ===\n";
$pra = DB::connection('sqlsrv')->table('pra')->where('id', 76196)->first();
if ($pra) {
    echo "  prop_id={$pra->prop_id} | temp={$pra->temp_fileno} | p1={$pra->party_1} | p2={$pra->party_2}\n";
    echo "  Grantor=" . ($pra->Grantor ?? 'NULL') . " | Grantee=" . ($pra->Grantee ?? 'NULL') . "\n";
    echo "  op_type=" . ($pra->op_type ?? 'NULL') . " | instrument=" . ($pra->instrument_type ?? 'NULL') . "\n";
    echo "  plot=" . ($pra->plot_no ?? 'NULL') . " | tp=" . ($pra->tp_no ?? 'NULL') . "\n";
    echo "  source_pra_id=" . ($pra->source_pra_id ?? 'NULL') . "\n";
}

// Wider search for ALI ADAMU with looser pattern  
echo "\n=== Wider search for 'ALI ADAM' in PRA ===\n";
$pras = DB::connection('sqlsrv')->table('pra')
    ->where(function ($q) {
        $q->where('party_1', 'like', '%ALI ADAM%')
          ->orWhere('party_2', 'like', '%ALI ADAM%')
          ->orWhere('Grantor', 'like', '%ALI ADAM%')
          ->orWhere('Grantee', 'like', '%ALI ADAM%')
          ->orWhere('Assignor', 'like', '%ALI ADAM%')
          ->orWhere('Assignee', 'like', '%ALI ADAM%');
    })
    ->orderBy('id')
    ->get();
echo "  Found " . count($pras) . " records\n";
foreach ($pras as $r) {
    echo "  id={$r->id} | prop_id=" . ($r->prop_id ?? 'NULL')
        . " | temp=" . ($r->temp_fileno ?? 'NULL')
        . " | p1=" . ($r->party_1 ?? 'NULL')
        . " | p2=" . ($r->party_2 ?? 'NULL')
        . " | Grantor=" . ($r->Grantor ?? 'NULL')
        . " | Grantee=" . ($r->Grantee ?? 'NULL')
        . " | Assignor=" . ($r->Assignor ?? 'NULL')
        . " | Assignee=" . ($r->Assignee ?? 'NULL')
        . " | op_type=" . ($r->op_type ?? 'NULL')
        . " | instrument=" . ($r->instrument_type ?? 'NULL')
        . "\n";
}

// Also check the chain of PRAs for the same plot/tp
echo "\n=== PRA records with plot 298 and TP/MLPP/KBT/256G ===\n";
$pras2 = DB::connection('sqlsrv')->table('pra')
    ->where('plot_no', '298')
    ->where('tp_no', 'TP/MLPP/KBT/256G')
    ->orderBy('id')
    ->get();
foreach ($pras2 as $r) {
    echo "  id={$r->id} | prop_id=" . ($r->prop_id ?? 'NULL')
        . " | temp=" . ($r->temp_fileno ?? 'NULL')
        . " | p1=" . ($r->party_1 ?? 'NULL')
        . " | p2=" . ($r->party_2 ?? 'NULL')
        . " | op_type=" . ($r->op_type ?? 'NULL')
        . " | instrument=" . ($r->instrument_type ?? 'NULL')
        . "\n";
}

// Also check instrument_capture
echo "\n=== instrument_capture with ALI ADAM ===\n";
$ics = DB::connection('sqlsrv')->table('instrument_capture')
    ->where(function ($q) {
        $q->where('party_1_name', 'like', '%ALI ADAM%')
          ->orWhere('party_2_name', 'like', '%ALI ADAM%');
    })
    ->orderBy('id')
    ->get(['id','prop_id','temp_fileno','mlsFNo','party_1_name','party_2_name','op_type','instrument_type']);
foreach ($ics as $r) {
    echo "  ic.id={$r->id} | prop_id=" . ($r->prop_id ?? 'NULL')
        . " | temp=" . ($r->temp_fileno ?? 'NULL')
        . " | mlsFNo=" . ($r->mlsFNo ?? 'NULL')
        . " | p1=" . ($r->party_1_name ?? 'NULL')
        . " | p2=" . ($r->party_2_name ?? 'NULL')
        . " | op_type=" . ($r->op_type ?? 'NULL')
        . " | instr=" . ($r->instrument_type ?? 'NULL')
        . "\n";
}
