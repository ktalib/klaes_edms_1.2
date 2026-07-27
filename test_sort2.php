<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Support\LegalSearchTimelineWeights;

$canonicalTransactionType = function (?string $type): string {
    $raw = trim(mb_strtolower($type ?? ''));
    if ($raw === '' || $raw === '-') return '';
    if (str_contains($raw, 'right of occupancy') || str_contains($raw, 'right of occupanc') || preg_match('/^r\s*of\s*o$/', $raw)) return 'right of occupancy';
    if (str_contains($raw, 'certificate of occupancy') || str_contains($raw, 'cert of occupancy') || preg_match('/^c\s*of\s*o$/', $raw)) return 'certificate of occupancy';
    if (str_contains($raw, 'occupancy permit') || preg_match('/^o\s*p$/', $raw)) return 'occupancy permit';
    if (str_contains($raw, 'transfer of title')) return 'transfer of title';
    return $raw;
};

$weightOf = function (array $row) use ($canonicalTransactionType): int {
    $txType = $canonicalTransactionType($row['transaction_type'] ?? ($row['instrument_type'] ?? ''));
    $w = \App\Support\LegalSearchTimelineWeights::weightFor($row, $txType);
    return $w ?? 0;
};

$rows = [
    ['id' => 1, 'source_table' => 'PRA', 'instrument_type' => 'Occupancy Permit (Op)', 'transaction_type' => 'Occupancy Permit (Op)'],
    ['id' => 2, 'source_table' => 'File Commissioning', 'instrument_type' => 'File Commissioning', 'transaction_type' => 'File Commissioning'],
    ['id' => 3, 'source_table' => 'PRA', 'instrument_type' => 'Transfer of Title (OP)', 'transaction_type' => 'Transfer of Title (OP) - OP Change of Name'],
];

usort($rows, function (array $a, array $b) use ($weightOf): int {
    $wa = $weightOf($a);
    $wb = $weightOf($b);
    if ($wa !== $wb) {
        return $wb <=> $wa;
    }
    return 0;
});

foreach ($rows as $row) {
    echo $row['instrument_type'] . " | " . $row['transaction_type'] . " | Weight: " . $weightOf($row) . "\n";
}
