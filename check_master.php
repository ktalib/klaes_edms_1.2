<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::connection('sqlsrv')->table('PropID_Master')
    ->where('primary_file_number', 'COM-2025-757')
    ->orWhere('mlsFNo', 'COM-2025-757')
    ->get();

echo json_encode($rows, JSON_PRETTY_PRINT);
