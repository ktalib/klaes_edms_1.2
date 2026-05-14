<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$results = DB::connection('sqlsrv')->select("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'related_fileno'");
print_r($results);

$results2 = DB::connection('sqlsrv')->select("SELECT TOP 5 related_fileno FROM file_indexings WHERE related_fileno IS NOT NULL AND related_fileno <> ''");
print_r($results2);
