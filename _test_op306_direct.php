<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

$serial = '306';

// Simulate the API logic directly
$normalize = function ($value) {
    return strtoupper(trim((string) ($value ?? '')));
};

// Get PRA records
$praRecords = DB::connection('sqlsrv')->table('pra')
    ->whereRaw('UPPER(LTRIM(RTRIM(CAST(op_serial_number AS NVARCHAR(100))))) = ?', [$serial])
    ->select('id','op_serial_number','instrument_type','transaction_type','fileno','prop_id','party_1_name','party_2_name','created_at')
    ->orderByDesc('id')
    ->get();

echo "PRA Records for Serial {$serial}: {$praRecords->count()}\n";
foreach($praRecords as $r) {
    echo "  ID={$r->id}, type={$r->instrument_type}, txn_type=" . ($r->transaction_type ?? 'null') . ", prop_id={$r->prop_id}, fileno=" . ($r->fileno ?? 'null') . "\n";
}

// Check instrument_capture
$icRecords = DB::connection('sqlsrv')->table('instrument_capture')
    ->whereRaw('UPPER(LTRIM(RTRIM(CAST(op_serial_number AS NVARCHAR(100))))) = ?', [$serial])
    ->select('id','op_serial_number','instrument_type','temp_fileno','prop_id')
    ->orderByDesc('id')
    ->get();
echo "instrument_capture Records: {$icRecords->count()}\n";

// Find transfers
$transferPropIds = [];
$transferFilenos = [];
foreach($praRecords as $r) {
    $type = $normalize($r->instrument_type ?? '');
    $txn = $normalize($r->transaction_type ?? '');
    if (str_contains($type, 'TRANSFER') || str_contains($txn, 'TRANSFER')) {
        $pid = $normalize($r->prop_id ?? '');
        $fno = $normalize($r->fileno ?? '');
        if ($pid !== '') $transferPropIds[$pid] = true;
        if ($fno !== '') $transferFilenos[$fno] = true;
        echo "  Transfer found: prop_id={$pid}, fileno={$fno}\n";
    }
}

// Check which OPs would be marked as used
foreach($praRecords as $r) {
    $type = $normalize($r->instrument_type ?? '');
    if (str_contains($type, 'TRANSFER')) {
        echo "  Record ID={$r->id}: TRANSFER (excluded from cards)\n";
        continue;
    }
    $pid = $normalize($r->prop_id ?? '');
    $fno = $normalize($r->fileno ?? '');
    $used = ($pid !== '' && isset($transferPropIds[$pid])) || ($fno !== '' && isset($transferFilenos[$fno]));
    echo "  Record ID={$r->id}: " . ($used ? 'ALREADY USED' : 'AVAILABLE') . " (prop_id={$pid}, fileno={$fno})\n";
}

echo "\nDone.\n";
