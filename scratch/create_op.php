<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\PropertyIdAllocationService;

$totId = 142100;
$tot = DB::connection('sqlsrv')->table('pra')->where('id', $totId)->first();

if (!$tot) {
    die("TOT record not found\n");
}

$allocationService = new PropertyIdAllocationService();

// Since there is a collision with TEMP-39516, we use a custom identifier for the OP's master row
$opPropId = $allocationService->allocateOrRetrievePropId(
    $tot->mlsFNo . '-OP',
    null,
    null,
    null,
    [
        'temp_fileno' => $tot->temp_fileno . '-OP',
        'skip_lookup' => true,
        'allow_temp_only' => true
    ]
);

echo "Allocated OP Prop ID: $opPropId\n";

$opSerial = 380; 

$opData = [
    'mlsFNo' => $tot->mlsFNo,
    'fileno' => $tot->mlsFNo,
    'temp_fileno' => $tot->temp_fileno,
    'transaction_type' => 'Occupancy Permit (OP)',
    'instrument_type' => 'Occupancy Permit (OP)',
    'Grantor' => 'KANO STATE GOVERNMENT',
    'party_1' => 'KANO STATE GOVERNMENT',
    'Grantee' => $tot->party_1, 
    'party_2' => $tot->party_1,
    'property_description' => $tot->property_description,
    'location' => $tot->location,
    'plot_no' => $tot->plot_no,
    'lgsaOrCity' => $tot->lgsaOrCity,
    'land_use' => $tot->land_use,
    'tp_no' => $tot->tp_no,
    'op_type' => $tot->op_type,
    'system_source' => 'OSSOPCHANGEOFNAME',
    'prop_id' => $opPropId,
    'created_at' => now(),
    'updated_at' => now(),
    'created_by' => 1, 
    'source' => 'FFR Existing Capture',
    'op_serial_number' => $opSerial,
    'is_deleted' => 0
];

$opId = DB::connection('sqlsrv')->table('pra')->insertGetId($opData);

echo "Created OP ID: $opId\n";

// Now update the TOT to link to this new OP
DB::connection('sqlsrv')->table('pra')->where('id', $totId)->update([
    'parent_prop_id' => $opPropId,
    'op_serial_number' => $opSerial
]);

echo "Linked TOT to OP\n";
