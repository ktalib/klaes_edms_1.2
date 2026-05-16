<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$conn = DB::connection('sqlsrv');

$cols = $conn->select("
    SELECT TABLE_NAME, COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE COLUMN_NAME IN ('tp_no', 'tpno', 'tp_number', 'tpNo')
    ORDER BY TABLE_NAME
");

foreach ($cols as $c) {
    echo "{$c->TABLE_NAME}.{$c->COLUMN_NAME}\n";
}
