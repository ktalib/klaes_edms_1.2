<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$configs = [
    'CofO_staging' => ['tp_no', 'approved_plan_no'],
    'pra' => ['tp_no', 'approved_plan_no'],
    'pic' => ['tp_no', 'approved_plan_no'],
    'file_indexings' => ['tp_no'],
    'instrument_capture' => ['tp_no', 'survey_plan_no']
];

foreach ($configs as $table => $cols) {
    echo "--- $table ---\n";
    foreach ($cols as $col) {
        $info = DB::connection('sqlsrv')->select(
            "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $col]
        );
        echo "  $col: " . ($info[0]->DATA_TYPE ?? 'N/A') . "\n";
    }
}
