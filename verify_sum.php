<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$query = DB::connection('sqlsrv')->table('Rack_Shelf_Labels')
    ->where('assigned', 'LIKE', '%REF:ALL-UNASSIGNED%');

try {
    // Log the generated SQL for the sum
    $sqlForSum = $query->clone()->selectRaw("SUM(CAST(ISNULL(NULLIF(counter, ''), '0') AS INT)) as aggregate")->toSql();
    echo "SQL for SUM: {$sqlForSum}\n";

    // Attempt the sum using the same logic as the controller
    $totalUsed = $query->sum(DB::raw("CAST(ISNULL(NULLIF(counter, ''), '0') AS INT)"));
    echo "Verification Success! Sum of counter: {$totalUsed}\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
