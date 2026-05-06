<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$db = DB::connection('sqlsrv');

// Check all duplicate temp_fileno in instrument_capture
echo "=== Duplicate temp_fileno in instrument_capture ===" . PHP_EOL;
$dupes = $db->select("
    SELECT temp_fileno, COUNT(*) as cnt 
    FROM instrument_capture
    WHERE temp_fileno IS NOT NULL AND temp_fileno LIKE 'TEMP-%'
    AND (is_deleted = 0 OR is_deleted IS NULL)
    GROUP BY temp_fileno
    HAVING COUNT(*) > 1
    ORDER BY temp_fileno
");

echo "Total duplicate temp_filenos: " . count($dupes) . PHP_EOL;
foreach ($dupes as $d) {
    echo "  {$d->temp_fileno} => {$d->cnt} records" . PHP_EOL;
}

// Detail for each duplicate
foreach ($dupes as $d) {
    echo PHP_EOL . "--- Detail for {$d->temp_fileno} ---" . PHP_EOL;
    $rows = $db->table('instrument_capture')
        ->where('temp_fileno', $d->temp_fileno)
        ->where(function($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
        ->select('id', 'temp_fileno', 'instrument_type', 'registration_number', 'party_1_name', 'party_2_name', 'prop_id', 'op_serial_number', 'created_at')
        ->orderBy('id')
        ->get();
    foreach ($rows as $r) {
        echo "  id={$r->id} | reg={$r->registration_number} | type={$r->instrument_type} | p1={$r->party_1_name} | p2={$r->party_2_name} | prop_id={$r->prop_id} | op_serial={$r->op_serial_number} | created={$r->created_at}" . PHP_EOL;
    }
}
