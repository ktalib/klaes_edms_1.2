<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$tables = ['CofO_staging', 'pic', 'file_history_staging'];
foreach ($tables as $table) {
    try {
        $data = Illuminate\Support\Facades\DB::connection('sqlsrv')->select("SELECT * FROM $table WHERE prop_id = '74296'");
        echo strtoupper($table) . ":\n";
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
    } catch (\Exception $e) {
        echo strtoupper($table) . " (Error): " . $e->getMessage() . "\n\n";
    }
}
