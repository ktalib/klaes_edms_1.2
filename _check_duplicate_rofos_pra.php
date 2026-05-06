<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking for duplicate Right of Occupancy (R of O) records in the PRA table...\n\n";

try {
    $duplicates = DB::connection('sqlsrv')
        ->table('pra')
        ->select('prop_id', DB::raw("COALESCE(fileno, mlsFNo) as file_number"), DB::raw('COUNT(*) as total_count'))
        ->where(function($q) {
            $q->where('transaction_type', 'like', '%of occ%')
              ->orWhere('transaction_type', 'like', '%R of O%')
              ->orWhere('transaction_type', 'like', '%R. of O.%')
              ->orWhere('transaction_type', 'like', '%R Of O%')
              ->orWhere('transaction_type', 'like', '%R0fO%')
              ->orWhere('transaction_type', 'like', '%ROFO%');
        })
        ->where(function($q) {
            $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
        })
        ->groupBy('prop_id', 'fileno', 'mlsFNo')
        ->havingRaw('COUNT(*) > 1')
        ->get();

    echo "Total files with duplicate R of Os: " . count($duplicates) . "\n";
    echo str_repeat("-", 60) . "\n";
    printf("%-20s | %-20s | %s\n", "Prop ID", "File Number", "Count");
    echo str_repeat("-", 60) . "\n";

    $displayed = 0;
    foreach($duplicates as $dup) {
        printf("%-20s | %-20s | %d\n", $dup->prop_id ?? 'N/A', $dup->file_number ?? 'N/A', $dup->total_count);
        $displayed++;
        if ($displayed >= 50) {
            echo "... and " . (count($duplicates) - 50) . " more matches hiding.\n";
            break;
        }
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
