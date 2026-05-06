<?php
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $results = DB::connection('sqlsrv')->select("
        SELECT 
            COLUMN_NAME, 
            COLUMN_DEFAULT, 
            IS_NULLABLE, 
            DATA_TYPE,
            COLUMNPROPERTY(OBJECT_ID(TABLE_SCHEMA + '.' + TABLE_NAME), COLUMN_NAME, 'IsIdentity') AS IsIdentity
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'instrument_capture' AND COLUMN_NAME = 'id'
    ");

    print_r($results);

    $count = DB::connection('sqlsrv')->table('instrument_capture')->count();
    echo "\nTotal records: $count\n";

    $maxId = DB::connection('sqlsrv')->table('instrument_capture')->max('id');
    echo "Max ID: $maxId\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
