<?php
/**
 * Check all columns used by captureEdit() query across 3 tables:
 *   fileNumber, mls_file_no, instrument_capture
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;

$tables = [
    'fileNumber' => [
        'id', 'mlsfNo', 'tracking_id', 'FileName',
        'plot_no', 'tp_no', 'lga', 'location', 'temp_fileno',
    ],
    'mls_file_no' => [
        'id', 'tracking_id', 'source_instrument_capture_id', 'source_pra_id',
        'land_use', 'customer_type',
    ],
    'instrument_capture' => [
        'id', 'instrument_type', 'op_type', 'op_serial_number',
        'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'fileno', 'temp_fileno',
        'party_1_name', 'party_1_address', 'party_1_phone',
        'party_1_state', 'party_1_lga', 'party_1_district',
        'party_2_name', 'party_2_address', 'party_2_phone',
        'party_2_state', 'party_2_lga', 'party_2_district',
        'land_use', 'purpose', 'plot_number', 'tp_no',
        'property_description', 'lga', 'district',
        'serial_no', 'page_no', 'volume_no', 'registration_number',
        'reg_date', 'reg_time', 'transaction_date', 'instrument_date', 'entry_date',
        'duration', 'root_reg_number',
        'solicitor_name', 'solicitor_address',
        'survey_plan_no', 'lpkn_no', 'size',
        'created_at',
    ],
    'pra' => [
        'id', 'mlsFNo', 'fileno', 'system_source',
        'op_type', 'op_serial_number',
        'Grantor', 'Grantee', 'party_1', 'party_2',
        'party_1_phone', 'party_1_address',
        'party_2_phone', 'party_2_address',
        'land_use', 'purpose', 'plot_no', 'tp_no',
        'location', 'property_description',
        'lga', 'lgsaOrCity', 'district',
        'created_at', 'prop_id', 'temp_fileno',
    ],
];

echo "=== captureEdit() Column Check ===\n\n";

foreach ($tables as $table => $columns) {
    $exists = Schema::connection('sqlsrv')->hasTable($table);
    echo "TABLE: {$table} " . ($exists ? '✓ EXISTS' : '✗ MISSING') . "\n";
    if (!$exists) { echo "\n"; continue; }

    $missing = [];
    $found = [];
    foreach ($columns as $col) {
        if (Schema::connection('sqlsrv')->hasColumn($table, $col)) {
            $found[] = $col;
        } else {
            $missing[] = $col;
        }
    }

    echo "  FOUND (" . count($found) . "): " . implode(', ', $found) . "\n";
    if ($missing) {
        echo "  *** MISSING (" . count($missing) . "): " . implode(', ', $missing) . "\n";
    }
    echo "\n";
}
