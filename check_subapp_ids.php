<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $res = DB::connection('sqlsrv')->table('subapplications')->select('id')->limit(10)->get();
    echo "Current IDs in subapplications:\n";
    foreach ($res as $row) {
        echo "- " . $row->id . " (Type: " . gettype($row->id) . ")\n";
    }

    $nonNumeric = DB::connection('sqlsrv')->select("SELECT id FROM subapplications WHERE id LIKE '%[^0-9]%'");
    echo "\nNon-numeric IDs found (" . count($nonNumeric) . "):\n";
    foreach (array_slice($nonNumeric, 0, 10) as $row) {
        echo "- " . $row->id . "\n";
    }
    
    $schema = DB::connection('sqlsrv')->select("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'subapplications' AND COLUMN_NAME = 'id'");
    echo "\nSchema of id column:\n";
    print_r($schema);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
