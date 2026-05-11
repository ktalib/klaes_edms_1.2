<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$results = DB::connection('sqlsrv')->select("
    SELECT COLUMN_NAME, IS_NULLABLE 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'PropID_Master'
");

foreach($results as $r) {
    echo "{$r->COLUMN_NAME}: {$r->IS_NULLABLE}\n";
}
