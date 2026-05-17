<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $results = DB::connection('sqlsrv')->select("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME IN ('file_number', 'temp_file_no', 'has_temp_file')
    ");
    
    echo "file_indexings columns info:\n";
    foreach ($results as $row) {
        echo " - {$row->COLUMN_NAME}: Type={$row->DATA_TYPE}, Nullable={$row->IS_NULLABLE}\n";
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
