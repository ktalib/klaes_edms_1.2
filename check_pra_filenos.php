<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

$fileNos = [
    'RES-2026-3000','TEMP-06044',
    'RES-2025-500','TEMP-14495',
    'RES-2025-6000','TEMP-14512',
    'AG-2026-4','TEMP-14504',
    'AG-2026-5','TEMP-14515',
];

foreach ($fileNos as $fn) {
    $rows = DB::connection('sqlsrv')->table('pra')
        ->where('fileno', $fn)
        ->orWhere('temp_fileno', $fn)
        ->orWhere('mlsFNo', $fn)
        ->get();

    echo "=== {$fn} === (" . count($rows) . " rows)\n";
    foreach ($rows as $r) {
        echo "  id={$r->id} | fileno={$r->fileno} | temp_fileno=" . ($r->temp_fileno ?? '') 
            . " | mlsFNo=" . ($r->mlsFNo ?? '') 
            . " | txn_type=" . ($r->transaction_type ?? '') 
            . " | party_1=" . ($r->party_1 ?? '') 
            . " | party_2=" . ($r->party_2 ?? '') 
            . " | Grantor=" . ($r->Grantor ?? '') 
            . " | Grantee=" . ($r->Grantee ?? '') 
            . " | txn_date=" . ($r->transaction_date ?? '') 
            . " | source=" . ($r->source ?? '') 
            . " | prop_id=" . ($r->prop_id ?? '') 
            . "\n";
    }
    echo "\n";
}
