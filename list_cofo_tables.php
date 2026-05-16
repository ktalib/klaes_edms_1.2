<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$res = DB::connection('sqlsrv')->select("SELECT name FROM sys.tables WHERE name LIKE '%c_of_o%' OR name LIKE '%cofo%' OR name LIKE '%certificate%'");
print_r($res);
