<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Support\LegalSearchTimelineWeights;

$transactions = [
    [
        'id' => 1,
        'instrument_type' => 'Occupancy Permit (Op)',
        'source_table' => 'PRA',
        'transaction_date' => '2014-01-01',
        'reg_date' => '2014-01-02'
    ],
    [
        'id' => 2,
        'instrument_type' => 'Transfer Of Title (Op)',
        'source_table' => 'PRA',
        'transaction_date' => '2015-01-01',
        'reg_date' => '2015-01-02'
    ],
    [
        'id' => 3,
        'instrument_type' => 'File Commissioning',
        'source_table' => 'File Commissioning',
        'transaction_date' => '2010-01-01',
        'reg_date' => '2010-01-02'
    ]
];

foreach ($transactions as $t) {
    $raw = trim(strtolower($t['instrument_type']));
    $c = $raw;
    if (str_contains($raw, 'occupancy permit')) $c = 'occupancy permit';
    if (str_contains($raw, 'transfer of title')) $c = 'transfer of title';
    
    $w = LegalSearchTimelineWeights::weightFor($t, $c);
    echo "Instrument: {$t['instrument_type']}, Canonical: {$c}, Weight: {$w}\n";
}

$weighted = $transactions;
usort($weighted, function($a, $b) {
    $rawA = trim(strtolower($a['instrument_type']));
    $cA = $rawA;
    if (str_contains($rawA, 'occupancy permit')) $cA = 'occupancy permit';
    if (str_contains($rawA, 'transfer of title')) $cA = 'transfer of title';
    
    $rawB = trim(strtolower($b['instrument_type']));
    $cB = $rawB;
    if (str_contains($rawB, 'occupancy permit')) $cB = 'occupancy permit';
    if (str_contains($rawB, 'transfer of title')) $cB = 'transfer of title';
    
    $wa = LegalSearchTimelineWeights::weightFor($a, $cA);
    $wb = LegalSearchTimelineWeights::weightFor($b, $cB);
    
    if ($wa !== $wb) return $wb <=> $wa;
    return 0;
});

echo "\nSorted Order:\n";
foreach ($weighted as $i => $t) {
    echo ($i+1) . ". " . $t['instrument_type'] . "\n";
}
