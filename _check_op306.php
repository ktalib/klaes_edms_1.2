<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

$serial = '306';
echo "Checking instrument_capture for op_serial_number = $serial\n";

$ic = DB::connection('sqlsrv')->table('instrument_capture')
    ->where('op_serial_number', $serial)
    ->select('id','op_serial_number','instrument_type','temp_fileno','party_1_name')
    ->get();
echo "instrument_capture simple WHERE: {$ic->count()}\n";
foreach($ic as $r) { print_r((array)$r); }

echo "\nChecking with UPPER/LTRIM/RTRIM/CAST...\n";
$ic2 = DB::connection('sqlsrv')->table('instrument_capture')
    ->whereRaw('UPPER(LTRIM(RTRIM(CAST(op_serial_number AS NVARCHAR(100))))) = ?', [$serial])
    ->select('id','op_serial_number','instrument_type','temp_fileno')
    ->get();
echo "CAST results: {$ic2->count()}\n";
foreach($ic2 as $r) { print_r((array)$r); }

echo "\nChecking PRA...\n";
$pra = DB::connection('sqlsrv')->table('pra')
    ->whereRaw('UPPER(LTRIM(RTRIM(CAST(op_serial_number AS NVARCHAR(100))))) = ?', [$serial])
    ->select('id','op_serial_number','instrument_type')
    ->get();
echo "PRA results: {$pra->count()}\n";
foreach($pra as $r) { print_r((array)$r); }

// Also check column type
echo "\nop_serial_number column type in instrument_capture:\n";
$colInfo = DB::connection('sqlsrv')->select("
    SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'instrument_capture' AND COLUMN_NAME = 'op_serial_number'
");
foreach($colInfo as $c) { print_r((array)$c); }
