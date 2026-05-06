<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// 1) Fetch source PRA 77687 (original OP Resettlement: KSG → UMAR SHEHU)
$pra = DB::connection('sqlsrv')->table('pra')->where('id', 77687)->first();
if (!$pra) { die("PRA 77687 not found!\n"); }

// 2) Fetch mls_file_no 12094 (ALI ADAMU)
$mls = DB::connection('sqlsrv')->table('mls_file_no')->where('id', 12094)->first();
if (!$mls) { die("mls_file_no 12094 not found!\n"); }

echo "=== Source PRA 77687 ===\n";
echo "  party_1={$pra->party_1} | party_2={$pra->party_2}\n";
echo "  prop_id={$pra->prop_id} | temp={$pra->temp_fileno} | plot={$pra->plot_no} | tp={$pra->tp_no}\n";
echo "  op_type={$pra->op_type} | instrument={$pra->instrument_type}\n";

echo "\n=== MLS 12094 ===\n";
echo "  file_name={$mls->file_name} | source={$mls->source} | sub_source={$mls->sub_source}\n";
echo "  full_file_number={$mls->full_file_number}\n";

// 3) Create new PRA record: Transfer of Title (UMAR SHEHU → ALI ADAMU)
$newPra = [
    'mlsFNo'               => $mls->full_file_number,       // RES-2026-1669
    'transaction_type'     => 'Transfer of Title (OP) - ' . $mls->sub_source, // Transfer of Title (OP) - OP Change of Name
    'transaction_date'     => now()->toDateTimeString(),
    'instrument_type'      => 'Transfer of Title (OP)',
    'Grantor'              => $pra->party_2,                  // UMAR SHEHU (previous grantee becomes grantor)
    'Grantee'              => $mls->file_name,                // ALI ADAMU
    'party_1'              => $pra->party_2,                  // UMAR SHEHU
    'party_2'              => $mls->file_name,                // ALI ADAMU
    'property_description' => $pra->property_description,
    'location'             => $pra->location,
    'plot_no'              => $pra->plot_no,
    'lgsaOrCity'           => $pra->lgsaOrCity,
    'tp_no'                => $pra->tp_no,
    'land_use'             => $pra->land_use,
    'prop_id'              => $pra->prop_id,                  // 71682
    'temp_fileno'          => $pra->temp_fileno,              // TEMP-11670
    'fileno'               => $pra->temp_fileno,              // TEMP-11670
    'op_type'              => $mls->sub_source,               // OP Change of Name
    'source'               => 'Transfer of Title (OP)',
    'source_pra_id'        => 77687,
    'created_at'           => now()->toDateTimeString(),
    'updated_at'           => now()->toDateTimeString(),
    'created_by'           => $pra->created_by,
    'updated_by'           => $pra->created_by,
    'is_deleted'           => 0,
];

echo "\n=== New PRA record to insert ===\n";
foreach ($newPra as $k => $v) {
    echo "  {$k}: {$v}\n";
}

echo "\nInserting...\n";
$newId = DB::connection('sqlsrv')->table('pra')->insertGetId($newPra);
echo "SUCCESS! New PRA id = {$newId}\n";

// 4) Verify
echo "\n=== Verification: PRA chain for prop_id={$pra->prop_id} ===\n";
$chain = DB::connection('sqlsrv')->table('pra')
    ->where('prop_id', $pra->prop_id)
    ->orderBy('id')
    ->get();
foreach ($chain as $r) {
    echo "  id={$r->id} | p1={$r->party_1} | p2={$r->party_2}"
        . " | instrument=" . ($r->instrument_type ?? 'NULL')
        . " | op_type=" . ($r->op_type ?? 'NULL')
        . " | source_pra_id=" . ($r->source_pra_id ?? 'NULL')
        . " | mlsFNo=" . ($r->mlsFNo ?? 'NULL')
        . "\n";
}
