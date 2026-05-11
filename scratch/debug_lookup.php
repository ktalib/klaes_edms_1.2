<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\DB;

$fileNo = 'RES-2023-6430';
echo "Searching for $fileNo in 'grouping' table...\n";

$record = DB::connection('sqlsrv')->table('grouping')
    ->where('awaiting_fileno', $fileNo)
    ->first();

if ($record) {
    echo "Found record in 'grouping'!\n";
    print_r($record);
} else {
    echo "NOT FOUND in 'grouping'.\n";
    
    // Try normalized
    $normalized = str_replace(['-', '/', ' '], '', $fileNo);
    echo "Trying normalized search for $normalized...\n";
    
    $record = DB::connection('sqlsrv')->table('grouping')
        ->whereRaw("REPLACE(REPLACE(REPLACE(awaiting_fileno, '-', ''), '/', ''), ' ', '') = ?", [$normalized])
        ->first();
        
    if ($record) {
        echo "Found record using normalized search!\n";
        print_r($record);
    } else {
        echo "NOT FOUND in 'grouping' even with normalization.\n";
        
        // Search in other tables
        $tables = ['gkn_grouping', 'lpkn_grouping', 'miscs_kn_grouping', 'sltr_grouping', 'sit_grouping', 'dciv_grouping', 'kangis_grouping', 'kn_grouping'];
        foreach ($tables as $table) {
            echo "Searching in $table...\n";
            $col = str_contains($table, '_') ? str_replace('_grouping', '_awaiting_fileno', $table) : 'awaiting_fileno';
            if ($table === 'miscs_kn_grouping') $col = 'miscs_kn_awaiting_fileno';
            
            $record = DB::connection('sqlsrv')->table($table)
                ->where($col, $fileNo)
                ->first();
            if ($record) {
                echo "Found in $table!\n";
                print_r($record);
                break;
            }
        }
    }
}
