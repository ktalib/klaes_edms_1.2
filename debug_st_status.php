<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking Distinct Statuses in SubApplications...\n";

$statuses = DB::connection('sqlsrv')->table('subapplications')
    ->select('planning_recommendation_status')
    ->distinct()
    ->get();

foreach ($statuses as $s) {
    echo "Status: '" . $s->planning_recommendation_status . "'\n";
}
