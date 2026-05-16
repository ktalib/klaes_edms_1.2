<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$tables = ['CofO_staging', 'pra', 'pic', 'file_indexings', 'instrument_capture'];
$columns_to_check = ['tp_no', 'tpno', 'tp_number', 'tpNo'];

foreach ($tables as $t) {
    echo "--- Table: $t ---\n";
    
    // Check which columns actually exist
    $existing_cols = DB::connection('sqlsrv')->select(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME IN ('" . implode("','", $columns_to_check) . "')",
        [$t]
    );
    
    if (empty($existing_cols)) {
        echo "No TP columns found.\n\n";
        continue;
    }
    
    foreach ($existing_cols as $c) {
        $col = $c->COLUMN_NAME;
        echo "Column: $col\n";
        
        $values = DB::connection('sqlsrv')
            ->table($t)
            ->whereNotNull($col)
            ->where($col, '!=', '')
            ->distinct()
            ->pluck($col)
            ->take(20); // Just a sample for now to see what we have
            
        foreach ($values as $val) {
            echo "  - $val\n";
        }
    }
    echo "\n";
}
