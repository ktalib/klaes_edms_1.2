<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$sourceFiles = ['IND-2021-13', 'IND-RC-1982-24'];
$tables = [
    'file_history_staging' => ['fileno', 'mlsFNo'],
    'pra' => ['fileno', 'mlsFNo'],
    'instrument_capture' => ['fileno', 'mlsFNo'],
    'registered_instruments' => ['fileno'],
    'CofO' => ['fileno'],
    'CofO_staging' => ['fileno']
];

$results = [];

foreach ($sourceFiles as $fileNo) {
    $results[$fileNo] = [];
    foreach ($tables as $table => $columns) {
        if (!Schema::connection('sqlsrv')->hasTable($table)) {
            $results[$fileNo][$table] = "Table not found";
            continue;
        }

        $query = DB::connection('sqlsrv')->table($table);
        $query->where(function($q) use ($columns, $fileNo) {
            foreach ($columns as $col) {
                if (Schema::connection('sqlsrv')->hasColumn($q->from, $col)) {
                    $q->orWhere($col, $fileNo);
                }
            }
        });
        
        $count = $query->count();
        $results[$fileNo][$table] = $count;
    }
}

echo json_encode($results, JSON_PRETTY_PRINT);
