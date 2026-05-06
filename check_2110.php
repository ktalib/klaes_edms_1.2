<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Get the TOT for RES-2026-2110
$tot = DB::connection('sqlsrv')->table('pra')
    ->where('mlsFNo', 'RES-2026-2110')
    ->orWhere('fileno', 'RES-2026-2110')
    ->first();

echo "TOT:\n" . json_encode($tot, JSON_PRETTY_PRINT) . "\n";

// Check merger group if it has one
if ($tot && $tot->merger_group_id) {
    echo "\nMerger group: {$tot->merger_group_id}\n";
    $mergerRows = DB::connection('sqlsrv')->table('pra')
        ->where('merger_group_id', $tot->merger_group_id)
        ->get();
    echo "All PRA rows in merger group:\n" . json_encode($mergerRows, JSON_PRETTY_PRINT) . "\n";
}

// Also look up OPs with serial 3210 and surrounding to find the other merger OP
echo "\nLooking for related OPs in instrument_capture...\n";
$relatedOps = DB::connection('sqlsrv')->table('instrument_capture')
    ->where('prop_id', $tot->prop_id ?? '80000907')
    ->get(['id','prop_id','temp_fileno','party_2_name','plot_number','op_serial_number','op_type','merger_group_id','is_merger_op','registration_number']);
echo json_encode($relatedOps, JSON_PRETTY_PRINT) . "\n";

// Check what prop_id 80000907 has in instrument_capture
echo "\nAll instrument_capture for prop_id 80000907:\n";
$ic = DB::connection('sqlsrv')->table('instrument_capture')
    ->where('prop_id', '80000907')
    ->get(['id','prop_id','temp_fileno','party_2_name','plot_number','op_serial_number','op_type','merger_group_id','is_merger_op']);
echo json_encode($ic, JSON_PRETTY_PRINT) . "\n";
