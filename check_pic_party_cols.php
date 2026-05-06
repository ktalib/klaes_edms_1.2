<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$cols = DB::connection('sqlsrv')->select("
    SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME='pic' AND COLUMN_NAME LIKE '%party%'
    ORDER BY COLUMN_NAME
");

foreach ($cols as $c) {
    echo $c->COLUMN_NAME . ": " . $c->DATA_TYPE . "(" . $c->CHARACTER_MAXIMUM_LENGTH . ")\n";
}

// Also check actual record count
$total = DB::connection('sqlsrv')->table('pic')->count();
echo "\nTotal pic records: $total\n";

// Check instrument_type filter support
$types = DB::connection('sqlsrv')->table('pic')
    ->select('instrument_type', DB::raw('COUNT(*) as cnt'))
    ->groupBy('instrument_type')
    ->orderByDesc('cnt')
    ->get();
echo "\nInstrument types:\n";
foreach ($types as $t) {
    echo "  " . ($t->instrument_type ?? 'NULL') . ": " . $t->cnt . "\n";
}
