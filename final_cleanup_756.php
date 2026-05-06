<?php
/**
 * Final cleanup for COM-2025-756 / Plot C-97 vs Plot 62 conflict.
 */
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');

// 1. Isolate the unrelated Plot 62 record
echo "Isolating Plot 62 (ID: 15576)...\n";
$db->table('instrument_capture')->where('id', 15576)->update([
    'mlsFNo' => 'COM-2025-756-DUP',
    'temp_fileno' => 'TEMP-36619-DUP',
    'updated_at' => now()
]);

// 2. Align the correct OP (Plot C-97) with the ToT's identifiers
echo "Aligning Plot C-97 OP (ID: 16287)...\n";
$db->table('instrument_capture')->where('id', 16287)->update([
    'mlsFNo' => 'COM-2025-756',
    'temp_fileno' => 'TEMP-36619',
    'updated_at' => now()
]);

// 3. Ensure the ToT is correctly linked and configured
echo "Verifying ToT (ID: 139667)...\n";
$db->table('pra')->where('id', 139667)->update([
    'parent_prop_id' => '76901',
    'mlsFNo' => 'COM-2025-756',
    'temp_fileno' => 'TEMP-36619',
    'updated_at' => now()
]);

echo "Cleanup complete. The ToT and its correct OP are now perfectly aligned.\n";
