<?php
/*
 * Repair party_1 / Grantor on a manual-linkage MERGER PRA row.
 *
 * Sets party_1 (grantor) = the combined current owners (party_2) of the merged
 * source files. Run a DRY RUN first, then re-run with --apply to write.
 *
 *   php scratch/fix_merger_party1.php CON-COM-2023-197
 *   php scratch/fix_merger_party1.php CON-COM-2023-197 --apply
 */
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$fileNo = $argv[1] ?? null;
$apply  = in_array('--apply', $argv, true);

if (!$fileNo) {
    fwrite(STDERR, "Usage: php scratch/fix_merger_party1.php <NEW_FILE_NO> [--apply]\n");
    exit(1);
}
$fileNo = strtoupper(trim($fileNo));
$conn   = DB::connection('sqlsrv');

// 1) The merger PRA row(s) for this destination file.
$mergerRows = $conn->table('pra')
    ->where('mlsFNo', $fileNo)
    ->whereIn('transaction_type', ['Merger', 'Plot Extension', 'Change of Purpose'])
    ->get(['id', 'mlsFNo', 'transaction_type', 'party_1', 'party_2', 'Grantor', 'Grantee']);

if ($mergerRows->isEmpty()) {
    fwrite(STDERR, "No Merger/Extension/CoP PRA row found for {$fileNo}.\n");
    exit(1);
}

// 2) Source files from the linkage audit row.
$linkages = $conn->table('manual_file_linkages')->where('new_file_number', $fileNo)->get();
$oldFiles = [];
foreach ($linkages as $l) {
    foreach (json_decode($l->old_file_numbers, true) ?: [] as $f) {
        $f = strtoupper(trim((string) $f));
        if ($f !== '') { $oldFiles[] = $f; }
    }
}
$oldFiles = array_values(array_unique($oldFiles));

if (empty($oldFiles)) {
    fwrite(STDERR, "No source (old) file numbers recorded in manual_file_linkages for {$fileNo}.\n");
    exit(1);
}
echo "Source files: " . implode(', ', $oldFiles) . "\n";

// 3) Each source file's current owner = its latest PRA party_2/Grantee.
//    Fall back to file_indexings.current_holder (if still present) or decommissioned_files.
$holders = [];
foreach ($oldFiles as $of) {
    $src = $conn->table('pra')
        ->where('mlsFNo', $of)
        ->orderByDesc('id')
        ->first(['party_2', 'Grantee']);
    $name = trim((string) ($src->party_2 ?? $src->Grantee ?? ''));

    if ($name === '') {
        $idx = $conn->table('file_indexings')->where('file_number', $of)->first(['current_holder', 'file_title']);
        $name = trim((string) ($idx->current_holder ?? $idx->file_title ?? ''));
    }
    if ($name !== '') {
        $holders[] = $name;
        echo "  {$of} -> {$name}\n";
    } else {
        echo "  {$of} -> (no owner found)\n";
    }
}

$holders = array_values(array_unique(array_filter($holders)));
if (empty($holders)) {
    fwrite(STDERR, "Could not resolve any source owners; aborting.\n");
    exit(1);
}
$party1 = implode(', ', $holders);

echo "\nProposed party_1 / Grantor: {$party1}\n\n";

foreach ($mergerRows as $row) {
    echo "PRA #{$row->id} ({$row->transaction_type}): "
        . "party_1 [{$row->party_1}] -> [{$party1}]\n";
    if ($apply) {
        $conn->table('pra')->where('id', $row->id)->update([
            'party_1'    => $party1,
            'Grantor'    => $party1,
            'updated_at' => now(),
        ]);
        echo "  ...updated.\n";
    }
}

echo $apply ? "\nDone (applied).\n" : "\nDRY RUN — re-run with --apply to write changes.\n";
