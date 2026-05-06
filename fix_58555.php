<?php
/**
 * Fix Prop ID 58555: Plot TGS-217 (ToT) vs Plot 1290B (OP)
 */
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');

// 1. Isolate the unrelated Plot 1290B record (ID: 2345)
// Give it a new temp fileno and prop_id
echo "Isolating Plot 1290B (ID: 2345)...\n";
$newSeqId = $db->table('temp_fileno_sequence')->insertGetId([
    'created_at' => now(),
    'updated_at' => now(),
    'is_used' => 1
]);
$isolatedTempFileNo = 'TEMP-' . str_pad((string)$newSeqId, 7, '0', STR_PAD_LEFT);

$isolatedPropId = '80000820'; // A fresh ID for the isolated property

$db->table('instrument_capture')->where('id', 2345)->update([
    'temp_fileno' => $isolatedTempFileNo,
    'prop_id' => $isolatedPropId,
    'updated_at' => now()
]);

// 2. Create the correct OP for Plot TGS-217
echo "Creating correct OP for Plot TGS-217...\n";
$icId = $db->table('instrument_capture')->insertGetId([
    'instrument_type' => 'Occupancy Permit (OP)',
    'op_type' => 'OP Resettlement',
    'op_serial_number' => '16',
    'registration_number' => '3/3/247',
    'volume_no' => '247',
    'page_no' => '3',
    'serial_no' => '3',
    'party_1_name' => 'KANO STATE GOVERNMENT',
    'party_2_name' => 'HAJIYA UMMA AND OHTERS',
    'plot_number' => 'TGS-217',
    'district' => 'AKK TAMBURAWA',
    'property_description' => 'Plot TGS-217, AKK TAMBURAWA, Dawakin Kudu, Kano',
    'property_location' => 'Plot TGS-217, AKK TAMBURAWA, Dawakin Kudu, Kano',
    'land_use' => 'INDUSTRIAL',
    'land_use_id' => '3',
    'purpose' => 'INDUSTRIAL',
    'tp_no' => 'TP/K/382',
    'temp_fileno' => 'TEMP-36605',
    'prop_id' => '58555',
    'created_at' => now(),
    'updated_at' => now(),
    'created_by' => '101524',
    'is_deed_registered' => 1
]);

echo "Created OP (ID: $icId) for Plot TGS-217.\n";

// 3. Link the ToT (ID: 139664) to this new OP
echo "Linking ToT (ID: 139664) to its new parent...\n";
$db->table('pra')->where('id', 139664)->update([
    'parent_prop_id' => '58555',
    'party_1' => 'HAJIYA UMMA AND OHTERS',
    'Grantor' => 'HAJIYA UMMA AND OHTERS',
    'updated_at' => now()
]);

echo "Fix complete.\n";
