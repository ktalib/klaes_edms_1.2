<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$motherId = 3; // From logs: CON-COM-2010-258
echo "Inspecting SubApplications for MotherApp ID: $motherId\n";

$subApps = DB::connection('sqlsrv')->table('subapplications')
    ->where('main_application_id', $motherId)
    ->get();

echo "Found " . $subApps->count() . " records.\n";

foreach ($subApps as $subApp) {
    echo "ID: {$subApp->id}, FileNo: {$subApp->fileno}\n";
    echo "  -> is_primary_the_owner: " . json_encode($subApp->is_primary_the_owner) . "\n";
    echo "  -> Status: {$subApp->planning_recommendation_status}\n";
}

echo "\nChecking ALL subapps with likely primary owner flag:\n";
$likely = DB::connection('sqlsrv')->table('subapplications')
    ->where('is_primary_the_owner', '!=', 0)
    ->whereNotNull('is_primary_the_owner')
    ->limit(10)
    ->get(['id', 'fileno', 'is_primary_the_owner']);

foreach ($likely as $l) {
    echo "ID: {$l->id}, Val: " . json_encode($l->is_primary_the_owner) . "\n";
}
