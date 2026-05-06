<?php
/**
 * Import CofO Ready / CofO Already Collected data from CSV into duplicate_fileno table.
 *
 * Usage:  php import_cofo_data.php
 *
 * The CSV lives at docs/data/COFO_DATA/COFOREADY.csv and contains two sections:
 *   Section 1 (lines 2–1005): CofO Ready for Collection
 *   Section 2 (lines 1009–1025): CofO Already Collected
 *
 * Each row is stored with the appropriate [COFO_READY] or [COFO_COLLECTED] comment prefix.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(\Illuminate\Http\Request::capture());

use Illuminate\Support\Facades\DB;

$csvPath = __DIR__ . '/docs/data/COFO_DATA/COFOREADY.csv';

if (!file_exists($csvPath)) {
    echo "CSV file not found at: $csvPath\n";
    exit(1);
}

// Normalize CR-only line endings (old Mac format) to LF
$raw = file_get_contents($csvPath);
if ($raw === false) {
    echo "Failed to read CSV file.\n";
    exit(1);
}
$raw = str_replace(["\r\n", "\r"], "\n", $raw);
$lines = array_filter(explode("\n", $raw), fn($l) => trim($l) !== '');

$sections = [
    'cofo_ready'     => [],
    'cofo_collected' => [],
];

$currentSection = null;

foreach ($lines as $lineNum => $line) {
    $trimmed = trim($line);

    // Detect section headers
    if (stripos($trimmed, 'C OF O READY FOR COLLECTION') !== false) {
        $currentSection = 'cofo_ready';
        continue;
    }
    if (stripos($trimmed, 'C OF O ALREADY COLLECTED') !== false) {
        $currentSection = 'cofo_collected';
        continue;
    }

    // Skip column header rows
    if ($currentSection !== null && stripos($trimmed, 'S/N') === 0) {
        continue;
    }

    if ($currentSection === null) {
        continue;
    }

    // Parse CSV row
    $fields = str_getcsv($trimmed);
    if (count($fields) < 3) {
        continue;
    }

    $fileNumber = trim($fields[1] ?? '');
    $fileName   = trim($fields[2] ?? '');

    if ($fileNumber === '') {
        continue;
    }

    $sections[$currentSection][] = [
        'file_number' => strtoupper($fileNumber),
        'file_title'  => $fileName,
    ];
}

echo "Parsed CofO Ready: " . count($sections['cofo_ready']) . " rows\n";
echo "Parsed CofO Collected: " . count($sections['cofo_collected']) . " rows\n";

$now = now();
$userId = 1; // system/admin user

$insertCount = 0;
$skipCount   = 0;

foreach ($sections as $type => $rows) {
    $prefix = '[' . strtoupper($type) . ']';

    foreach (array_chunk($rows, 150) as $chunk) {
        foreach ($chunk as $row) {
            // Upsert: skip if file_number already exists for this type
            $exists = DB::connection('sqlsrv')
                ->table('duplicate_fileno')
                ->where('file_number', $row['file_number'])
                ->where('comment', 'like', $prefix . '%')
                ->exists();

            if ($exists) {
                $skipCount++;
                continue;
            }

            DB::connection('sqlsrv')->table('duplicate_fileno')->insert([
                'file_number' => $row['file_number'],
                'file_title'  => $row['file_title'],
                'registry'    => 'KANGIS Registry',
                'comment'     => $prefix,
                'category'    => $prefix,
                'source'      => 'csv_import',
                'created_by'  => $userId,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            $insertCount++;
        }
    }
}

echo "\nImport complete.\n";
echo "Inserted: $insertCount\n";
echo "Skipped (already exists): $skipCount\n";
