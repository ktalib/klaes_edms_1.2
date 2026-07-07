<?php
/**
 * Fix wrong Party 2 on Occupancy Permit (OP) rows.
 *
 * Corruption pattern: the OP's Party 2 / Grantee was overwritten with the NEW
 * owner from the subsequent Transfer of Title, instead of the original allottee.
 *
 * OP lineage:
 *   Party 1 / Grantor = Kano State Government   (left untouched)
 *   Party 2 / Grantee = original allottee = child ToT Party 1 / Grantor
 *
 * The correct allottee is derived from the child ToT row (linked via
 * parent_prop_id) — never hardcoded. Only party_2 / Grantee is touched.
 * Prints before/after and is idempotent.
 *
 * Run:  php fix_op_party2_batch.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');

// OP rows to correct, keyed by OP prop_id => OP temp_fileno.
$targets = [
    63333 => 'TEMP-88246', // RES-2025-1778  (allottee: Khadijah Haruna)     [done]
    63332 => 'TEMP-88248', // RES-2025-1777  (allottee: Abubakar Sadiq Kwa)   [done]
    63334 => 'TEMP-60718', // RES-2025-1779  (allottee: Sa'Aatu M. Yusuf)      [done]
    // --- Group A audit batch (allottee derived live from child ToT Party 1) ---
    63833 => 'TEMP-35089', // RES-2025-4545  (allottee: Binta Nuhu Bichi)
    63944 => 'TEMP-88237', // RES-2025-580   (allottee: Rukayya Isyaku)
    63326 => 'TEMP-60716', // RES-2025-1727  (allottee: Dankada Farm Ltd)
    63249 => 'TEMP-61286', // RES-2025-1146  (allottee: Alhaji Haruna Garba)
    63879 => 'TEMP-34839', // RES-2025-4851  (allottee: Auwalu Sadi)
    5640  => 'TEMP-35128', // COM-2025-693   (allottee: Hajiya Hafsatu Salihu)
    63705 => 'TEMP-34860', // RES-2025-3433  (allottee: Malan Tasiu Muhammad Jogana)
    64023 => 'TEMP-74457', // RES-2025-679   (allottee: Bala Garba Isah)
];

echo "DB: " . $db->getDatabaseName() . "\n\n";

foreach ($targets as $opPropId => $opTempFileno) {
    echo "---- OP prop_id=$opPropId temp=$opTempFileno ----\n";

    $opRows = $db->table('pra')
        ->where('prop_id', $opPropId)
        ->where('temp_fileno', $opTempFileno)
        ->where('instrument_type', 'LIKE', '%Occupancy Permit%')
        ->get();

    if ($opRows->count() !== 1) {
        echo "  SKIP: expected 1 OP row, found {$opRows->count()}.\n\n";
        continue;
    }
    $op = $opRows->first();

    // Child ToT = source of the correct allottee (its Party 1 / Grantor).
    $totRows = $db->table('pra')
        ->where('parent_prop_id', (string) $opPropId) // varchar column — bind as string
        ->where('instrument_type', 'LIKE', '%Transfer%')
        ->orderBy('id')
        ->get();

    if ($totRows->count() !== 1) {
        echo "  SKIP: expected 1 child ToT row, found {$totRows->count()}.\n\n";
        continue;
    }
    $tot = $totRows->first();

    $correctParty2 = trim((string) ($tot->party_1 ?: $tot->Grantor));
    if ($correctParty2 === '') {
        echo "  SKIP: ToT party_1/Grantor (allottee) is empty.\n\n";
        continue;
    }

    echo "  ToT (id={$tot->id}) allottee (Party 1): '{$correctParty2}'\n";
    echo "  OP  (id={$op->id}) BEFORE -> party_1: '{$op->party_1}' | party_2: '{$op->party_2}' | Grantee: '{$op->Grantee}'\n";

    if (strcasecmp(trim((string) $op->party_2), $correctParty2) === 0
        && strcasecmp(trim((string) $op->Grantee), $correctParty2) === 0) {
        echo "  No change needed: OP Party 2 already correct.\n\n";
        continue;
    }

    $db->table('pra')->where('id', $op->id)->update([
        'party_2'    => $correctParty2,
        'Grantee'    => $correctParty2,
        'updated_at' => now(),
    ]);

    $after = $db->table('pra')->where('id', $op->id)->first();
    echo "  OP  (id={$op->id}) AFTER  -> party_1: '{$after->party_1}' | party_2: '{$after->party_2}' | Grantee: '{$after->Grantee}'\n\n";
}

echo "Done.\n";
