<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = ['mother_applications', 'subapplications', 'StFileNo', 'eRegistry', 'billing'];

foreach ($tables as $table) {
    echo "Table: $table\n";
    $results = DB::connection('sqlsrv')->select("
        SELECT name FROM sys.columns 
        WHERE object_id = OBJECT_ID('$table') 
        AND is_identity = 1
    ");
    print_r($results);
}
