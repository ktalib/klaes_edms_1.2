<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$row = DB::connection('sqlsrv')->table('PropID_Master')
    ->where('prop_id', '80000785')
    ->first();

echo json_encode($row, JSON_PRETTY_PRINT);
