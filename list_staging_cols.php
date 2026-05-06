<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$conn = \DB::connection('sqlsrv');
$tables = ['file_history_staging', 'CofO_staging', 'pra', 'deed_registrations'];
foreach ($tables as $t) {
    $cols = $conn->select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? ORDER BY ORDINAL_POSITION", [$t]);
    echo "=== $t ===\n";
    $names = [];
    foreach ($cols as $c) {
        $names[] = $c->COLUMN_NAME;
    }
    echo implode(', ', $names) . "\n\n";
}
