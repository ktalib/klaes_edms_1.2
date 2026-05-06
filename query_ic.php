<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$sql = "SELECT id, prop_id, mlsFNo, temp_fileno, created_at FROM instrument_capture 
        WHERE mlsFNo IN ('RES-2000-4828', 'TEMP-36519', 'RES-2026-2099') 
           OR temp_fileno = 'TEMP-36519'";

$data = Illuminate\Support\Facades\DB::connection('sqlsrv')->select($sql);
echo "instrument_capture:\n";
echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
