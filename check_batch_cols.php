<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cols = DB::connection('sqlsrv')->select("
    SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'file_indexings' 
    AND COLUMN_NAME IN ('last_batch_id','batch_generated','batch_generated_at','batch_generated_by')
");

foreach ($cols as $col) {
    echo $col->COLUMN_NAME . ' => ' . $col->DATA_TYPE . '(' . ($col->CHARACTER_MAXIMUM_LENGTH ?? 'n/a') . ')' . PHP_EOL;
}
