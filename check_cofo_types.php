<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$results = DB::connection('sqlsrv')->select("
    SELECT transaction_type, COUNT(*) as cnt 
    FROM CofO_staging 
    GROUP BY transaction_type 
    ORDER BY cnt DESC
");

echo "=== Transaction Types in CofO_staging ===\n\n";
foreach ($results as $r) {
    echo str_pad($r->transaction_type ?? '(NULL)', 45) . number_format($r->cnt) . "\n";
}
echo "\nTotal: " . number_format(array_sum(array_map(function($r) { return $r->cnt; }, $results))) . "\n";
