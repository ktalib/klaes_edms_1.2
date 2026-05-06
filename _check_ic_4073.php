<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$icExists = DB::connection('sqlsrv')->table('instrument_capture')->where('id', 4073)->exists();
echo "instrument_capture id=4073: " . ($icExists ? 'EXISTS' : 'NOT FOUND') . PHP_EOL;

$fnExists = DB::connection('sqlsrv')->table('fileNumber')->where('id', 4073)->exists();
echo "fileNumber id=4073: " . ($fnExists ? 'EXISTS' : 'NOT FOUND') . PHP_EOL;

// Check what the dashboard UNION actually returns for source_capture_id around this value
$sample = DB::connection('sqlsrv')->table('instrument_capture')
    ->where('instrument_type', 'Occupancy Permit (OP)')
    ->where(function($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })
    ->orderByDesc('id')
    ->take(5)
    ->get(['id', 'mlsFNo', 'temp_fileno', 'prop_id']);
echo "Latest 5 instrument_capture OPs:" . PHP_EOL;
foreach ($sample as $r) {
    echo "  ic.id={$r->id} mlsFNo={$r->mlsFNo} temp={$r->temp_fileno} prop_id={$r->prop_id}" . PHP_EOL;
}

// Check if the dashboard query Part 1 might be outputting fn.id as source_capture_id
// The UNION has: ISNULL(CAST(ic.id AS NVARCHAR(50)), '') as source_capture_id
// and:           ISNULL(CAST(fn.id AS NVARCHAR(50)), '') as id
// In buildCaptureEditIdentifier, after the fix: pra_id -> fn.id -> ic-{source_capture_id}
// If fn.id is empty for a row, we'd fall to ic-{source_capture_id}
// But if fn.id IS present but equals 4073, we'd use fn.id=4073 (numeric route, not ic-)
// So ic-4073 means fn.id was empty and source_capture_id=4073
// Let's check if ic.id=4073 exists
echo PHP_EOL;
$ic = DB::connection('sqlsrv')->table('instrument_capture')->where('id', 4073)->first(['id','mlsFNo','temp_fileno','instrument_type']);
echo "instrument_capture row 4073: " . ($ic ? json_encode($ic) : 'NULL') . PHP_EOL;

// Check max id range
$maxId = DB::connection('sqlsrv')->table('instrument_capture')->max('id');
echo "Max instrument_capture id: {$maxId}" . PHP_EOL;
