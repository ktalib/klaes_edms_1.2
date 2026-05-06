<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Find "pic" table
echo "=== Looking for PIC-related tables ===\n";
$tables = DB::connection('sqlsrv')->select("
    SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_TYPE = 'BASE TABLE' 
    AND (TABLE_NAME LIKE '%pic%' OR TABLE_NAME LIKE '%PIC%')
    ORDER BY TABLE_NAME
");
foreach ($tables as $t) {
    echo "  " . $t->TABLE_NAME . "\n";
}

echo "\n=== CofO_staging columns ===\n";
$cols1 = DB::connection('sqlsrv')->select("
    SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'CofO_staging'
    ORDER BY ORDINAL_POSITION
");
foreach ($cols1 as $c) {
    $len = $c->CHARACTER_MAXIMUM_LENGTH ? "({$c->CHARACTER_MAXIMUM_LENGTH})" : '';
    echo "  {$c->COLUMN_NAME} - {$c->DATA_TYPE}{$len} [{$c->IS_NULLABLE}]\n";
}

echo "\n=== CofO_staging row count ===\n";
$count1 = DB::connection('sqlsrv')->selectOne("SELECT COUNT(*) AS cnt FROM CofO_staging");
echo "  " . $count1->cnt . " rows\n";

// Now check each PIC table
foreach ($tables as $t) {
    $tn = $t->TABLE_NAME;
    echo "\n=== {$tn} columns ===\n";
    $cols = DB::connection('sqlsrv')->select("
        SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = ?
        ORDER BY ORDINAL_POSITION
    ", [$tn]);
    foreach ($cols as $c) {
        $len = $c->CHARACTER_MAXIMUM_LENGTH ? "({$c->CHARACTER_MAXIMUM_LENGTH})" : '';
        echo "  {$c->COLUMN_NAME} - {$c->DATA_TYPE}{$len} [{$c->IS_NULLABLE}]\n";
    }
    $cnt = DB::connection('sqlsrv')->selectOne("SELECT COUNT(*) AS cnt FROM [{$tn}]");
    echo "  Row count: {$cnt->cnt}\n";
}
