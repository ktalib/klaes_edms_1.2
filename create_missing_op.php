<?php
/**
 * Create a missing OP for Plot C-97 and link it to the ToT.
 */
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');

// 1. Generate a new temp file number
$tempId = $db->table('temp_fileno_sequence')->insertGetId([
    'created_at' => now(),
    'updated_at' => now(),
    'is_used' => 1
]);
$newTempFileNo = 'TEMP-' . str_pad((string)$tempId, 7, '0', STR_PAD_LEFT);

echo "Generated Temp File No: $newTempFileNo\n";

// 2. Details for the new OP
$propId = '76901'; // The correct Prop ID for Plot C-97
$details = [
    'instrument_type' => 'Occupancy Permit (OP)',
    'op_type' => 'OP Resettlement',
    'op_serial_number' => '63',
    'registration_number' => '191/191/257',
    'volume_no' => '257',
    'page_no' => '191',
    'serial_no' => '191',
    'party_1_name' => 'KANO STATE GOVERNMENT',
    'party_2_name' => 'AISHA ADAMU',
    'plot_number' => 'C-97',
    'district' => 'FANISAU',
    'property_description' => 'Plot C-97, Fanisau, Ungogo, Kano',
    'property_location' => 'Plot C-97, Fanisau, Ungogo, Kano',
    'land_use' => 'COMMERCIAL',
    'land_use_id' => '2',
    'purpose' => 'COMMERCIAL',
    'tp_no' => 'TP/K/277',
    'temp_fileno' => $newTempFileNo,
    'prop_id' => $propId,
    'created_at' => now(),
    'updated_at' => now(),
    'created_by' => '101524', // Same as ToT creator
    'is_deed_registered' => 1
];

echo "Inserting OP record into instrument_capture...\n";
$icId = $db->table('instrument_capture')->insertGetId($details);

echo "Created OP with ID: $icId\n";

// 3. Link the ToT to this new OP (already linked to 76901, but let's double check)
$totId = 139667;
$db->table('pra')->where('id', $totId)->update([
    'parent_prop_id' => $propId,
    'updated_at' => now()
]);

echo "Linked ToT (ID: $totId) to OP (PropID: $propId).\n";
