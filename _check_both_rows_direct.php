<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Fetch both rows directly by ID regardless of instrument_type
$row224 = DB::connection('sqlsrv')->table('pra')->where('id', 127224)->first();
$row225 = DB::connection('sqlsrv')->table('pra')->where('id', 127225)->first();

echo "=== Row 127224 ===" . PHP_EOL;
echo json_encode([
    'id'               => $row224->id ?? null,
    'instrument_type'  => $row224->instrument_type ?? null,
    'transaction_type' => $row224->transaction_type ?? null,
    'is_deleted'       => $row224->is_deleted ?? null,
    'deleted_at'       => $row224->deleted_at ?? null,
    'system_source'    => $row224->system_source ?? null,
    'updated_at'       => $row224->updated_at ?? null,
], JSON_PRETTY_PRINT) . PHP_EOL;

echo PHP_EOL . "=== Row 127225 ===" . PHP_EOL;
echo json_encode([
    'id'               => $row225->id ?? null,
    'instrument_type'  => $row225->instrument_type ?? null,
    'transaction_type' => $row225->transaction_type ?? null,
    'is_deleted'       => $row225->is_deleted ?? null,
    'deleted_at'       => $row225->deleted_at ?? null,
    'system_source'    => $row225->system_source ?? null,
    'updated_at'       => $row225->updated_at ?? null,
], JSON_PRETTY_PRINT) . PHP_EOL;

// Also show ALL pra rows for this prop_id regardless of type
echo PHP_EOL . "=== All pra rows for prop_id 87945 ===" . PHP_EOL;
$all = DB::connection('sqlsrv')->table('pra')
    ->where('prop_id', 87945)
    ->select(['id', 'instrument_type', 'transaction_type', 'system_source', 'is_deleted', 'deleted_at', 'updated_at'])
    ->orderBy('id')
    ->get();
echo json_encode($all, JSON_PRETTY_PRINT) . PHP_EOL;
