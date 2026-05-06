<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::connection('sqlsrv')->table('pra')
    ->where('prop_id', '80000800')
    ->get();

echo json_encode($rows, JSON_PRETTY_PRINT);
