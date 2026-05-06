<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::connection('sqlsrv')->table('instrument_capture')
    ->where('plot_number', 'C-97')
    ->orWhere('property_description', 'LIKE', '%C-97%')
    ->get();

echo "Instrument Capture:\n" . json_encode($rows, JSON_PRETTY_PRINT) . "\n";

$rows2 = DB::connection('sqlsrv')->table('pra')
    ->where('plot_no', 'C-97')
    ->get();

echo "PRA:\n" . json_encode($rows2, JSON_PRETTY_PRINT) . "\n";
