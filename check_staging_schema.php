<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

foreach (['entities_staging', 'customers_staging'] as $table) {
    echo "Table: $table\n";
    $results = DB::connection('sqlsrv')->select("
        SELECT COLUMN_NAME, IS_NULLABLE, DATA_TYPE 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = '$table'
    ");
    print_r($results);
}
