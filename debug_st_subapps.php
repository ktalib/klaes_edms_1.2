<?php

use Illuminate\Support\Facades\DB;
use App\Models\SubApplication;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking SubApplications with is_primary_the_owner = 1...\n";

$subApps = DB::connection('sqlsrv')->table('subapplications')
    ->where('is_primary_the_owner', 1)
    ->get(['id', 'fileno', 'planning_recommendation_status', 'main_application_id']);

echo "Found " . $subApps->count() . " records.\n";

foreach ($subApps as $subApp) {
    echo "ID: {$subApp->id}, FileNo: {$subApp->fileno}, Status: {$subApp->planning_recommendation_status}, MainAppID: {$subApp->main_application_id}\n";

    // Check main application existence
    $mother = DB::connection('sqlsrv')->table('mother_applications')->where('id', $subApp->main_application_id)->first();
    echo "  -> Mother Found: " . ($mother ? "Yes (ID: {$mother->id})" : "No") . "\n";

    // Check existing registration
    $reg = DB::connection('sqlsrv')->table('registered_instruments')
        ->where('StFileNo', $subApp->fileno)
        ->where('instrument_type', 'ST Fragmentation')
        ->first();

    echo "  -> Registered: " . ($reg ? "Yes (ID: {$reg->id}, Status: {$reg->status})" : "No") . "\n";
}
