<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$cols = DB::connection('sqlsrv')->select("
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME='pra' AND COLUMN_NAME LIKE 'party%'
    ORDER BY COLUMN_NAME
");

echo "PRA party columns:\n";
foreach ($cols as $c) {
    echo "  " . $c->COLUMN_NAME . "\n";
}
