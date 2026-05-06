<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$results = DB::connection('sqlsrv')->select("
    SELECT transaction_type, COUNT(*) as cnt 
    FROM pic 
    GROUP BY transaction_type 
    ORDER BY cnt DESC
");

echo "=== Transaction Types in pic ===\n\n";
$total = 0;
foreach ($results as $r) {
    $type = $r->transaction_type ?? '(NULL)';
    echo str_pad($type, 45) . number_format($r->cnt) . "\n";
    $total += $r->cnt;
}
echo "\nTotal: " . number_format($total) . "\n";
