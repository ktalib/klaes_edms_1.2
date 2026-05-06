<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$db = DB::connection('sqlsrv');

// Check for TEMP-11493 records in instrument_capture
echo "=== instrument_capture records for TEMP-11493 ===" . PHP_EOL;
$records = $db->table('instrument_capture')
    ->where(function($q) {
        $q->where('temp_fileno', 'TEMP-11493')
          ->orWhere('mlsFNo', 'TEMP-11493');
    })
    ->select('id', 'temp_fileno', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'instrument_type', 'party_1_name', 'party_2_name', 'registration_number', 'volume_no', 'page_no', 'serial_no', 'prop_id', 'is_deleted', 'created_at')
    ->get();

echo "Count: " . $records->count() . PHP_EOL;
foreach ($records as $r) {
    echo json_encode($r, JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL;
}

// Also check the view query with LEFT JOIN to deed_registrations
echo "=== View query (with deed_registrations join) for TEMP-11493 ===" . PHP_EOL;
$viewRecords = $db->table('instrument_capture as ic')
    ->leftJoin('deed_registrations as dr', function($join) {
        $join->on('ic.registration_number', '=', 'dr.registration_number')
             ->on('ic.instrument_type', '=', 'dr.instrument_type');
    })
    ->where(function($q) {
        $q->where('ic.temp_fileno', 'TEMP-11493')
          ->orWhere('ic.mlsFNo', 'TEMP-11493');
    })
    ->where(function($q) {
        $q->where('ic.is_deleted', 0)
          ->orWhereNull('ic.is_deleted');
    })
    ->select('ic.id', 'ic.temp_fileno', 'ic.registration_number', 'ic.instrument_type',
        'ic.party_1_name', 'ic.volume_no', 'ic.page_no', 'ic.serial_no',
        'dr.id as dr_id', 'dr.volume_no as dr_volume', 'dr.page_no as dr_page', 'dr.serial_no as dr_serial',
        'dr.registration_number as dr_reg_number', 'dr.instrument_type as dr_instrument_type')
    ->get();

echo "Count with join: " . $viewRecords->count() . PHP_EOL;
foreach ($viewRecords as $r) {
    echo json_encode($r, JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL;
}

// Check deed_registrations for matching records
echo "=== deed_registrations matching the instrument_capture registration_number ===" . PHP_EOL;
$icRegNums = $records->pluck('registration_number')->filter()->unique();
foreach ($icRegNums as $regNum) {
    echo "Looking for registration_number = '{$regNum}' in deed_registrations..." . PHP_EOL;
    $drRecords = $db->table('deed_registrations')
        ->where('registration_number', $regNum)
        ->select('id', 'registration_number', 'instrument_type', 'volume_no', 'page_no', 'serial_no')
        ->get();
    echo "Found: " . $drRecords->count() . PHP_EOL;
    foreach ($drRecords as $dr) {
        echo json_encode($dr, JSON_PRETTY_PRINT) . PHP_EOL;
    }
    echo PHP_EOL;
}
