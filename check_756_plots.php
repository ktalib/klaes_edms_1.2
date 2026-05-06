<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::connection('sqlsrv')->table('pra')
    ->where('mlsFNo', 'COM-2025-756')
    ->orWhere('fileno', 'COM-2025-756')
    ->select('id', 'prop_id', 'mlsFNo', 'plot_no', 'party_1', 'party_2', 'instrument_type', 'transaction_type', 'created_at')
    ->get();

echo json_encode($rows, JSON_PRETTY_PRINT);
