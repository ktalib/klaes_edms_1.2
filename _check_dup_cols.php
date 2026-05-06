<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$tables = ['instrument_capture', 'pra', 'land_recommendations', 'CofO', 'st_cofo'];

foreach ($tables as $table) {
    try {
        $cols = DB::connection('sqlsrv')->getSchemaBuilder()->getColumnListing($table);
        echo "=== $table ===\n" . implode(', ', $cols) . "\n\n";
    } catch (\Exception $e) {
        echo "=== $table === ERROR: " . $e->getMessage() . "\n\n";
    }
}
