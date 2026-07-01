<?php
/**
 * Index COFO land files from duplicate_fileno into file_indexings.
 *
 * Source rows: duplicate_fileno where comment IN ('[COFO_READY]', '[COFO_COLLECTED]').
 * Idempotent — skips file numbers that are already indexed (UPPER/TRIM match).
 * created_at / updated_at are stamped to 5 days ago (per request).
 *
 * Carries over from duplicate_fileno: registry, location, plot_number, file_title.
 * Derives land_use_type from the file-number prefix and sets has_cofo = 1.
 *
 * Usage:
 *   php scratch/index_cofo_duplicate_files.php            # DRY RUN (no writes)
 *   php scratch/index_cofo_duplicate_files.php --commit   # perform the insert
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\FileIndexing;
use Illuminate\Support\Facades\DB;

$COMMENTS   = ['[COFO_READY]', '[COFO_COLLECTED]'];
$COMMIT     = in_array('--commit', $argv, true);
$CHUNK      = 200;
$CREATED_BY = 'COFO Duplicate Backfill';
$SOURCE     = 'cofo_duplicate_backfill';

// 5 days ago, per request.
$stamp = now()->subDays(5)->format('Y-m-d H:i:s');

$conn = DB::connection('sqlsrv');
$t0   = microtime(true);

echo ($COMMIT ? "*** COMMIT MODE — rows WILL be inserted ***" : "--- DRY RUN (no writes) — pass --commit to insert ---") . "\n";
echo "created_at / updated_at will be: {$stamp}\n\n";

/** Derive a land use type from the file-number prefix (mirrors IndexWrcDuplicateFiles). */
$deriveLandUse = function (string $fileNumber): ?string {
    $f = strtoupper(trim($fileNumber));
    $f = preg_replace('/^CON-/', '', $f);
    $map = [
        'RES'  => 'RESIDENTIAL',
        'COM'  => 'COMMERCIAL',
        'IND'  => 'INDUSTRIAL',
        'AG'   => 'AGRICULTURAL',
        'MISC' => 'MISCELLANEOUS',
    ];
    foreach ($map as $prefix => $use) {
        if (preg_match('/^' . $prefix . '[-\/]/', $f)) {
            return $use;
        }
    }
    return null;
};

// 1. Pull the COFO rows.
$rows = $conn->table('duplicate_fileno')
    ->whereIn('comment', $COMMENTS)
    ->whereNotNull('file_number')
    ->where('file_number', '<>', '')
    ->orderBy('id')
    ->get(['file_number', 'file_title', 'plot_number', 'location', 'registry', 'comment']);

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
    $candidates[$key] = [
        'file_number'      => $fileNo,
        'file_title'       => $row->file_title,
        'plot_number'      => $row->plot_number,
        'location'         => $row->location,
        'registry'         => $row->registry,
        'comment'          => $row->comment,
        'land_use_type'    => $deriveLandUse($fileNo),
        'general_registry' => FileIndexing::detectRegistryFromFileNumber($fileNo) ?? 'Lands Registry',
    ];
}
echo "Distinct file numbers: " . count($candidates) . "\n";

if (empty($candidates)) {
    echo "Nothing to do.\n";
    return;
}

// 3. Skip file numbers already present in file_indexings.
$existing = [];
foreach (array_chunk(array_keys($candidates), 500) as $keyChunk) {
    $conn->table('file_indexings')
        ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(file_number)))'), $keyChunk)
        ->pluck('file_number')
        ->each(function ($fn) use (&$existing) {
            $existing[strtoupper(trim((string) $fn))] = true;
        });
}

$toInsert = array_filter($candidates, fn ($key) => !isset($existing[$key]), ARRAY_FILTER_USE_KEY);
$skipped  = count($candidates) - count($toInsert);

echo "Already indexed (skipped): {$skipped}\n";
echo ($COMMIT ? 'To index' : 'Would index') . ': ' . count($toInsert) . "\n\n";

if (empty($toInsert)) {
    echo "All COFO files already indexed. Nothing to insert.\n";
    return;
}

// Preview a few.
echo "Sample of files to index:\n";
$preview = 0;
foreach ($toInsert as $row) {
    echo sprintf(
        "  %-30s | %-13s | %-16s | reg=%s\n",
        $row['file_number'], $row['land_use_type'] ?? '-', $row['comment'], $row['general_registry']
    );
    if (++$preview >= 15) { echo "  ...\n"; break; }
}
echo "\n";

if (!$COMMIT) {
    echo "Dry run complete. Re-run with --commit to insert " . count($toInsert) . " row(s).\n";
    return;
}

// 4. Insert.
$inserted = 0;
foreach (array_chunk($toInsert, $CHUNK) as $batch) {
    $conn->transaction(function () use ($batch, $stamp, $SOURCE, $CREATED_BY, &$inserted) {
        foreach ($batch as $row) {
            FileIndexing::forceCreate([
                'file_number'      => $row['file_number'],
                'file_title'       => $row['file_title'],
                'land_use_type'    => $row['land_use_type'],
                'plot_number'      => $row['plot_number'],
                'location'         => $row['location'],
                'registry'         => $row['registry'],
                'general_registry' => $row['general_registry'],
                'has_cofo'         => 1,
                'indexing_type'    => 'Regular',
                'source'           => $SOURCE,
                'created_by'       => $CREATED_BY,
                'created_at'       => $stamp,
                'updated_at'       => $stamp,
            ]);
            $inserted++;
        }
    });
    echo "  inserted {$inserted}/" . count($toInsert) . "\n";
}

$elapsed = round((microtime(true) - $t0) * 1000);
echo "\nDone. Indexed {$inserted} COFO file(s) in {$elapsed} ms.\n";
echo "Rollback if needed: DELETE FROM file_indexings WHERE source = '{$SOURCE}';\n";
