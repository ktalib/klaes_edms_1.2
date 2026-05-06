<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

/*
 * Backfill party_1 and party_2 in the pic table.
 * 
 * Priority order (first non-empty pair wins):
 *   1. Grantor  → party_1,  Grantee   → party_2
 *   2. Assignor → party_1,  Assignee  → party_2
 *   3. Mortgagor → party_1, Mortgagee → party_2
 *   4. Surrenderor → party_1, Surrenderee → party_2
 *   5. Lessor   → party_1,  Lessee    → party_2
 *   6. Releasor → party_1,  Releasee  → party_2
 *   7. Donor    → party_1,  Donee     → party_2
 *   8. Vendor   → party_1,  Purchaser → party_2
 */

$sql = "
UPDATE pic
SET 
    party_1 = COALESCE(
        NULLIF(LTRIM(RTRIM(Grantor)), ''),
        NULLIF(LTRIM(RTRIM(Assignor)), ''),
        NULLIF(LTRIM(RTRIM(Mortgagor)), ''),
        NULLIF(LTRIM(RTRIM(Surrenderor)), ''),
        NULLIF(LTRIM(RTRIM(Lessor)), ''),
        NULLIF(LTRIM(RTRIM(Releasor)), ''),
        NULLIF(LTRIM(RTRIM(Donor)), ''),
        NULLIF(LTRIM(RTRIM(Vendor)), '')
    ),
    party_2 = COALESCE(
        NULLIF(LTRIM(RTRIM(Grantee)), ''),
        NULLIF(LTRIM(RTRIM(Assignee)), ''),
        NULLIF(LTRIM(RTRIM(Mortgagee)), ''),
        NULLIF(LTRIM(RTRIM(Surrenderee)), ''),
        NULLIF(LTRIM(RTRIM(Lessee)), ''),
        NULLIF(LTRIM(RTRIM(Releasee)), ''),
        NULLIF(LTRIM(RTRIM(Donee)), ''),
        NULLIF(LTRIM(RTRIM(Purchaser)), '')
    ),
    updated_at = GETDATE()
WHERE party_1 IS NULL AND party_2 IS NULL
";

// Show count before
$beforeNull = DB::connection('sqlsrv')->table('pic')->whereNull('party_1')->count();
echo "Before: {$beforeNull} records with NULL party_1\n";

// Execute the update
$affected = DB::connection('sqlsrv')->statement($sql);
echo "Update executed.\n";

// Verify after
$afterNull = DB::connection('sqlsrv')->table('pic')->whereNull('party_1')->count();
$afterFilled = DB::connection('sqlsrv')->table('pic')->whereNotNull('party_1')->count();
echo "After: {$afterNull} still NULL, {$afterFilled} now populated\n";

// Show some samples
echo "\n=== Sample populated records ===\n";
$samples = DB::connection('sqlsrv')->table('pic')
    ->whereNotNull('party_1')
    ->take(10)
    ->get(['id', 'instrument_type', 'Grantor', 'Grantee', 'Assignor', 'Assignee', 'party_1', 'party_2']);
foreach ($samples as $r) {
    echo "  id={$r->id} | instr=" . ($r->instrument_type ?? 'NULL')
        . " | Grantor=" . ($r->Grantor ?? 'NULL')
        . " | Grantee=" . ($r->Grantee ?? 'NULL')
        . " | party_1=" . ($r->party_1 ?? 'NULL')
        . " | party_2=" . ($r->party_2 ?? 'NULL')
        . "\n";
}

// Check records still NULL (no party data at all)
echo "\n=== Records still NULL (no source party data) ===\n";
$stillNull = DB::connection('sqlsrv')->table('pic')
    ->whereNull('party_1')
    ->take(5)
    ->get(['id', 'instrument_type', 'Grantor', 'Grantee', 'Assignor', 'Assignee', 'Surrenderor', 'Surrenderee']);
foreach ($stillNull as $r) {
    echo "  id={$r->id} | instr=" . ($r->instrument_type ?? 'NULL')
        . " | Grantor=" . ($r->Grantor ?? 'NULL')
        . " | Grantee=" . ($r->Grantee ?? 'NULL')
        . " | Assignor=" . ($r->Assignor ?? 'NULL')
        . " | Assignee=" . ($r->Assignee ?? 'NULL')
        . " | Surrenderor=" . ($r->Surrenderor ?? 'NULL')
        . " | Surrenderee=" . ($r->Surrenderee ?? 'NULL')
        . "\n";
}
