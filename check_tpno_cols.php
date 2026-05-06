<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$tables = ['file_history_staging', 'CofO_staging', 'pra', 'deed_registrations'];
foreach ($tables as $t) {
    echo "\n$t:\n";
    $cols = DB::connection('sqlsrv')->select(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND (COLUMN_NAME LIKE '%tp%' OR COLUMN_NAME LIKE '%plan%' OR COLUMN_NAME LIKE '%town%') ORDER BY COLUMN_NAME",
        [$t]
    );
    foreach ($cols as $c) {
        echo "  {$c->COLUMN_NAME}\n";
    }
}
