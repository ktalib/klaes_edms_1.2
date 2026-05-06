<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Numeric max prop_id
$maxPropId = DB::connection('sqlsrv')->table('pra')
    ->selectRaw("MAX(TRY_CONVERT(bigint, prop_id)) as max_val")
    ->value('max_val');
echo "Max numeric prop_id: {$maxPropId}\n";

// Max temp number
$maxTemp = DB::connection('sqlsrv')->table('pra')
    ->whereNotNull('temp_fileno')
    ->where('temp_fileno', 'LIKE', 'TEMP-%')
    ->selectRaw("MAX(TRY_CONVERT(int, REPLACE(temp_fileno, 'TEMP-', ''))) as max_num")
    ->value('max_num');
echo "Max temp number: {$maxTemp}\n";

// Also check instrument_capture for max prop_id
$maxIcPropId = DB::connection('sqlsrv')->table('instrument_capture')
    ->selectRaw("MAX(TRY_CONVERT(bigint, prop_id)) as max_val")
    ->value('max_val');
echo "Max numeric prop_id in instrument_capture: {$maxIcPropId}\n";

// Check max temp in instrument_capture
$maxIcTemp = DB::connection('sqlsrv')->table('instrument_capture')
    ->whereNotNull('temp_fileno')
    ->where('temp_fileno', 'LIKE', 'TEMP-%')
    ->selectRaw("MAX(TRY_CONVERT(int, REPLACE(temp_fileno, 'TEMP-', ''))) as max_num")
    ->value('max_num');
echo "Max temp number in instrument_capture: {$maxIcTemp}\n";

// Verify the new values won't collide
$newPropId = max($maxPropId, $maxIcPropId) + 1;
$newTemp = max($maxTemp, $maxIcTemp) + 1;
echo "\nSuggested new prop_id: {$newPropId}\n";
echo "Suggested new temp_fileno: TEMP-{$newTemp}\n";
