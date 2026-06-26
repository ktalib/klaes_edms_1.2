<?php
/**
 * Fix wrong Party 1 on Transfer of Title (OP) rows.
 *
 * Lineage for a "Transfer of Title (OP)":
 *   Party 1 / Grantor = the OP allottee (OP row Party 2 = Grantee)
 *   Party 2 / Grantee = the new owner (left untouched)
 *
 * Several ToT rows wrongly show Party 1 = "KANO STATE GOVERNMENT" (the OP
 * grantor). This derives the correct Party 1 from the OP row (linked via the
 * ToT's parent_prop_id) and updates only party_1 / Grantor. Idempotent.
 *
 * Run:  php fix_tot_party1_batch_20260625.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');

// ToT rows to correct: prop_id => temp_fileno
$targets = [
    125572 => 'TEMP-87427', // RES-2024-5560
    124843 => 'TEMP-86899', // RES-2025-39
];

echo "DB: " . $db->getDatabaseName() . "\n\n";

foreach ($targets as $totPropId => $totTempFileno) {
    echo "---- ToT prop_id=$totPropId temp=$totTempFileno ----\n";

    $totRows = $db->table('pra')
        ->where('prop_id', $totPropId)
        ->where('temp_fileno', $totTempFileno)
        ->get();

    if ($totRows->count() !== 1) {
        echo "  SKIP: expected 1 ToT row, found {$totRows->count()}.\n\n";
        continue;
    }
    $tot = $totRows->first();

    if (empty($tot->parent_prop_id)) {
        echo "  SKIP: ToT has no parent_prop_id to locate the OP.\n\n";
        continue;
    }

    // OP row = source of the correct Party 1 (allottee).
    $op = $db->table('pra')
        ->where('prop_id', $tot->parent_prop_id)
        ->where('instrument_type', 'LIKE', '%Occupancy Permit%')
        ->orderBy('id')
        ->first();

    if (!$op) {
        echo "  SKIP: OP row not found (prop_id={$tot->parent_prop_id}).\n\n";
        continue;
    }

    $correctParty1 = trim((string) ($op->party_2 ?: $op->Grantee));
    if ($correctParty1 === '') {
        echo "  SKIP: OP party_2/Grantee (allottee) is empty.\n\n";
        continue;
    }

    echo "  OP  (id={$op->id})  allottee: '{$correctParty1}'\n";
    echo "  ToT (id={$tot->id}) BEFORE -> party_1: '{$tot->party_1}' | Grantor: '{$tot->Grantor}' | party_2: '{$tot->party_2}'\n";

    if (strcasecmp(trim((string) $tot->party_1), $correctParty1) === 0
        && strcasecmp(trim((string) $tot->Grantor), $correctParty1) === 0) {
        echo "  No change needed: Party 1 already correct.\n\n";
        continue;
    }

    $db->table('pra')->where('id', $tot->id)->update([
        'party_1'    => $correctParty1,
        'Grantor'    => $correctParty1,
        'updated_at' => now(),
    ]);

    $after = $db->table('pra')->where('id', $tot->id)->first();
    echo "  ToT (id={$tot->id}) AFTER  -> party_1: '{$after->party_1}' | Grantor: '{$after->Grantor}' | party_2: '{$after->party_2}'\n\n";
}

echo "Done.\n";
