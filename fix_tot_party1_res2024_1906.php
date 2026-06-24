<?php
/**
 * Fix wrong Party 1 on the Transfer of Title (OP) for RES-2024-1906.
 *
 * Problem (PROD): the ToT row (TEMP-75419) shows
 *     Party 1 = "Kano State Government"
 * That is the OP's grantor. For a "Transfer of Title (OP)" the transfer is
 * FROM the original allottee (the OP's Party 2 = "Lamash") TO the new owner
 * (Usman Nuhu Alfadarai). So Party 1 / Grantor must be the OP's allottee.
 *
 * This script DERIVES the correct value from the OP row (TEMP-75418) instead
 * of hardcoding it, prints before/after, and only touches party_1 / Grantor.
 *
 * Run on PROD:  php fix_tot_party1_res2024_1906.php
 */
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');

$propId       = 120250;
$opTempFileno = 'TEMP-75418'; // Occupancy Permit row
$totTempFileno = 'TEMP-75419'; // Transfer of Title row

echo "DB: " . $db->getDatabaseName() . "\n\n";

// 1. Locate the OP row -> source of the correct Party 1.
$op = $db->table('pra')
    ->where('prop_id', $propId)
    ->where('temp_fileno', $opTempFileno)
    ->first();

if (!$op) {
    echo "ABORT: OP row not found (prop_id=$propId, temp_fileno=$opTempFileno).\n";
    exit(1);
}

$correctParty1 = trim((string) $op->party_2);
if ($correctParty1 === '') {
    echo "ABORT: OP party_2 (allottee) is empty; cannot derive correct Party 1.\n";
    exit(1);
}

// 2. Locate the ToT row to fix.
$totRows = $db->table('pra')
    ->where('prop_id', $propId)
    ->where('temp_fileno', $totTempFileno)
    ->get();

if ($totRows->count() === 0) {
    echo "ABORT: ToT row not found (prop_id=$propId, temp_fileno=$totTempFileno).\n";
    exit(1);
}
if ($totRows->count() > 1) {
    echo "ABORT: expected 1 ToT row, found {$totRows->count()}. Inspect manually.\n";
    exit(1);
}

$tot = $totRows->first();

echo "OP  (id={$op->id})  party_2 / allottee : '{$op->party_2}'\n";
echo "ToT (id={$tot->id}) BEFORE -> party_1: '{$tot->party_1}' | Grantor: '{$tot->Grantor}' | party_2: '{$tot->party_2}'\n";

if (trim((string) $tot->party_1) === $correctParty1 && trim((string) $tot->Grantor) === $correctParty1) {
    echo "\nNo change needed: ToT Party 1 already = '{$correctParty1}'.\n";
    exit(0);
}

// 3. Apply the fix (only Party 1 / Grantor).
$db->table('pra')->where('id', $tot->id)->update([
    'party_1'    => $correctParty1,
    'Grantor'    => $correctParty1,
    'updated_at' => now(),
]);

$after = $db->table('pra')->where('id', $tot->id)->first();
echo "ToT (id={$tot->id}) AFTER  -> party_1: '{$after->party_1}' | Grantor: '{$after->Grantor}' | party_2: '{$after->party_2}'\n";
echo "\nDone. ToT Party 1 corrected to the OP allottee ('{$correctParty1}').\n";
