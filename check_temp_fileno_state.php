<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$db = DB::connection('sqlsrv');

// Check current identity value
$ident = $db->select("SELECT IDENT_CURRENT('temp_fileno_sequence') as current_identity");
echo "Current identity seed: " . $ident[0]->current_identity . PHP_EOL;

// Check existing rows
$rows = $db->table('temp_fileno_sequence')->orderBy('id')->get();
echo "Rows in temp_fileno_sequence: " . $rows->count() . PHP_EOL;
foreach($rows as $r) {
    echo "  id={$r->id} is_used={$r->is_used} created_by={$r->created_by}" . PHP_EOL;
}

// Check TEMP- in pra
$praTemps = $db->table('pra')->where('temp_fileno', 'like', 'TEMP-%')->get(['id','temp_fileno']);
echo "\nTEMP- in pra: " . $praTemps->count() . PHP_EOL;
foreach($praTemps->take(10) as $r) echo "  id={$r->id} temp_fileno={$r->temp_fileno}" . PHP_EOL;

// Check TEMP- in pic
$picTemps = $db->table('pic')->where('temp_fileno', 'like', 'TEMP-%')->get(['id','temp_fileno']);
echo "\nTEMP- in pic: " . $picTemps->count() . PHP_EOL;
foreach($picTemps->take(10) as $r) echo "  id={$r->id} temp_fileno={$r->temp_fileno}" . PHP_EOL;

// Check TEMP- in instrument_capture
$icTemps = $db->table('instrument_capture')->where('temp_fileno', 'like', 'TEMP-%')->get(['id','temp_fileno']);
echo "\nTEMP- in instrument_capture: " . $icTemps->count() . PHP_EOL;
foreach($icTemps->take(10) as $r) echo "  id={$r->id} temp_fileno={$r->temp_fileno}" . PHP_EOL;

// Check max TEMP numbers across tables
$maxPra = $db->table('pra')->where('temp_fileno', 'like', 'TEMP-%')
    ->selectRaw("MAX(TRY_CONVERT(INT, REPLACE(CAST(temp_fileno AS VARCHAR), 'TEMP-', ''))) as max_num")->first();
echo "\nMax TEMP number in pra: " . ($maxPra->max_num ?? 'none') . PHP_EOL;

$maxPic = $db->table('pic')->where('temp_fileno', 'like', 'TEMP-%')
    ->selectRaw("MAX(TRY_CONVERT(INT, REPLACE(CAST(temp_fileno AS VARCHAR), 'TEMP-', ''))) as max_num")->first();
echo "Max TEMP number in pic: " . ($maxPic->max_num ?? 'none') . PHP_EOL;

$maxIc = $db->table('instrument_capture')->where('temp_fileno', 'like', 'TEMP-%')
    ->selectRaw("MAX(TRY_CONVERT(INT, REPLACE(CAST(temp_fileno AS VARCHAR), 'TEMP-', ''))) as max_num")->first();
echo "Max TEMP number in instrument_capture: " . ($maxIc->max_num ?? 'none') . PHP_EOL;

// Check also mlsFNo for TEMP-
$maxPraMls = $db->table('pra')->where('mlsFNo', 'like', 'TEMP-%')
    ->selectRaw("MAX(TRY_CONVERT(INT, REPLACE(CAST(mlsFNo AS VARCHAR), 'TEMP-', ''))) as max_num")->first();
echo "\nMax TEMP in pra.mlsFNo: " . ($maxPraMls->max_num ?? 'none') . PHP_EOL;

$maxPicMls = $db->table('pic')->where('mlsFNo', 'like', 'TEMP-%')
    ->selectRaw("MAX(TRY_CONVERT(INT, REPLACE(CAST(mlsFNo AS VARCHAR), 'TEMP-', ''))) as max_num")->first();
echo "Max TEMP in pic.mlsFNo: " . ($maxPicMls->max_num ?? 'none') . PHP_EOL;

$maxIcMls = $db->table('instrument_capture')->where('mlsFNo', 'like', 'TEMP-%')
    ->selectRaw("MAX(TRY_CONVERT(INT, REPLACE(CAST(mlsFNo AS VARCHAR), 'TEMP-', ''))) as max_num")->first();
echo "Max TEMP in instrument_capture.mlsFNo: " . ($maxIcMls->max_num ?? 'none') . PHP_EOL;
