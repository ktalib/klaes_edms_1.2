<?php
/**
 * Fix wrong Party 2 on the Occupancy Permit (OP) for RES-2025-1780.
 *
 * Problem (PROD): the OP row (prop_id=63335, TEMP-88250) shows
 *     Party 2 / Grantee = "MUKTAR DANLAMI"
 * But Muktar Danlami is the NEW owner from the subsequent Transfer of Title,
 * not the original allottee. The OP allottee is "ABDULLAHI BATAYE" — already
 * correctly recorded as the child ToT's Party 1 / Grantor.
 *
 * OP lineage:
 *   Party 1 / Grantor = Kano State Government (left untouched)
 *   Party 2 / Grantee = original allottee  = ToT Party 1 / Grantor
 *
 * Derives the correct allottee from the child ToT row (linked via parent_prop_id)
 * instead of hardcoding, prints before/after, only touches party_2 / Grantee,
 * and is idempotent.
 *
 * Run:  php fix_op_party2_res2025_1780.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');

$opPropId     = 63335;
$opTempFileno = 'TEMP-88250';

echo "DB: " . $db->getDatabaseName() . "\n\n";

// 1. Locate the OP row to fix.
$opRows = $db->table('pra')
    ->where('prop_id', $opPropId)
    ->where('temp_fileno', $opTempFileno)
    ->where('instrument_type', 'LIKE', '%Occupancy Permit%')
    ->get();

if ($opRows->count() !== 1) {
    echo "ABORT: expected 1 OP row, found {$opRows->count()} (prop_id=$opPropId, temp_fileno=$opTempFileno).\n";
    exit(1);
}
$op = $opRows->first();

// 2. Locate the child ToT row = source of the correct allottee (its Party 1).
$totRows = $db->table('pra')
    ->where('parent_prop_id', (string) $opPropId) // varchar column — bind as string
    ->where('instrument_type', 'LIKE', '%Transfer%')
    ->orderBy('id')
    ->get();

if ($totRows->count() !== 1) {
    echo "ABORT: expected 1 child ToT row, found {$totRows->count()} (parent_prop_id=$opPropId).\n";
    exit(1);
}
$tot = $totRows->first();

$correctParty2 = trim((string) ($tot->party_1 ?: $tot->Grantor));
if ($correctParty2 === '') {
    echo "ABORT: ToT party_1/Grantor (allottee) is empty; cannot derive correct OP Party 2.\n";
    exit(1);
}

echo "ToT (id={$tot->id}) allottee (Party 1): '{$correctParty2}'\n";
echo "OP  (id={$op->id}) BEFORE -> party_1: '{$op->party_1}' | party_2: '{$op->party_2}' | Grantee: '{$op->Grantee}'\n";

if (strcasecmp(trim((string) $op->party_2), $correctParty2) === 0
    && strcasecmp(trim((string) $op->Grantee), $correctParty2) === 0) {
    echo "\nNo change needed: OP Party 2 already = '{$correctParty2}'.\n";
    exit(0);
}

// 3. Apply the fix (only Party 2 / Grantee).
$db->table('pra')->where('id', $op->id)->update([
    'party_2'    => $correctParty2,
    'Grantee'    => $correctParty2,
    'updated_at' => now(),
]);

$after = $db->table('pra')->where('id', $op->id)->first();
echo "OP  (id={$op->id}) AFTER  -> party_1: '{$after->party_1}' | party_2: '{$after->party_2}' | Grantee: '{$after->Grantee}'\n";
echo "\nDone. OP Party 2 corrected to the original allottee ('{$correctParty2}').\n";
