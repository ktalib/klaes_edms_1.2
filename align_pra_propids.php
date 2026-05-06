<?php
/**
 * Align PRA records with PropID_Master assignments.
 */
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');

$mappings = [
    'COM-2025-757' => '80000785',
    'COM-2025-756' => '80000800',
];

foreach ($mappings as $fileNo => $correctPropId) {
    echo "Aligning $fileNo to PropID: $correctPropId\n";
    
    $updated = $db->table('pra')
        ->where('mlsFNo', $fileNo)
        ->orWhere('fileno', $fileNo)
        ->update([
            'prop_id' => $correctPropId,
            'updated_at' => now()
        ]);
    
    echo "Updated $updated rows in pra table.\n";
}

echo "Alignment complete.\n";
