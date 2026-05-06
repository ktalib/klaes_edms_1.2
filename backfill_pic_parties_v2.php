<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Step 1: Widen party_1 and party_2 to nvarchar(500)
echo "Altering party_1 and party_2 to nvarchar(500)...\n";
DB::connection('sqlsrv')->statement("ALTER TABLE pic ALTER COLUMN party_1 NVARCHAR(500) NULL");
DB::connection('sqlsrv')->statement("ALTER TABLE pic ALTER COLUMN party_2 NVARCHAR(500) NULL");
echo "Columns altered.\n\n";

// Step 2: Backfill
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

$beforeNull = DB::connection('sqlsrv')->table('pic')->whereNull('party_1')->count();
echo "Before: {$beforeNull} records with NULL party_1\n";

DB::connection('sqlsrv')->statement($sql);
echo "Update executed.\n";

$afterNull = DB::connection('sqlsrv')->table('pic')->whereNull('party_1')->count();
$afterFilled = DB::connection('sqlsrv')->table('pic')->whereNotNull('party_1')->count();
echo "After: {$afterNull} still NULL, {$afterFilled} now populated\n";

// Samples
echo "\n=== Sample populated records ===\n";
$samples = DB::connection('sqlsrv')->table('pic')
    ->whereNotNull('party_1')
    ->take(10)
    ->get(['id', 'instrument_type', 'party_1', 'party_2']);
foreach ($samples as $r) {
    echo "  id={$r->id} | type=" . ($r->instrument_type ?? 'NULL')
        . " | party_1=" . ($r->party_1 ?? 'NULL')
        . " | party_2=" . ($r->party_2 ?? 'NULL')
        . "\n";
}

// Records still NULL
if ($afterNull > 0) {
    echo "\n=== Records still NULL (no source party data) ===\n";
    $stillNull = DB::connection('sqlsrv')->table('pic')
        ->whereNull('party_1')
        ->take(5)
        ->get(['id', 'instrument_type', 'Grantor', 'Grantee', 'Assignor', 'Assignee']);
    foreach ($stillNull as $r) {
        echo "  id={$r->id} | type=" . ($r->instrument_type ?? 'NULL')
            . " | Grantor=" . ($r->Grantor ?? 'NULL')
            . " | Grantee=" . ($r->Grantee ?? 'NULL')
            . " | Assignor=" . ($r->Assignor ?? 'NULL')
            . " | Assignee=" . ($r->Assignee ?? 'NULL')
            . "\n";
    }
}
