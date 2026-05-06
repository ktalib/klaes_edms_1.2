<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

DB::connection('sqlsrv')->statement("ALTER TABLE file_indexings ALTER COLUMN last_batch_id NVARCHAR(50) NULL");
echo "Column last_batch_id altered to NVARCHAR(50) successfully.\n";

// Verify
$cols = DB::connection('sqlsrv')->select("
    SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'last_batch_id'
");
foreach ($cols as $col) {
    echo $col->COLUMN_NAME . ' => ' . $col->DATA_TYPE . '(' . ($col->CHARACTER_MAXIMUM_LENGTH ?? 'n/a') . ')' . PHP_EOL;
}
