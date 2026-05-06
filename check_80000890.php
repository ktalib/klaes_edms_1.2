<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::connection('sqlsrv')->table('pra')
    ->where('prop_id', '80000890')
    ->orWhere('parent_prop_id', '80000890')
    ->get();

echo "PRA (80000890):\n" . json_encode($rows, JSON_PRETTY_PRINT) . "\n";

$rows2 = DB::connection('sqlsrv')->table('instrument_capture')
    ->where('prop_id', '80000890')
    ->get();

echo "Instrument Capture (80000890):\n" . json_encode($rows2, JSON_PRETTY_PRINT) . "\n";
