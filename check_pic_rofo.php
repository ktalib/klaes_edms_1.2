<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get pic columns with types
$cols = DB::connection('sqlsrv')->select("
    SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'pic'
    ORDER BY ORDINAL_POSITION
");

echo "=== pic table columns (" . count($cols) . " total) ===\n\n";
foreach ($cols as $c) {
    $type = $c->DATA_TYPE;
    if ($c->CHARACTER_MAXIMUM_LENGTH) {
        $len = $c->CHARACTER_MAXIMUM_LENGTH == -1 ? 'max' : $c->CHARACTER_MAXIMUM_LENGTH;
        $type .= "($len)";
    }
    $nullable = $c->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL';
    echo str_pad($c->COLUMN_NAME, 35) . str_pad($type, 20) . $nullable . "\n";
}

// Count RofO types
echo "\n=== R of O transaction types in pic ===\n";
$rofos = DB::connection('sqlsrv')->select("
    SELECT transaction_type, COUNT(*) as cnt FROM pic
    WHERE transaction_type IN (
        'Right of Occupancy', 'R OF O', 'R-OF-O', 'R.OF.O', 'R-OFO'
    )
    GROUP BY transaction_type ORDER BY cnt DESC
");
$total = 0;
foreach ($rofos as $r) {
    echo str_pad($r->transaction_type, 35) . number_format($r->cnt) . "\n";
    $total += $r->cnt;
}
echo "Total RofO: " . number_format($total) . "\n";
