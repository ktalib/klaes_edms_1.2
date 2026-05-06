<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$cols = DB::connection('sqlsrv')->select("SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'rofo_staging' ORDER BY ORDINAL_POSITION");
foreach ($cols as $c) {
    echo $c->COLUMN_NAME . ' | ' . $c->DATA_TYPE . ' | ' . ($c->CHARACTER_MAXIMUM_LENGTH ?? 'null') . "\n";
}

echo "\n--- op_staging columns ---\n";
$cols2 = DB::connection('sqlsrv')->select("SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'op_staging' ORDER BY ORDINAL_POSITION");
foreach ($cols2 as $c) {
    echo $c->COLUMN_NAME . ' | ' . $c->DATA_TYPE . ' | ' . ($c->CHARACTER_MAXIMUM_LENGTH ?? 'null') . "\n";
}
