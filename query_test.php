<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$pra = Illuminate\Support\Facades\DB::connection('sqlsrv')->select("SELECT id, prop_id, mlsFNo, temp_fileno, instrument_type, created_at FROM pra WHERE prop_id = '74296'");
echo "PRA Table:\n";
echo json_encode($pra, JSON_PRETTY_PRINT) . "\n\n";

$master = Illuminate\Support\Facades\DB::connection('sqlsrv')->select("SELECT * FROM PropID_Master WHERE prop_id = '74296'");
echo "Master Table:\n";
echo json_encode($master, JSON_PRETTY_PRINT) . "\n";
