<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $results = DB::connection('sqlsrv')->select("
        SELECT COLUMN_NAME, COLUMN_DEFAULT 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'fileNumber'
        AND COLUMN_NAME IN ('cad_lands_matching', 'cad_st_matching', 'cad_sltr_matching', 'pp_lands_matching', 'pp_st_matching', 'pp_sltr_matching', 'has_temp_file')
    ");
    print_r($results);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
