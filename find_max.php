<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = ['pra', 'instrument_capture', 'file_history_staging', 'CofO_staging', 'PropID_Master'];

foreach ($tables as $table) {
    try {
        $max = DB::connection('sqlsrv')->table($table)->max('prop_id');
        echo "Table: $table, Max prop_id: $max\n";
    } catch (\Exception $e) {
        echo "Table: $table, Error: " . $e->getMessage() . "\n";
    }
}
