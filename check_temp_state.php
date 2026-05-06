<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('sqlsrv');

echo "=== Current Temp File Number State ===" . PHP_EOL;

// Count temp_fileno_sequence rows
try {
    $seqCount = $db->table('temp_fileno_sequence')->count();
    $maxSeqId = $db->table('temp_fileno_sequence')->max('id');
    echo "temp_fileno_sequence: {$seqCount} rows, max id: {$maxSeqId}" . PHP_EOL;
} catch(\Exception $e) {
    echo "temp_fileno_sequence: " . $e->getMessage() . PHP_EOL;
}

// Count TEMP- records in pra
try {
    $praTotal = $db->table('pra')->count();
    $praTemp = $db->table('pra')->where('temp_fileno', 'like', 'TEMP-%')->count();
    echo "pra: {$praTemp} TEMP- records out of {$praTotal} total" . PHP_EOL;
} catch(\Exception $e) {
    echo "pra: " . $e->getMessage() . PHP_EOL;
}

// Count TEMP- records in pic
try {
    $picTotal = $db->table('pic')->count();
    $picTemp = $db->table('pic')->where('temp_fileno', 'like', 'TEMP-%')->count();
    echo "pic: {$picTemp} TEMP- records out of {$picTotal} total" . PHP_EOL;
} catch(\Exception $e) {
    echo "pic: " . $e->getMessage() . PHP_EOL;
}

// Count TEMP- records in instrument_capture
try {
    $icTotal = $db->table('instrument_capture')->count();
    $icTemp = $db->table('instrument_capture')->where('temp_fileno', 'like', 'TEMP-%')->count();
    echo "instrument_capture: {$icTemp} TEMP- records out of {$icTotal} total" . PHP_EOL;
} catch(\Exception $e) {
    echo "instrument_capture: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "Done." . PHP_EOL;
