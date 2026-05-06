<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Searching for Mother Apps with > 1 Approved SubApps...\n";

$results = DB::connection('sqlsrv')->select("
    SELECT m.id, m.fileno, COUNT(s.id) as sub_count
    FROM mother_applications m
    JOIN subapplications s ON s.main_application_id = m.id
    WHERE s.planning_recommendation_status = 'Approved'
    AND (s.is_primary_the_owner = 1 OR s.is_primary_the_owner = '1')
    GROUP BY m.id, m.fileno
    HAVING COUNT(s.id) > 1
");

echo "Found " . count($results) . " matching mother apps.\n";

foreach ($results as $r) {
    echo "Mother App: {$r->fileno} (ID: {$r->id}) - Approved SubApps: {$r->sub_count}\n";

    // Detailed check of subapps
    $subs = DB::connection('sqlsrv')->table('subapplications')
        ->where('main_application_id', $r->id)
        ->where('planning_recommendation_status', 'Approved')
        ->get(['id', 'fileno', 'is_primary_the_owner', 'planning_recommendation_status']);

    foreach ($subs as $s) {
        echo "   SubApp: {$s->fileno} (ID: {$s->id}) \n";
        echo "     is_primary_the_owner (Raw): " . json_encode($s->is_primary_the_owner) . "\n";

        // Check if query matches
        $match = DB::connection('sqlsrv')->table('subapplications')
            ->where('id', $s->id)
            ->where('is_primary_the_owner', 1)
            ->exists();
        echo "     Matches where('is_primary_the_owner', 1): " . ($match ? "YES" : "NO") . "\n";
    }
}
