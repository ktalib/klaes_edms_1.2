<?php
/**
 * Correct the Transfer of Title (OP) parties for RES-2023-210.
 *
 * Lineage for a "Transfer of Title (OP)":
 *   Party 1 / Grantor = the OP allottee (OP row Party 2 = "KAMILU KABIRU")
 *   Party 2 / Grantee = the new owner ("INDO TIJJANI")
 *
 * The ToT row (prop_id=125576, TEMP-87431) wrongly showed
 *   Party 1 = "KANO STATE GOVERNMENT" (the OP grantor, not the allottee).
 *
 * An earlier patch mistakenly changed Party 2 to "KAMILU KABIRU"; this script
 * also restores Party 2 to the correct new owner "INDO TIJJANI".
 *
 * Party 1 is DERIVED from the OP row; Party 2 (new owner) is restored explicitly.
 * Prints before/after.  Run:  php fix_tot_party2_res2023_210.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');

$opPropId      = 61666;        // Occupancy Permit prop_id (= ToT parent_prop_id)
$opTempFileno  = 'TEMP-87430'; // Occupancy Permit row
$totPropId     = 125576;       // Transfer of Title prop_id
$totTempFileno = 'TEMP-87431'; // Transfer of Title row
$newOwner      = 'INDO TIJJANI'; // correct Party 2 (grantee / new owner)

echo "DB: " . $db->getDatabaseName() . "\n\n";

// 1. Locate the OP row -> source of the correct Party 1 (allottee).
$op = $db->table('pra')
    ->where('prop_id', $opPropId)
    ->where('temp_fileno', $opTempFileno)
    ->first();

if (!$op) {
    echo "ABORT: OP row not found (prop_id=$opPropId, temp_fileno=$opTempFileno).\n";
    exit(1);
}

$correctParty1 = trim((string) ($op->party_2 ?: $op->Grantee));
if ($correctParty1 === '') {
    echo "ABORT: OP party_2/Grantee (allottee) is empty; cannot derive correct Party 1.\n";
    exit(1);
}

// 2. Locate the ToT row to fix.
$totRows = $db->table('pra')
    ->where('prop_id', $totPropId)
    ->where('temp_fileno', $totTempFileno)
    ->get();

if ($totRows->count() === 0) {
    echo "ABORT: ToT row not found (prop_id=$totPropId, temp_fileno=$totTempFileno).\n";
    exit(1);
}
if ($totRows->count() > 1) {
    echo "ABORT: expected 1 ToT row, found {$totRows->count()}. Inspect manually.\n";
    exit(1);
}

$tot = $totRows->first();

echo "OP  (id={$op->id})  party_2 / allottee : '{$op->party_2}' | Grantee: '{$op->Grantee}'\n";
echo "ToT (id={$tot->id}) BEFORE -> party_1: '{$tot->party_1}' | Grantor: '{$tot->Grantor}' | party_2: '{$tot->party_2}' | Grantee: '{$tot->Grantee}'\n";

// 3. Apply the fix:
//    party_1 / Grantor = OP allottee (KAMILU KABIRU)
//    party_2 / Grantee = new owner   (INDO TIJJANI)
$db->table('pra')->where('id', $tot->id)->update([
    'party_1'    => $correctParty1,
    'Grantor'    => $correctParty1,
    'party_2'    => $newOwner,
    'Grantee'    => $newOwner,
    'updated_at' => now(),
]);

$after = $db->table('pra')->where('id', $tot->id)->first();
echo "ToT (id={$tot->id}) AFTER  -> party_1: '{$after->party_1}' | Grantor: '{$after->Grantor}' | party_2: '{$after->party_2}' | Grantee: '{$after->Grantee}'\n";
echo "\nDone. ToT Party 1 = OP allottee ('{$correctParty1}'); Party 2 = new owner ('{$newOwner}').\n";
