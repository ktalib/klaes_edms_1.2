<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$conn = DB::connection('sqlsrv');

$configs = [
    'CofO_staging' => ['tp_no', 'approved_plan_no'],
    'pra' => ['tp_no', 'approved_plan_no'],
    'pic' => ['tp_no', 'approved_plan_no'],
    'file_indexings' => ['tp_no'],
    'instrument_capture' => ['tp_no', 'survey_plan_no']
];

$tp_data = [];

foreach ($configs as $table => $cols) {
    foreach ($cols as $col) {
        echo "Gathering data from $table.$col...\n";
        try {
            // Using raw query to avoid any Eloquent/QueryBuilder overhead that might cause implicit casting
            $results = $conn->select("SELECT DISTINCT [$col] FROM [$table] WHERE [$col] IS NOT NULL AND [$col] != ''");
            
            foreach ($results as $row) {
                $val = trim($row->$col);
                if ($val) {
                    if (!isset($tp_data[$val])) {
                        $tp_data[$val] = [];
                    }
                    if (!in_array($table, $tp_data[$val])) {
                        $tp_data[$val][] = $table;
                    }
                }
            }
        } catch (\Exception $e) {
            echo "Error in $table.$col: " . $e->getMessage() . "\n";
        }
    }
}

echo "Total unique values to process: " . count($tp_data) . "\n";

// 3. Insert/Update
foreach ($tp_data as $tp => $sources) {
    $sourceStr = implode(',', $sources);
    try {
        $existing = $conn->table('tp_lookups')->where('tp_no', (string)$tp)->first();
        if ($existing) {
            $existingSources = explode(',', $existing->source_tables);
            $newSources = array_unique(array_merge($existingSources, $sources));
            sort($newSources);
            $newSourceStr = implode(',', $newSources);
            
            if ($newSourceStr !== $existing->source_tables) {
                $conn->table('tp_lookups')->where('id', $existing->id)->update(['source_tables' => $newSourceStr]);
            }
        } else {
            $conn->table('tp_lookups')->insert([
                'tp_no' => (string)$tp,
                'source_tables' => $sourceStr,
                'created_at' => now()
            ]);
        }
    } catch (\Exception $e) {
        echo "Error inserting/updating '$tp': " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
