<?php
/**
 * Separate Kudiddifawa Plot 4113 from Kumbotso conflict.
 */
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\PropertyIdAllocationService;

$db = DB::connection('sqlsrv');
$service = app()->make(PropertyIdAllocationService::class);

$opId = 123043;
$totId = 139640;

// Generate a fresh unique ID
$newParentId = $service->allocateOrRetrievePropId(null, 'TEMP-15856-NEW');

echo "Separating Kudiddifawa lineage to new ID: $newParentId...\n";

// 1. Update the OP
$db->table('pra')->where('id', $opId)->update([
    'prop_id' => $newParentId,
    'updated_at' => now()
]);

// 2. Update the TOT
$db->table('pra')->where('id', $totId)->update([
    'parent_prop_id' => $newParentId,
    'updated_at' => now()
]);

echo "Separation complete.\n";
