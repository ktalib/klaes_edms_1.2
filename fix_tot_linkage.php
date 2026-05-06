<?php
/**
 * Fix Linkage and Party for COM-2025-756 / Plot C-97
 */
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');

// The ToT record
$totId = 139667;

// Correct info for Plot C-97
$correctParentPropId = '76901';
$correctParty1 = 'AISHA ADAMU';

echo "Updating ToT (ID: $totId)...\n";

$db->table('pra')->where('id', $totId)->update([
    'parent_prop_id' => $correctParentPropId,
    'party_1' => $correctParty1,
    'Grantor' => $correctParty1,
    'updated_at' => now()
]);

echo "Update complete. The ToT is now linked to Plot C-97 (PropID: $correctParentPropId).\n";
