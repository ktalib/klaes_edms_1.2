<?php
/**
 * Verify if OP and Transfer of Title exist for:
 *   temp_fileno : TEMP-17789
 *   mlsFNo      : IND-RC-2026-111
 *   prop_id     : 87945
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// ── Parameters ──────────────────────────────────────────
$tempFileno = 'TEMP-17789';
$mlsFNo     = 'IND-RC-2026-111';
$propId     = 87945;

// ── Helper: fetch one pra row matching any of the three identifiers + instrument_type ──
function fetchPraRow(string $tempFileno, string $mlsFNo, int $propId, string $instrumentType): ?object
{
    return DB::connection('sqlsrv')
        ->table('pra')
        ->where('instrument_type', $instrumentType)
        ->where(function ($q) use ($tempFileno, $mlsFNo, $propId) {
            $q->where('temp_fileno', $tempFileno)
              ->orWhere('mlsFNo', $mlsFNo)
              ->orWhere('prop_id', $propId);
        })
        ->orderByDesc('id')
        ->first() ?: null;
}

// ── Lookup ───────────────────────────────────────────────
$opRow  = fetchPraRow($tempFileno, $mlsFNo, $propId, 'Occupancy Permit (OP)');
$totRow = fetchPraRow($tempFileno, $mlsFNo, $propId, 'Transfer of Title (OP)');

$found = ($opRow !== null) || ($totRow !== null);

$result = [
    'found'             => $found,
    'searched'          => [
        'temp_fileno' => $tempFileno,
        'mlsFNo'      => $mlsFNo,
        'prop_id'     => $propId,
    ],
    'op'                => $opRow,
    'transfer_of_title' => $totRow,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

// ── Write individual JSON files ──────────────────────────
$dir = __DIR__;

file_put_contents(
    $dir . '/pra_op_127224.json',
    json_encode($opRow, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

file_put_contents(
    $dir . '/pra_transfer_of_title_127225.json',
    json_encode($totRow, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

echo "Written: pra_op_127224.json\n";
echo "Written: pra_transfer_of_title_127225.json\n";
