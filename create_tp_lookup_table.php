<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$conn = DB::connection('sqlsrv');

// 1. Create table if not exists
echo "Checking/Creating tp_lookups table...\n";
$tableExists = $conn->select("SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'tp_lookups'");

if (empty($tableExists)) {
    $conn->statement("
        CREATE TABLE tp_lookups (
            id INT IDENTITY(1,1) PRIMARY KEY,
            tp_no NVARCHAR(255) NOT NULL,
            source_tables NVARCHAR(MAX),
            created_at DATETIME DEFAULT GETDATE()
        )
    ");
    $conn->statement("CREATE UNIQUE INDEX idx_tp_no ON tp_lookups(tp_no)");
    echo "Table created.\n";
} else {
    echo "Table already exists.\n";
}

// 2. Aggregate values
$configs = [
    'CofO_staging' => ['tp_no', 'approved_plan_no'],
    'pra' => ['tp_no', 'approved_plan_no'],
    'pic' => ['tp_no', 'approved_plan_no'],
    'file_indexings' => ['tp_no'],
    'instrument_capture' => ['tp_no', 'survey_plan_no']
];

$tp_data = [];

foreach ($configs as $table => $cols) {
    echo "Gathering data from $table...\n";
    foreach ($cols as $col) {
        $results = $conn->table($table)
            ->whereNotNull($col)
            ->where($col, '!=', '')
            ->select($col)
            ->distinct()
            ->get();
            
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
    }
}

echo "Total unique values to process: " . count($tp_data) . "\n";

// 3. Insert/Update
foreach ($tp_data as $tp => $sources) {
    $sourceStr = implode(',', $sources);
    
    $existing = $conn->table('tp_lookups')->where('tp_no', $tp)->first();
    if ($existing) {
        // Update sources if needed
        $existingSources = explode(',', $existing->source_tables);
        $newSources = array_unique(array_merge($existingSources, $sources));
        sort($newSources);
        $newSourceStr = implode(',', $newSources);
        
        if ($newSourceStr !== $existing->source_tables) {
            $conn->table('tp_lookups')->where('id', $existing->id)->update(['source_tables' => $newSourceStr]);
        }
    } else {
        $conn->table('tp_lookups')->insert([
            'tp_no' => $tp,
            'source_tables' => $sourceStr,
            'created_at' => now()
        ]);
    }
}

echo "Done.\n";
