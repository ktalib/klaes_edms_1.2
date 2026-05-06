<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$row = Illuminate\Support\Facades\DB::connection('sqlsrv')->table('fileNumber')
    ->join('mls_file_no', 'fileNumber.tracking_id', '=', 'mls_file_no.tracking_id')
    ->where('fileNumber.mlsFNo', 'RES-2026-2099')
    ->select('fileNumber.id', 'fileNumber.mlsFNo', 'mls_file_no.source_pra_id', 'mls_file_no.source_instrument_capture_id')
    ->first();

echo "fileNumber row:\n" . json_encode($row, JSON_PRETTY_PRINT) . "\n";
