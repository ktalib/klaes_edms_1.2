<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$query = $argv[1] ?? "SELECT TOP 10 kangis_awaiting_fileno FROM kangis_grouping";
$results = DB::connection('sqlsrv')->select($query);
print_r($results);
