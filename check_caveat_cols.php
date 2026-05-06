<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$tables = ['file_history_staging', 'CofO_staging', 'pra', 'deed_registrations'];
foreach ($tables as $t) {
    $cols = DB::connection('sqlsrv')
        ->select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME=? AND COLUMN_NAME LIKE '%caveat%' ORDER BY COLUMN_NAME", [$t]);
    $names = array_map(fn($c) => $c->COLUMN_NAME, $cols);
    echo "$t: " . (empty($names) ? 'NONE' : implode(', ', $names)) . "\n";
}
