<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::connection('sqlsrv')->table('pra')
    ->where('party_2', 'LIKE', '%Safiyyanu Isah%')
    ->get();

echo "PRA Search results:\n" . json_encode($rows, JSON_PRETTY_PRINT) . "\n";
