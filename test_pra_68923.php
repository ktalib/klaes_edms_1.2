<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== PRA records for prop_id = 68923 ===\n";
$rows = DB::connection('sqlsrv')
    ->table('pra')
    ->where('prop_id', '68923')
    ->orderBy('id')
    ->get();

foreach ($rows as $r) {
    echo "id={$r->id} | prop_id={$r->prop_id} | temp_fileno=" . ($r->temp_fileno ?? 'NULL')
        . " | party_1=" . ($r->party_1 ?? 'NULL')
        . " | party_2=" . ($r->party_2 ?? 'NULL')
        . " | file_no=" . ($r->file_no ?? 'NULL')
        . " | mlsFNo=" . ($r->mlsFNo ?? 'NULL')
        . "\n";
}

echo "\n=== PRA record id = 76196 ===\n";
$rec = DB::connection('sqlsrv')
    ->table('pra')
    ->where('id', 76196)
    ->first();

if ($rec) {
    echo "id={$rec->id} | prop_id={$rec->prop_id} | temp_fileno=" . ($rec->temp_fileno ?? 'NULL')
        . " | party_1=" . ($rec->party_1 ?? 'NULL')
        . " | party_2=" . ($rec->party_2 ?? 'NULL')
        . " | file_no=" . ($rec->file_no ?? 'NULL')
        . " | mlsFNo=" . ($rec->mlsFNo ?? 'NULL')
        . "\n";

    // Check if this temp_fileno is duplicated
    if ($rec->temp_fileno) {
        echo "\n=== All PRA records with temp_fileno = {$rec->temp_fileno} ===\n";
        $dupes = DB::connection('sqlsrv')
            ->table('pra')
            ->where('temp_fileno', $rec->temp_fileno)
            ->orderBy('id')
            ->get();
        foreach ($dupes as $d) {
            echo "id={$d->id} | prop_id={$d->prop_id} | temp_fileno={$d->temp_fileno}"
                . " | party_1=" . ($d->party_1 ?? 'NULL')
                . " | party_2=" . ($d->party_2 ?? 'NULL')
                . "\n";
        }
    }
} else {
    echo "Record id=76196 not found.\n";
}
