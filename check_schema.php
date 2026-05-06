<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $results = DB::connection('sqlsrv')->select("
        SELECT 
            COLUMN_NAME, 
            COLUMN_DEFAULT,
            IS_NULLABLE, 
            DATA_TYPE, 
            COLUMNPROPERTY(OBJECT_ID(TABLE_SCHEMA + '.' + TABLE_NAME), COLUMN_NAME, 'IsIdentity') AS IsIdentity
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'subapplications'
    ");
    print_r($results);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
