<?php
/**
 * Test: Insert a DCIV record directly into file_indexings table
 * (simulates a file indexed under the DCIV registry)
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$conn = DB::connection('sqlsrv');

// ── Config ────────────────────────────────────────────────────────────────────
$fileNumber  = 'DCIV-2026-TEST-' . rand(100, 999);   // unique-ish test file no.
$trackingId  = 'TRK-DCIV-TEST-' . strtoupper(Str::random(6));
$fileTitle   = 'MUSA TESTING DCIV INDEXED FILE';
$plotNumber  = 'PLOT 12B';
$tpNo        = 'TP/DCIV/2026';
$location    = 'PLOT 12B, FAGGE, KANO MUNICIPAL, Kano';
$district    = 'FAGGE';
$lga         = 'KANO MUNICIPAL';
$registry    = 'DCIV';
$source      = 'DCIV File Indexing Test';
$createdBy   = 'Test Script';
$now         = now();
// ─────────────────────────────────────────────────────────────────────────────

echo "=== DCIV File Indexing Test Insert ===\n\n";

try {
    // 1. Insert into file_indexings
    $id = $conn->table('file_indexings')->insertGetId([
        'tracking_id'     => $trackingId,
        'file_number'     => $fileNumber,
        'file_title'      => $fileTitle,
        'plot_number'     => $plotNumber,
        'tp_no'           => $tpNo,
        'location'        => $location,
        'district'        => $district,
        'lga'             => $lga,
        'registry'        => $registry,
        'workflow_status' => 'indexed',
        'source'          => $source,
        'created_by'      => $createdBy,
        'is_deleted'      => 0,
        'created_at'      => $now,
        'updated_at'      => $now,
    ]);

    echo "✔  Inserted into file_indexings   id = {$id}\n";
    echo "   file_number  : {$fileNumber}\n";
    echo "   tracking_id  : {$trackingId}\n";
    echo "   registry     : {$registry}\n";
    echo "   location     : {$location}\n\n";

    // 2. Also insert a related-file entry into file_indexing_links so the
    //    "Related FileNo" column shows a link in the DCIV table.
    //    (table uses deleted_at for soft deletes; no is_deleted column)
    $relatedFileNumber = 'MLS/KAN/2020/TEST001';
    $conn->table('file_indexing_links')->insert([
        'file_indexing_id' => $id,
        'file_number'      => $relatedFileNumber,
        'file_title'       => 'RELATED TEST FILE TITLE',
        'plot_number'      => 'PLOT 5A',
        'tp_no'            => 'TP/TEST/2020',
        'location'         => 'PLOT 5A, NASARAWA, KANO MUNICIPAL, Kano',
        'lga'              => 'KANO MUNICIPAL',
        'district'         => 'NASARAWA',
        'created_at'       => $now,
        'updated_at'       => $now,
    ]);

    echo "✔  Inserted into file_indexing_links\n";
    echo "   file_indexing_id : {$id}\n";
    echo "   related_file_no  : {$relatedFileNumber}\n\n";

    // 3. Verify — re-read both rows
    $row = $conn->table('file_indexings')->where('id', $id)->first();
    echo "── file_indexings row ──────────────────────────────────────\n";
    foreach ((array)$row as $col => $val) {
        if (!is_null($val) && $val !== '' && $val !== 0) {
            echo "  {$col}: {$val}\n";
        }
    }

    $links = $conn->table('file_indexing_links')->where('file_indexing_id', $id)->get();
    echo "\n── file_indexing_links rows ({$links->count()}) ──────────────────\n";
    foreach ($links as $link) {
        echo "  id={$link->id}  file_number={$link->file_number}  file_title={$link->file_title}\n";
    }

    echo "\n✔  Done. Refresh the DCIV Generation page to see the new INDEXED FILE row.\n";
    echo "   File Number: {$fileNumber}\n";

} catch (\Exception $e) {
    echo "✘  Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
