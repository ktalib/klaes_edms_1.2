<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$fileNumbers = ['RES-2026-2112', 'RES-2026-2111', 'RES-2026-2109'];

foreach ($fileNumbers as $fileno) {
    echo "\n=== TOT: $fileno ===\n";
    $tots = DB::connection('sqlsrv')->table('pra')
        ->where('mlsFNo', $fileno)
        ->orWhere('fileno', $fileno)
        ->get();
    echo json_encode($tots, JSON_PRETTY_PRINT) . "\n";
}
