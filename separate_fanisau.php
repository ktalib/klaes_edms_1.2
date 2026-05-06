<?php
/**
 * Separate Fanisau Plot C-97 from Nama Site Sharada conflict.
 */
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');

$fanisauOpId = 16287;
$fanisauTotId = 139667;
$newFanisauParentId = '80000890';

echo "Separating Fanisau Plot C-97...\n";

// 1. Update the OP
$db->table('instrument_capture')->where('id', $fanisauOpId)->update([
    'prop_id' => $newFanisauParentId,
    'updated_at' => now()
]);

// 2. Update the TOT
$db->table('pra')->where('id', $fanisauTotId)->update([
    'parent_prop_id' => $newFanisauParentId,
    'updated_at' => now()
]);

// 3. Register the new ID in PropID_Master if not already there
$exists = $db->table('PropID_Master')->where('prop_id', $newFanisauParentId)->exists();
if (!$exists) {
    $db->table('PropID_Master')->insert([
        'prop_id' => $newFanisauParentId,
        'temp_fileno' => 'TEMP-36619',
        'created_at' => now()
    ]);
}

echo "Separation complete. Fanisau Plot C-97 now has parent ID $newFanisauParentId.\n";
