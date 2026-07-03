<?php
/**
 * One-off lineage repair — 2026-07-03
 *
 * Chain: MLKN 2455 → CON-AG-2014-35 → (subdivision) → CON-AG-2026-108/109/110
 *        → (change of purpose) 108 → CON-COM-2026-430, 109 → CON-COM-2026-431
 *
 * The CoP commissioning (2026-07-02, via the Conversion + CoP-app flow) created the new
 * numbers but neither decommissioned the originals nor linked the new files back. The
 * engine's change_of_purpose_app_id branch is fixed going forward; this repairs the
 * existing chain:
 *   1. Sets related_fileno / parent_prop_id on CON-COM-2026-430/431 (new -> old link).
 *   2. Decommissions CON-AG-2026-108, CON-AG-2026-109 and MLKN 2455 via
 *      PlotWorkflowService (archives to decommissioned_files + deprecated_records,
 *      records successor_file_no, removes from active tables).
 *   3. Backfills successor_file_no on CON-AG-2014-35 (subdivided before the column existed).
 *
 * Run once:  php database_scripts/repair_lineage_2026_07_03.php
 * Idempotent: re-running finds nothing left to change (decommissionFiles reports
 * "not found in active records" for already-processed files).
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$c = DB::connection('sqlsrv');
$svc = app(App\Services\PlotWorkflowService::class);
$by = 'System (lineage repair 2026-07-03)';

echo "Connected to: " . $c->getDatabaseName() . " @ " . config('database.connections.sqlsrv.host') . "\n\n";

echo "== 1. Link new CoP files back to their originals ==\n";
$pairs = [
    'CON-COM-2026-430' => 'CON-AG-2026-108',
    'CON-COM-2026-431' => 'CON-AG-2026-109',
];
foreach ($pairs as $new => $old) {
    $oldIdx = $c->table('file_indexings')->where('file_number', $old)->first();
    $update = ['related_fileno' => json_encode([$old]), 'updated_at' => now()];
    $pp = $oldIdx->prop_id ?? ($oldIdx->parent_prop_id ?? null);
    if (!empty($pp)) {
        $update['parent_prop_id'] = (string) $pp;
    }
    $n1 = $c->table('file_indexings')->where('file_number', $new)->update($update);
    $n2 = $c->table('fileNumber')->where('mlsfNo', $new)->update($update);
    echo "  $new <- $old : file_indexings=$n1 fileNumber=$n2 parent_prop=" . var_export($pp, true) . "\n";
}

echo "\n== 2. Decommission superseded files (archive + successor pointer) ==\n";
$decoms = [
    ['CON-AG-2026-108', 'Change of Purpose to CON-COM-2026-430', 'CON-COM-2026-430'],
    ['CON-AG-2026-109', 'Change of Purpose to CON-COM-2026-431', 'CON-COM-2026-431'],
    ['MLKN 2455',       'KANGIS Recertification to CON-AG-2014-35', 'CON-AG-2014-35'],
];
foreach ($decoms as [$file, $reason, $succ]) {
    $res = $svc->decommissionFiles([$file], $reason, $by, $succ);
    echo "  $file -> $succ : archived=" . json_encode($res['archived']) . " errors=" . json_encode($res['errors']) . "\n";
}

echo "\n== 3. Backfill successor on CON-AG-2014-35 (subdivided before deploy) ==\n";
$n = $c->table('decommissioned_files')
    ->where('file_no', 'CON-AG-2014-35')
    ->whereNull('successor_file_no')
    ->update(['successor_file_no' => 'CON-AG-2026-108, CON-AG-2026-109, CON-AG-2026-110', 'updated_at' => now()]);
echo "  updated rows: $n\n";

echo "\nDone.\n";
