<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$sql = "SELECT id, prop_id, mlsFNo, temp_fileno, instrument_type, created_at FROM pra 
        WHERE mlsFNo IN ('RES-2000-4828', 'TEMP-36519', 'RES-2026-2099') 
           OR temp_fileno = 'TEMP-36519'";

$data = Illuminate\Support\Facades\DB::connection('sqlsrv')->select($sql);
echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
