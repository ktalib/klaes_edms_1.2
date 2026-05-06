<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$propId = '80000814';
echo "prop_id = $propId\n";

$pra = Illuminate\Support\Facades\DB::connection('sqlsrv')->select("SELECT id, prop_id, mlsFNo, temp_fileno, instrument_type, created_at, parent_prop_id FROM pra WHERE prop_id = ?", [$propId]);
echo "PRA Table:\n" . json_encode($pra, JSON_PRETTY_PRINT) . "\n\n";

$ic = Illuminate\Support\Facades\DB::connection('sqlsrv')->select("SELECT id, prop_id, mlsFNo, temp_fileno, instrument_type, created_at FROM instrument_capture WHERE prop_id = ?", [$propId]);
echo "instrument_capture:\n" . json_encode($ic, JSON_PRETTY_PRINT) . "\n\n";

$master = Illuminate\Support\Facades\DB::connection('sqlsrv')->select("SELECT * FROM PropID_Master WHERE prop_id = ?", [$propId]);
echo "PropID_Master:\n" . json_encode($master, JSON_PRETTY_PRINT) . "\n";
