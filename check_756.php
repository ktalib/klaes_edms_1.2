<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$row = DB::connection('sqlsrv')->table('PropID_Master')
    ->where('primary_file_number', 'COM-2025-756')
    ->first();

echo json_encode($row, JSON_PRETTY_PRINT);
