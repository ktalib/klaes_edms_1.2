<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking ALL Approved SubApplications...\n";

$approved = DB::connection('sqlsrv')->table('subapplications')
    ->where('planning_recommendation_status', 'Approved')
    ->get(['id', 'fileno', 'is_primary_the_owner']);

echo "Found " . $approved->count() . " approved records.\n";

$countPrimary = 0;
foreach ($approved as $s) {
    echo "ID: {$s->id}, File: {$s->fileno}, OwnerFlag: " . json_encode($s->is_primary_the_owner) . "\n";
    if ($s->is_primary_the_owner == 1) {
        $countPrimary++;
    }
}
echo "Count with is_primary_the_owner == 1: $countPrimary\n";
