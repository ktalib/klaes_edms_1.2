<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$opType = $argv[1] ?? 'OP Resettlement';

echo "Checking instrument_capture duplicates for op_type = {$opType}\n\n";

$query = DB::connection('sqlsrv')->table('instrument_capture as ic')
    ->select(
        'ic.op_type',
        'ic.volume_no',
        'ic.page_no',
        'ic.serial_no',
        'ic.registration_number',
        DB::raw('COUNT(*) as occurrences')
    )
    ->where('ic.op_type', $opType)
    ->whereNotNull('ic.serial_no')
    ->groupBy('ic.op_type', 'ic.volume_no', 'ic.page_no', 'ic.serial_no', 'ic.registration_number')
    ->havingRaw('COUNT(*) > 1')
    ->orderBy('ic.volume_no')
    ->orderBy('ic.serial_no');

$groups = $query->get();

if ($groups->isEmpty()) {
    echo "No duplicate registration particulars found for this op_type.\n";
    exit(0);
}

foreach ($groups as $g) {
    echo "op_type: {$g->op_type} | Vol: {$g->volume_no} | Page: {$g->page_no} | Serial: {$g->serial_no} | Reg: {$g->registration_number} | Count: {$g->occurrences}\n";

    $rows = DB::connection('sqlsrv')->table('instrument_capture')
        ->where('op_type', $g->op_type)
        ->where('volume_no', $g->volume_no)
        ->where('page_no', $g->page_no)
        ->where('serial_no', $g->serial_no)
        ->where('registration_number', $g->registration_number)
        ->orderBy('id')
        ->get();

    foreach ($rows as $r) {
        echo "  - id={$r->id}, fileno=" . ($r->mlsFNo ?? $r->kangisFileNo ?? $r->NewKANGISFileno ?? $r->temp_fileno ?? 'N/A') . ", party_1={$r->party_1_name}, party_2={$r->party_2_name}, created_at={$r->created_at}\n";
    }

    echo "\n";
}
