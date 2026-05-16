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

$all_values = [];

foreach ($configs as $table => $cols) {
    echo "Processing $table...\n";
    foreach ($cols as $col) {
        $results = DB::connection('sqlsrv')
            ->table($table)
            ->whereNotNull($col)
            ->where($col, '!=', '')
            ->select($col)
            ->distinct()
            ->get();
            
        foreach ($results as $row) {
            $val = trim($row->$col);
            if ($val) {
                $all_values[$val] = true;
            }
        }
    }
}

$unique_values = array_keys($all_values);
sort($unique_values);

echo "Total Unique TP/Plan Numbers: " . count($unique_values) . "\n";
echo "First 50 values:\n";
foreach (array_slice($unique_values, 0, 50) as $v) {
    echo "  $v\n";
}

// Optionally save to a JSON or CSV if needed, but the user said "create a look table"
// I will create a table named `tp_lookup` if it doesn't exist.
