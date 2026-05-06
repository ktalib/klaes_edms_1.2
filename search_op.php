<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::connection('sqlsrv')->table('instrument_capture')
    ->where('party_2_name', 'LIKE', '%SAFIYYANU ISAH%')
    ->orWhere('plot_number', '4113')
    ->get();

echo "Search results:\n" . json_encode($rows, JSON_PRETTY_PRINT) . "\n";
