<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$conn = DB::connection('sqlsrv');
$targetCols = "'deeds_date','deeds_time','reg_date','reg_time','transaction_date','transaction_time'";

foreach (['file_history_staging','CofO_staging','pra','deed_registrations'] as $t) {
    $cols = $conn->select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME IN ($targetCols) ORDER BY COLUMN_NAME", [$t]);
    echo $t . ': ' . implode(', ', array_map(fn($c) => $c->COLUMN_NAME, $cols)) . PHP_EOL;
}
