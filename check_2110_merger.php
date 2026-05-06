<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// The merger group has 2 PRA rows:
// - pra id:139732 = OP  (Muhammad Yusuf, Plot 1077B, is_merger_op=1)
// - pra id:139733 = TOT (Auwal Bala Usman and Muhammad Yusuf -> Umar Tijjani Babuga)
// 
// The OP is in the PRA table (not instrument_capture).
// But there's also a SECOND merged OP owner: "AUWAL BALA USMAN".
// The TOT party_1 is "AUWAL BALA USMAN and MUHAMMAD YUSUF" (two owners merged).
// 
// We need to check if there is a second OP in pra for Auwal Bala Usman in this merger group.
// 
// The merger_group_id is: 6b8db3f9-ccf0-4862-a166-1ecc22b69187
// Currently only 2 PRA rows are in the group. The missing OP for Auwal Bala Usman needs to be found.

$mergerGroupId = '6b8db3f9-ccf0-4862-a166-1ecc22b69187';

// Search for any Auwal Bala Usman OP record
echo "Searching for Auwal Bala Usman OP in pra...\n";
$rows = DB::connection('sqlsrv')->table('pra')
    ->where('party_2', 'LIKE', '%AUWAL BALA USMAN%')
    ->orWhere('Grantee', 'LIKE', '%AUWAL BALA USMAN%')
    ->get(['id','prop_id','mlsFNo','temp_fileno','instrument_type','transaction_type','Grantor','Grantee','party_1','party_2','plot_no','op_serial_number','op_type','merger_group_id','is_merger_op']);
echo json_encode($rows, JSON_PRETTY_PRINT) . "\n";

// Also check instrument_capture for Auwal
echo "\nSearching instrument_capture...\n";
$rows2 = DB::connection('sqlsrv')->table('instrument_capture')
    ->where('party_2_name', 'LIKE', '%AUWAL BALA USMAN%')
    ->get(['id','prop_id','temp_fileno','party_2_name','plot_number','op_serial_number','op_type','registration_number']);
echo json_encode($rows2, JSON_PRETTY_PRINT) . "\n";
