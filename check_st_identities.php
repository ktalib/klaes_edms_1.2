<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = ['st_file_numbers', 'fileNumber', 'file_numbers'];

foreach ($tables as $table) {
    echo "Table: $table\n";
    $results = DB::connection('sqlsrv')->select("
        SELECT name FROM sys.columns 
        WHERE object_id = OBJECT_ID('$table') 
        AND is_identity = 1
    ");
    print_r($results);
}
