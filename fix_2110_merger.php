<?php
/**
 * Fix RES-2026-2110: Add the missing second merger OP (Auwal Bala Usman) to the merger group.
 * 
 * Current merger group: 6b8db3f9-ccf0-4862-a166-1ecc22b69187
 * - pra id:139732 = OP  (Muhammad Yusuf, Serial 3210, Plot 1077B) -- already in group
 * - pra id:139733 = TOT (party_1: Auwal Bala Usman + Muhammad Yusuf -> Umar Tijjani Babuga)
 * 
 * Missing: OP for Auwal Bala Usman (pra id:126099, prop_id:86681, Serial 3269)
 */
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');
$mergerGroupId = '6b8db3f9-ccf0-4862-a166-1ecc22b69187';
$missingOpPraId = 126099;

echo "Adding Auwal Bala Usman OP (pra id: $missingOpPraId) to merger group...\n";

$db->table('pra')->where('id', $missingOpPraId)->update([
    'merger_group_id' => $mergerGroupId,
    'is_merger_op'    => 1,
    'updated_at'      => now()
]);

// Verify the group now
$group = DB::connection('sqlsrv')->table('pra')
    ->where('merger_group_id', $mergerGroupId)
    ->get(['id','instrument_type','transaction_type','party_2','plot_no','op_serial_number','merger_group_id','is_merger_op']);

echo "Merger group now has " . count($group) . " rows:\n";
foreach ($group as $row) {
    echo "  id:{$row->id} type:{$row->instrument_type} party2:{$row->party_2} plot:{$row->plot_no} serial:{$row->op_serial_number}\n";
}

echo "Fix complete.\n";
