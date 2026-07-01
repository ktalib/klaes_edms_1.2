<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$newFileNo = 'CON-COM-2023-197';

echo "=== PRA rows for {$newFileNo} ===\n";
$pra = DB::connection('sqlsrv')->table('pra')
    ->where('mlsFNo', $newFileNo)
    ->get(['id','prop_id','parent_prop_id','mlsFNo','title_type','transaction_type','Grantor','Grantee','party_1','party_2','comments','temp_fileno','created_at']);
echo json_encode($pra, JSON_PRETTY_PRINT) . "\n";

echo "\n=== manual_file_linkages for {$newFileNo} ===\n";
$link = DB::connection('sqlsrv')->table('manual_file_linkages')
    ->where('new_file_number', $newFileNo)
    ->get();
echo json_encode($link, JSON_PRETTY_PRINT) . "\n";

// Get the old/source file numbers from linkage
foreach ($link as $l) {
    $olds = json_decode($l->old_file_numbers, true) ?: [];
    echo "\n=== Source file_indexings for: " . implode(', ', $olds) . " ===\n";
    $idx = DB::connection('sqlsrv')->table('file_indexings')
        ->whereIn('file_number', $olds)
        ->get(['id','file_number','file_title','original_holder','current_holder','prop_id']);
    echo json_encode($idx, JSON_PRETTY_PRINT) . "\n";

    echo "\n=== Source PRA party_2 (current owners) for those files ===\n";
    $srcPra = DB::connection('sqlsrv')->table('pra')
        ->whereIn('mlsFNo', $olds)
        ->get(['id','mlsFNo','transaction_type','party_1','party_2','Grantor','Grantee','created_at']);
    echo json_encode($srcPra, JSON_PRETTY_PRINT) . "\n";
}

echo "\n=== Destination file_indexings for {$newFileNo} ===\n";
$dest = DB::connection('sqlsrv')->table('file_indexings')
    ->where('file_number', $newFileNo)
    ->get(['id','file_number','file_title','original_holder','current_holder','prop_id']);
echo json_encode($dest, JSON_PRETTY_PRINT) . "\n";
