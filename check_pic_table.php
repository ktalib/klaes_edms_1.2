<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// 1. Check pic table columns
echo "=== pic table columns ===\n";
$cols = DB::connection('sqlsrv')->select("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pic' ORDER BY ORDINAL_POSITION");
foreach ($cols as $c) {
    echo "  {$c->COLUMN_NAME} ({$c->DATA_TYPE})\n";
}

// 2. Total records
$total = DB::connection('sqlsrv')->table('pic')->count();
echo "\nTotal records: {$total}\n";

// 3. Check party_1/party_2 null counts
$p1Null = DB::connection('sqlsrv')->table('pic')->whereNull('party_1')->count();
$p2Null = DB::connection('sqlsrv')->table('pic')->whereNull('party_2')->count();
echo "party_1 NULL: {$p1Null}\n";
echo "party_2 NULL: {$p2Null}\n";

// 4. Check specific party columns - which ones have data
$partyColumns = ['Grantor', 'Grantee', 'Assignor', 'Assignee', 'Mortgagor', 'Mortgagee', 
                 'Surrenderor', 'Surrenderee', 'Lessor', 'Lessee', 'Releasor', 'Releasee',
                 'Donor', 'Donee', 'Vendor', 'Purchaser'];

echo "\n=== Party column population ===\n";
foreach ($partyColumns as $col) {
    try {
        $cnt = DB::connection('sqlsrv')->table('pic')->whereNotNull($col)->where($col, '!=', '')->count();
        if ($cnt > 0) echo "  {$col}: {$cnt} records with data\n";
    } catch (\Exception $e) {
        echo "  {$col}: column not found\n";
    }
}

// 5. Sample records where party_1 is NULL but other party cols have data
echo "\n=== Sample records with NULL party_1 but populated specific parties (first 5) ===\n";
$samples = DB::connection('sqlsrv')->table('pic')
    ->whereNull('party_1')
    ->where(function($q) {
        $q->whereNotNull('Grantor')->where('Grantor', '!=', '')
          ->orWhere(function($q2) { $q2->whereNotNull('Assignor')->where('Assignor', '!=', ''); });
    })
    ->take(5)
    ->get();
foreach ($samples as $r) {
    echo "  id={$r->id} | instrument=" . ($r->instrument_type ?? 'NULL')
        . " | Grantor=" . ($r->Grantor ?? 'NULL')
        . " | Grantee=" . ($r->Grantee ?? 'NULL')
        . " | Assignor=" . ($r->Assignor ?? 'NULL')
        . " | Assignee=" . ($r->Assignee ?? 'NULL')
        . " | party_1=" . ($r->party_1 ?? 'NULL')
        . " | party_2=" . ($r->party_2 ?? 'NULL')
        . "\n";
}

// 6. Check instrument_type distribution
echo "\n=== instrument_type distribution in pic ===\n";
$types = DB::connection('sqlsrv')->table('pic')
    ->select(DB::raw('instrument_type, COUNT(*) as cnt'))
    ->whereNotNull('instrument_type')
    ->groupBy('instrument_type')
    ->orderByDesc(DB::raw('COUNT(*)'))
    ->get();
foreach ($types as $t) {
    echo "  {$t->instrument_type} => {$t->cnt}\n";
}
$nullType = DB::connection('sqlsrv')->table('pic')->whereNull('instrument_type')->count();
echo "  NULL => {$nullType}\n";
