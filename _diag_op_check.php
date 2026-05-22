<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
$db = DB::connection('sqlsrv');

$propIds  = [69579486, 69574082];
$tempNos  = ['TEMP-50177', 'TEMP-50114'];
$mlsNos   = ['RES-2026-2220', 'RES-2026-2219'];

foreach ($propIds as $pid) {
    echo "\n=== ALL pra rows with TRY_CAST(prop_id)={$pid} ===\n";
    $rows = $db->table('pra')
        ->whereRaw("TRY_CAST([prop_id] AS bigint) = ?", [$pid])
        ->get(['id','prop_id','mlsFNo','fileno','temp_fileno','instrument_type','op_type','party_1','party_2','Grantor','Grantee','land_use','plot_no','system_source']);
    if ($rows->isEmpty()) {
        echo "  (no rows found)\n";
    }
    foreach ($rows as $r) {
        echo "  id={$r->id}  prop_id={$r->prop_id}  mlsFNo={$r->mlsFNo}  temp_fileno={$r->temp_fileno}\n";
        echo "    instrument_type={$r->instrument_type}  op_type={$r->op_type}\n";
        echo "    party_1={$r->party_1}  party_2={$r->party_2}  Grantee={$r->Grantee}\n";
        echo "    land_use={$r->land_use}  plot_no={$r->plot_no}\n\n";
    }
}

echo "\n=== pra rows for temp_filenos (any prop_id) ===\n";
foreach ($tempNos as $t) {
    $rows = $db->table('pra')
        ->where(function($q) use ($t) {
            $q->where('temp_fileno', $t)->orWhere('mlsFNo', $t)->orWhere('fileno', $t);
        })
        ->get(['id','prop_id','mlsFNo','fileno','temp_fileno','instrument_type','op_type','party_2','Grantee']);
    echo "\n  Searching for [{$t}] — " . $rows->count() . " row(s):\n";
    foreach ($rows as $r) {
        echo "    id={$r->id}  prop_id={$r->prop_id}  instrument_type={$r->instrument_type}  op_type={$r->op_type}\n";
        echo "    party_2={$r->party_2}  Grantee={$r->Grantee}\n";
    }
}

echo "\n=== instrument_capture rows for same prop_ids ===\n";
foreach ($propIds as $pid) {
    $rows = $db->table('instrument_capture')
        ->whereRaw("TRY_CAST([prop_id] AS bigint) = ?", [$pid])
        ->get(['id','prop_id','mlsFNo','temp_fileno','instrument_type','op_type','party_1_name','party_2_name','land_use','plot_number']);
    echo "\n  prop_id={$pid} — " . $rows->count() . " row(s):\n";
    foreach ($rows as $r) {
        echo "    id={$r->id}  prop_id={$r->prop_id}  mlsFNo={$r->mlsFNo}  temp_fileno={$r->temp_fileno}\n";
        echo "    instrument_type={$r->instrument_type}  op_type={$r->op_type}\n";
        echo "    party_1={$r->party_1_name}  party_2={$r->party_2_name}\n";
    }
}

echo "\n=== instrument_capture rows for temp_filenos ===\n";
foreach ($tempNos as $t) {
    $rows = $db->table('instrument_capture')
        ->where(function($q) use ($t) {
            $q->where('temp_fileno', $t)->orWhere('mlsFNo', $t);
        })
        ->get(['id','prop_id','mlsFNo','temp_fileno','instrument_type','op_type','party_1_name','party_2_name']);
    echo "\n  [{$t}] — " . $rows->count() . " row(s):\n";
    foreach ($rows as $r) {
        echo "    id={$r->id}  prop_id={$r->prop_id}  instrument_type={$r->instrument_type}\n";
        echo "    party_1={$r->party_1_name}  party_2={$r->party_2_name}\n";
    }
}
