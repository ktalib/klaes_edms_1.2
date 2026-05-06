<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Basic table check
$count = DB::connection('sqlsrv')->table('instrument_capture')->count();
echo "Total instrument_capture rows: {$count}" . PHP_EOL;

if ($count > 0) {
    $maxId = DB::connection('sqlsrv')->table('instrument_capture')->max('id');
    $minId = DB::connection('sqlsrv')->table('instrument_capture')->min('id');
    echo "ID range: {$minId} to {$maxId}" . PHP_EOL;

    // Check around 4073
    $exists = DB::connection('sqlsrv')->table('instrument_capture')->where('id', 4073)->exists();
    echo "id=4073 exists: " . ($exists ? 'YES' : 'NO') . PHP_EOL;
    
    // Nearest ids
    $near = DB::connection('sqlsrv')->selectOne("SELECT 
        (SELECT MAX(id) FROM instrument_capture WHERE id <= 4073) as below,
        (SELECT MIN(id) FROM instrument_capture WHERE id >= 4073) as above");
    echo "Nearest below: " . ($near->below ?? 'none') . ", nearest above: " . ($near->above ?? 'none') . PHP_EOL;
}

// Also check what the user's temp_fileno=TEMP-14946 maps to
echo PHP_EOL;
$tempRow = DB::connection('sqlsrv')->table('instrument_capture')
    ->where('temp_fileno', 'TEMP-14946')
    ->first(['id', 'temp_fileno', 'mlsFNo', 'prop_id', 'instrument_type']);
echo "TEMP-14946 in IC: " . ($tempRow ? json_encode($tempRow) : 'NULL') . PHP_EOL;

// Check if the dashboard visible row has source_capture_id=4073
// by looking at what ic.id would be for TEMP-14946
if ($tempRow) {
    echo "IC id for TEMP-14946: {$tempRow->id}" . PHP_EOL;
}

// Also check fileNumber for 4073
$fn = DB::connection('sqlsrv')->table('fileNumber')->where('id', 4073)->first(['id', 'mlsfNo', 'FileName']);
echo "fileNumber id=4073: " . ($fn ? json_encode($fn) : 'NULL') . PHP_EOL;
