<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$cols = DB::connection('sqlsrv')->select("
    SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE COLUMN_NAME = 'prop_id' 
    AND TABLE_NAME IN ('pra', 'PropID_Master', 'instrument_capture', 'file_history_staging')
");
echo json_encode($cols, JSON_PRETTY_PRINT);
