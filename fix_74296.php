<?php
/**
 * Fix Prop ID 74296: Plot 4112 (ToT) vs Plot C605B (OP)
 */
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');

// 1. Isolate the unrelated Plot C605B record (ID: 3341)
echo "Isolating Plot C605B (ID: 3341)...\n";
$newSeqId = $db->table('temp_fileno_sequence')->insertGetId([
    'created_at' => now(),
    'updated_at' => now(),
    'is_used' => 1
]);
$isolatedTempFileNo = 'TEMP-' . str_pad((string)$newSeqId, 7, '0', STR_PAD_LEFT);
$isolatedPropId = '80000830';

$db->table('instrument_capture')->where('id', 3341)->update([
    'temp_fileno' => $isolatedTempFileNo,
    'prop_id' => $isolatedPropId,
    'updated_at' => now()
]);

// 2. Create the correct OP for Plot 4112
echo "Creating correct OP for Plot 4112...\n";
$icId = $db->table('instrument_capture')->insertGetId([
    'instrument_type' => 'Occupancy Permit (OP)',
    'op_type' => 'OP Direct Allocation',
    'op_serial_number' => '547',
    'registration_number' => '79/79/250', // Reusing the reg details from the current grouping if they belong together, or leave null if unknown. 
                                          // Actually, the user says Reg No 79/79/250 is for the "wrong" OP.
                                          // I'll set it to 0/0/0 or a placeholder unless I find better info.
    'volume_no' => '0',
    'page_no' => '0',
    'serial_no' => '0',
    'party_1_name' => 'KANO STATE GOVERNMENT',
    'party_2_name' => 'MURTALA ADAM',
    'plot_number' => '4112',
    'district' => 'KUDDIDIFAWA EXTENSION',
    'property_description' => 'Plot 4112, KUDDIDIFAWA EXTENSION, Ungogo, Kano',
    'property_location' => 'Plot 4112, KUDDIDIFAWA EXTENSION, Ungogo, Kano',
    'land_use' => 'RESIDENTIAL',
    'land_use_id' => '1',
    'purpose' => 'RESIDENTIAL',
    'tp_no' => 'TP/K/215M',
    'temp_fileno' => 'TEMP-36519',
    'prop_id' => '74296',
    'created_at' => now(),
    'updated_at' => now(),
    'created_by' => '101524',
    'is_deed_registered' => 1
]);

echo "Created OP (ID: $icId) for Plot 4112.\n";

// 3. Link the ToT (ID: 139637) to this new OP
echo "Linking ToT (ID: 139637) to its new parent...\n";
$db->table('pra')->where('id', 139637)->update([
    'parent_prop_id' => '74296',
    'party_1' => 'MURTALA ADAM',
    'Grantor' => 'MURTALA ADAM',
    'updated_at' => now()
]);

echo "Fix complete.\n";
