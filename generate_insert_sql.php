<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$conn = DB::connection('sqlsrv');
$records = $conn->table('tp_lookups')->get();

$sql = "INSERT INTO tp_lookups (tp_no, source_tables, created_at) VALUES \n";
$rows = [];
foreach ($records as $r) {
    $tp = str_replace("'", "''", $r->tp_no);
    $sources = str_replace("'", "''", $r->source_tables);
    $rows[] = "('$tp', '$sources', '{$r->created_at}')";
}

// Split into chunks of 1000 to avoid long strings/query limits if executed manually
$chunks = array_chunk($rows, 1000);
$finalSql = "";
foreach ($chunks as $chunk) {
    $finalSql .= "INSERT INTO tp_lookups (tp_no, source_tables, created_at) VALUES \n";
    $finalSql .= implode(",\n", $chunk) . ";\n\n";
}

file_put_contents('tp_lookups_inserts.sql', $finalSql);
echo "SQL file generated: tp_lookups_inserts.sql\n";
echo "Total records: " . count($records) . "\n";
