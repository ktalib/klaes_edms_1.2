<?php
/**
 * Fix Prop ID 59411: Plot 4111 (ToT) vs Plot 1982 (OP)
 */
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');

// 1. Isolate the unrelated Plot 1982 record (ID: 2428)
echo "Isolating Plot 1982 (ID: 2428)...\n";
$newSeqId = $db->table('temp_fileno_sequence')->insertGetId([
    'created_at' => now(),
    'updated_at' => now(),
    'is_used' => 1
]);
$isolatedTempFileNo = 'TEMP-' . str_pad((string)$newSeqId, 7, '0', STR_PAD_LEFT);
$isolatedPropId = '80000840';

$db->table('instrument_capture')->where('id', 2428)->update([
    'temp_fileno' => $isolatedTempFileNo,
    'prop_id' => $isolatedPropId,
    'updated_at' => now()
]);

// 2. Create the correct OP for Plot 4111
echo "Creating correct OP for Plot 4111...\n";
$icId = $db->table('instrument_capture')->insertGetId([
    'instrument_type' => 'Occupancy Permit (OP)',
    'op_type' => 'OP Direct Allocation',
    'op_serial_number' => '546',
    'registration_number' => '0/0/0',
    'volume_no' => '0',
    'page_no' => '0',
    'serial_no' => '0',
    'party_1_name' => 'KANO STATE GOVERNMENT',
    'party_2_name' => 'NURA SAID',
    'plot_number' => '4111',
    'district' => 'KUDDIDIFAWA EXTENSION',
    'property_description' => 'Plot 4111, KUDDIDIFAWA EXTENSION, Ungogo, Kano',
    'property_location' => 'Plot 4111, KUDDIDIFAWA EXTENSION, Ungogo, Kano',
    'land_use' => 'RESIDENTIAL',
    'land_use_id' => '1',
    'purpose' => 'RESIDENTIAL',
    'tp_no' => 'TP/K/215M',
    'temp_fileno' => 'TEMP-36509',
    'prop_id' => '59411',
    'created_at' => now(),
    'updated_at' => now(),
    'created_by' => '101524',
    'is_deed_registered' => 1
]);

echo "Created OP (ID: $icId) for Plot 4111.\n";

// 3. Link the ToT (ID: 139635) to this new OP
echo "Linking ToT (ID: 139635) to its new parent...\n";
$db->table('pra')->where('id', 139635)->update([
    'parent_prop_id' => '59411',
    'party_1' => 'NURA SAID',
    'Grantor' => 'NURA SAID',
    'updated_at' => now()
]);

echo "Fix complete.\n";
