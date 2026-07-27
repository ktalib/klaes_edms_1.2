<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = DB::connection('sqlsrv')->select("
    SELECT DISTINCT instrument_type, transaction_type 
    FROM pra 
    WHERE instrument_type LIKE '%transfer%' OR transaction_type LIKE '%transfer%'
");

print_r($rows);
