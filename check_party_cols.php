<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$tables = ['file_history_staging','pra','CofO_staging','deed_registrations'];
foreach ($tables as $t) {
    $cols = DB::connection('sqlsrv')
        ->select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME=? AND COLUMN_NAME LIKE 'party%' ORDER BY COLUMN_NAME", [$t]);
    $names = array_map(fn($c) => $c->COLUMN_NAME, $cols);
    echo "$t: " . implode(', ', $names) . "\n";
}
