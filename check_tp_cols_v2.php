<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$tables = ['CofO_staging', 'pra', 'pic', 'file_indexings', 'instrument_capture'];

foreach ($tables as $t) {
    echo "--- Table: $t ---\n";
    try {
        $cols = DB::connection('sqlsrv')->select(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? ORDER BY COLUMN_NAME",
            [$t]
        );
        foreach ($cols as $c) {
            $name = $c->COLUMN_NAME;
            if (stripos($name, 'tp') !== false || stripos($name, 'plan') !== false) {
                echo "  [MATCH] $name\n";
            } else {
                // echo "  $name\n"; // Uncomment to see all columns
            }
        }
    } catch (\Exception $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
