<?php
/**
 * Group B fix: Transfer of Title (OP) rows whose Party 1 / Grantor was wrongly
 * set to "KANO STATE GOVERNMENT".
 *
 * ToT lineage:
 *   Party 1 / Grantor = the OP allottee (OP row Party 2 / Grantee) — the seller
 *   Party 2 / Grantee = the new owner (left untouched)
 *
 * The correct Party 1 is derived from the parent OP row (located via the ToT's
 * parent_prop_id) — never hardcoded. If multiple OP rows exist for the parent
 * prop_id (duplicate OPs) they must all agree on the allottee, else the row is
 * skipped. Only party_1 / Grantor is touched. Prints before/after; idempotent.
 *
 * Run:  php fix_tot_party1_groupB.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');

// ToT rows to correct (child ToT id => file no for readability).
$totIds = [
    168725, // RES-2017-1653  (parent OP prop_id 120256 — 2 dup OP rows, both agree)
    170504, // RES-2025-3230
    170510, // RES-2024-5561
    172947, // RES-2023-331
    172107, // RES-2023-6488
    170518, // RES-2023-1004
    170515, // RES-2025-2337
    170507, // RES-2023-6267
    170516, // RES-2022-6421
];

echo "DB: " . $db->getDatabaseName() . "\n\n";

foreach ($totIds as $totId) {
    $tot = $db->table('pra')->where('id', $totId)->first();
    if (!$tot) { echo "---- ToT id=$totId ----\n  SKIP: not found.\n\n"; continue; }

    echo "---- ToT id={$totId} {$tot->mlsFNo} (parent_prop_id={$tot->parent_prop_id}) ----\n";

    if (empty($tot->parent_prop_id)) { echo "  SKIP: no parent_prop_id.\n\n"; continue; }

    // Parent OP row(s) = source of the correct allottee (their Party 2 / Grantee).
    $ops = $db->table('pra')
        ->where('prop_id', (string) $tot->parent_prop_id) // varchar column — bind as string
        ->where('instrument_type', 'LIKE', '%Occupancy Permit%')
        ->get(['id', 'party_2', 'Grantee']);

    if ($ops->count() === 0) { echo "  SKIP: no parent OP found (prop_id={$tot->parent_prop_id}).\n\n"; continue; }

    // Collect distinct non-empty allottee values across (possibly duplicate) OP rows.
    $allottees = [];
    foreach ($ops as $o) {
        $v = trim((string) ($o->party_2 ?: $o->Grantee));
        if ($v !== '') { $allottees[strtoupper($v)] = $v; }
    }
    if (count($allottees) === 0) { echo "  SKIP: OP allottee (party_2/Grantee) empty.\n\n"; continue; }
    if (count($allottees) > 1) {
        echo "  SKIP: duplicate OP rows disagree on allottee: " . implode(' | ', $allottees) . "\n\n";
        continue;
    }
    $correctParty1 = reset($allottees);

    echo "  OP allottee: '{$correctParty1}'\n";
    echo "  ToT BEFORE -> party_1: '{$tot->party_1}' | Grantor: '{$tot->Grantor}' | party_2: '{$tot->party_2}'\n";

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
    echo "  ToT AFTER  -> party_1: '{$after->party_1}' | Grantor: '{$after->Grantor}' | party_2: '{$after->party_2}'\n\n";
}

echo "Done.\n";
