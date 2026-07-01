<?php
/**
 * Check COFO duplicate_fileno rows against file_indexings.
 *
 * Pulls rows from `duplicate_fileno` whose comment is [COFO_READY] or
 * [COFO_COLLECTED] and reports which file numbers are already indexed in
 * `file_indexings` and which are not.
 *
 * Usage:
 *   php scratch/check_cofo_indexed.php            # summary + lists
 *   php scratch/check_cofo_indexed.php --csv      # also dump not-indexed CSV
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$COMMENTS = ['[COFO_READY]', '[COFO_COLLECTED]'];
$wantCsv  = in_array('--csv', $argv, true);

$conn = DB::connection('sqlsrv');
$t0   = microtime(true);

// 1. Pull the COFO rows.
$rows = $conn->table('duplicate_fileno')
    ->whereIn('comment', $COMMENTS)
    ->whereNotNull('file_number')
    ->where('file_number', '<>', '')
    ->orderBy('id')
    ->get(['id', 'registry', 'file_number', 'file_title', 'plot_number', 'location', 'comment']);

echo "duplicate_fileno rows matching " . implode(' / ', $COMMENTS) . ": {$rows->count()}\n";

// 2. Collapse to distinct file number (first row wins), keyed by UPPER(trim()).
$candidates = [];
foreach ($rows as $row) {
    $fileNo = trim((string) $row->file_number);
    if ($fileNo === '') {
        continue;
    }
    $key = strtoupper($fileNo);
    if (isset($candidates[$key])) {
        continue;
    }
    $candidates[$key] = $row;
}
echo "Distinct file numbers: " . count($candidates) . "\n";

if (empty($candidates)) {
    echo "Nothing to check.\n";
    return;
}

// 3. Build the set of file numbers that already exist in file_indexings.
//    Single set-based query per chunk, normalised with UPPER(LTRIM(RTRIM())).
$existing = [];
foreach (array_chunk(array_keys($candidates), 500) as $keyChunk) {
    $conn->table('file_indexings')
        ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(file_number)))'), $keyChunk)
        ->pluck('file_number')
        ->each(function ($fn) use (&$existing) {
            $existing[strtoupper(trim((string) $fn))] = true;
        });
}

// 4. Split into indexed / not-indexed.
$indexed    = [];
$notIndexed = [];
foreach ($candidates as $key => $row) {
    if (isset($existing[$key])) {
        $indexed[$key] = $row;
    } else {
        $notIndexed[$key] = $row;
    }
}

$elapsed = round((microtime(true) - $t0) * 1000);

echo "\n========================================\n";
echo "Already indexed     : " . count($indexed) . "\n";
echo "NOT yet indexed     : " . count($notIndexed) . "\n";
echo "Query time          : {$elapsed} ms\n";
echo "========================================\n";

// 5. List the not-indexed ones (the actionable set).
if (!empty($notIndexed)) {
    echo "\n--- NOT indexed (" . count($notIndexed) . ") ---\n";
    foreach ($notIndexed as $row) {
        echo sprintf(
            "  %-30s | %-16s | %s\n",
            $row->file_number,
            $row->comment,
            (string) $row->file_title
        );
    }
}

// 6. Optionally show the already-indexed ones too.
echo "\n--- Already indexed (" . count($indexed) . ") ---\n";
$shown = 0;
foreach ($indexed as $row) {
    echo sprintf("  %-30s | %s\n", $row->file_number, $row->comment);
    if (++$shown >= 50) {
        echo "  ... and " . (count($indexed) - 50) . " more\n";
        break;
    }
}

// 7. Optional CSV dump of not-indexed rows.
if ($wantCsv && !empty($notIndexed)) {
    $csvPath = __DIR__ . '/cofo_not_indexed.csv';
    $fh = fopen($csvPath, 'w');
    fputcsv($fh, ['id', 'registry', 'file_number', 'file_title', 'plot_number', 'location', 'comment']);
    foreach ($notIndexed as $row) {
        fputcsv($fh, [
            $row->id, $row->registry, $row->file_number,
            $row->file_title, $row->plot_number, $row->location, $row->comment,
        ]);
    }
    fclose($fh);
    echo "\nCSV written: {$csvPath}\n";
}
