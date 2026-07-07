<?php
/**
 * Fix wrong Party 1 on the Transfer of Title (OP) for RES-2024-6377.
 *
 * Problem (PROD): the ToT row (prop_id=126701, TEMP-88257) shows
 *     Party 1 / Grantor = "KANO STATE GOVERNMENT"
 * That is the OP's grantor. For a "Transfer of Title (OP)" the transfer is
 * FROM the original allottee (the OP's Party 2 = "MUKHTAR TIJJANII YOLA") TO the
 * new owner ("SANI IBRAHIM SUNUSI"). So Party 1 / Grantor must be the OP allottee.
 *
 * Derives the correct value from the OP row (located via the ToT's
 * parent_prop_id) instead of hardcoding, prints before/after, only touches
 * party_1 / Grantor, and is idempotent.
 *
 * Run:  php fix_tot_party1_res2024_6377.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');

$totPropId     = 126701;
$totTempFileno = 'TEMP-88257';

echo "DB: " . $db->getDatabaseName() . "\n\n";

// 1. Locate the ToT row to fix.
$totRows = $db->table('pra')
    ->where('prop_id', $totPropId)
    ->where('temp_fileno', $totTempFileno)
    ->where('instrument_type', 'LIKE', '%Transfer%')
    ->get();

if ($totRows->count() !== 1) {
    echo "ABORT: expected 1 ToT row, found {$totRows->count()} (prop_id=$totPropId, temp_fileno=$totTempFileno).\n";
    exit(1);
}
$tot = $totRows->first();

if (empty($tot->parent_prop_id)) {
    echo "ABORT: ToT has no parent_prop_id to locate the OP.\n";
    exit(1);
}

// 2. Locate the OP row (source of the correct Party 1 = allottee).
$op = $db->table('pra')
    ->where('prop_id', $tot->parent_prop_id)
    ->where('instrument_type', 'LIKE', '%Occupancy Permit%')
    ->orderBy('id')
    ->first();

if (!$op) {
    echo "ABORT: OP row not found (prop_id={$tot->parent_prop_id}).\n";
    exit(1);
}

$correctParty1 = trim((string) ($op->party_2 ?: $op->Grantee));
if ($correctParty1 === '') {
    echo "ABORT: OP party_2/Grantee (allottee) is empty; cannot derive correct Party 1.\n";
    exit(1);
}

echo "OP  (id={$op->id})  allottee: '{$correctParty1}'\n";
echo "ToT (id={$tot->id}) BEFORE -> party_1: '{$tot->party_1}' | Grantor: '{$tot->Grantor}' | party_2: '{$tot->party_2}'\n";

if (strcasecmp(trim((string) $tot->party_1), $correctParty1) === 0
    && strcasecmp(trim((string) $tot->Grantor), $correctParty1) === 0) {
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
