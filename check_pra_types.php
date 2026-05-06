<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$types = DB::connection('sqlsrv')->table('pra')
    ->select(DB::raw('instrument_type, COUNT(*) as cnt'))
    ->whereNotNull('instrument_type')
    ->groupBy('instrument_type')
    ->orderByDesc(DB::raw('COUNT(*)'))
    ->get();

echo "=== PRA instrument_type distribution ===\n";
foreach ($types as $t) {
    echo "  {$t->instrument_type} => {$t->cnt}\n";
}

// Also count NULLs
$nullCount = DB::connection('sqlsrv')->table('pra')->whereNull('instrument_type')->count();
echo "\n  NULL => {$nullCount}\n";
echo "\n  TOTAL = " . ($types->sum('cnt') + $nullCount) . "\n";
