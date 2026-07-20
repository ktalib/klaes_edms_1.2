<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$service = app(\App\Services\LegalSearchService::class);

$fileNo = $argv[1] ?? 'CON-AG-2014-35';

echo "Testing buildPrintReport for: {$fileNo}\n";
echo str_repeat('=', 60) . "\n";

$report = $service->buildPrintReport(['file_number' => $fileNo]);

echo "Report status: " . ($report['status'] ?? 'n/a') . "\n";
var_dump(array_keys($report['payload'] ?? []));
echo "Payload success: " . (($report['payload']['success'] ?? 'n/a') ? 'true' : 'false') . "\n";

if (($report['payload']['success'] ?? true) === false) {
    echo "ERROR: " . ($report['payload']['message'] ?? 'Unknown error') . "\n";
    exit(1);
}

$rows = $report['payload']['data']['rows'] ?? ($report['payload']['rows'] ?? []);
echo "Total rows: " . count($rows) . "\n\n";

$currentGroup = null;
foreach ($rows as $i => $row) {
    $group = $row['lifecycle_file_no'] ?? $row['file_no'] ?? '?';
    if ($group !== $currentGroup) {
        $currentGroup = $group;
        echo "\n--- GROUP: {$group} ---\n";
    }
    printf(
        "%2d | %-25s | %-25s | %s\n",
        $i + 1,
        $row['file_no'] ?? '-',
        $row['source_table'] ?? '-',
        $row['instrument_type'] ?? '-'
    );
}
