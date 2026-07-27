<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$files = ['RES-2025-48', 'RES-2025-139', 'RES-2025-142'];
$temps = ['TEMP-106326', 'TEMP-106327', 'TEMP-106337', 'TEMP-106338', 'TEMP-106339', 'TEMP-106340'];

echo "=== QUERYING BY MLS FILE NUMBERS ===\n";
foreach ($files as $file) {
    echo "--- File: $file ---\n";
    $rows = DB::connection('sqlsrv')->table('pra')
        ->where('mlsFNo', $file)
        ->orWhere('fileno', $file)
        ->select(['id', 'prop_id', 'mlsFNo', 'fileno', 'temp_fileno', 'instrument_type', 'transaction_type', 'Grantor', 'Grantee', 'party_1', 'party_2', 'op_serial_number', 'system_source', 'created_at'])
        ->get();
    foreach ($rows as $row) {
        echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
    }
}

echo "\n=== QUERYING BY TEMP FILE NUMBERS ===\n";
foreach ($temps as $temp) {
    echo "--- Temp File: $temp ---\n";
    $rows = DB::connection('sqlsrv')->table('pra')
        ->where('temp_fileno', $temp)
        ->select(['id', 'prop_id', 'mlsFNo', 'fileno', 'temp_fileno', 'instrument_type', 'transaction_type', 'Grantor', 'Grantee', 'party_1', 'party_2', 'op_serial_number', 'system_source', 'created_at'])
        ->get();
    foreach ($rows as $row) {
        echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
    }
}

echo "\n=== QUERYING FROM mls_file_no FOR THESE FILES ===\n";
foreach ($files as $file) {
    echo "--- mls_file_no for $file ---\n";
    $rows = DB::connection('sqlsrv')->table('mls_file_no')
        ->where('full_file_number', $file)
        ->select(['id', 'full_file_number', 'temp_file_number', 'customer_type', 'land_use', 'created_at'])
        ->get();
    foreach ($rows as $row) {
        echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
    }
}
