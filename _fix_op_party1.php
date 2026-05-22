<?php
/**
 * _fix_op_party1.php
 * Corrects party_1_name on the two new OP rows created by _fix_op_detach.php.
 * The workflow requires: OP Party 1 = Kano State Government, Party 2 = applicant.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
$db = DB::connection('sqlsrv');

$fixes = [
    ['id' => 17667, 'prop_id' => 69579486, 'tot_mls' => 'RES-2026-2220'],
    ['id' => 17668, 'prop_id' => 69574082, 'tot_mls' => 'RES-2026-2219'],
];

foreach ($fixes as $fix) {
    $row = $db->table('instrument_capture')
        ->where('id', $fix['id'])
        ->first(['id', 'prop_id', 'party_1_name', 'party_2_name', 'instrument_type']);

    if (!$row) {
        echo "[SKIP] instrument_capture.id={$fix['id']} not found\n";
        continue;
    }

    echo "Before: id={$row->id}  party_1_name={$row->party_1_name}  party_2_name={$row->party_2_name}\n";

    $db->table('instrument_capture')
        ->where('id', $fix['id'])
        ->update(['party_1_name' => 'Kano State Government', 'updated_at' => now()]);

    $updated = $db->table('instrument_capture')
        ->where('id', $fix['id'])
        ->first(['id', 'party_1_name', 'party_2_name']);

    echo "After : id={$updated->id}  party_1_name={$updated->party_1_name}  party_2_name={$updated->party_2_name}\n\n";
}

echo "Done.\n";
