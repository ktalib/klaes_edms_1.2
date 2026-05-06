<?php
/**
 * Fix Prop ID 83383: Link ToT to existing OP (80000010)
 */
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');

$totId = 139640;
$opPropId = '80000010';

echo "Linking ToT (ID: $totId) to OP (Prop ID: $opPropId)...\n";

$db->table('pra')->where('id', $totId)->update([
    'parent_prop_id' => $opPropId,
    'updated_at' => now()
]);

// Ensure OP is in PropID_Master
$exists = $db->table('PropID_Master')->where('prop_id', $opPropId)->exists();
if (!$exists) {
    echo "Registering $opPropId in PropID_Master...\n";
    $db->table('PropID_Master')->insert([
        'prop_id' => $opPropId,
        'temp_fileno' => 'TEMP-15856',
        'created_at' => now()
    ]);
}

echo "Fix complete.\n";
