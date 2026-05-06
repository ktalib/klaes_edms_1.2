<?php
/**
 * Phase 8 smoke test — merger ToT path.
 *
 * Picks two OP rows that share a file number, runs the same lineage write
 * with source_op_id pointing to the anchor, asserts a SINGLE ToT row gets
 * a fresh prop_id and parent_prop_id linked back to the anchor.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\Pra\PraRecordService;

$db = DB::connection('sqlsrv');
$dbName = $db->select("SELECT DB_NAME() AS name")[0]->name ?? 'unknown';
echo "Connected to: {$dbName}\n";
if (stripos($dbName, 'test') === false) {
    fwrite(STDERR, "ABORT: must run on *_test database.\n");
    exit(2);
}

// Pick a file number with >=2 unmatched OPs
$row = $db->select("
    SELECT TOP 1 mlsFNo, COUNT(*) AS n
    FROM pra
    WHERE mlsFNo IS NOT NULL AND mlsFNo <> ''
      AND prop_id IS NOT NULL AND prop_id <> ''
      AND (instrument_type LIKE '%Occupancy Permit%' OR transaction_type LIKE '%Occupancy Permit%')
      AND (system_source IS NULL OR system_source <> 'OSSOPCHANGEOFNAME')
    GROUP BY mlsFNo
    HAVING COUNT(*) >= 2
    ORDER BY COUNT(*) DESC
");
if (!$row) {
    echo "No file with >=2 unmatched OPs found; merger smoke skipped.\n";
    exit(0);
}
$fileNo = $row[0]->mlsFNo;
echo "Merger candidate file: {$fileNo} ({$row[0]->n} OPs)\n";

$ops = $db->table('pra')->where('mlsFNo', $fileNo)
    ->where(function ($q) {
        $q->where('instrument_type', 'like', '%Occupancy Permit%')
          ->orWhere('transaction_type', 'like', '%Occupancy Permit%');
    })
    ->where(function ($q) {
        $q->whereNull('system_source')->orWhere('system_source', '!=', 'OSSOPCHANGEOFNAME');
    })
    ->orderBy('id')->limit(2)->get();

$anchor = $ops[0];
echo "Anchor OP: id={$anchor->id} prop_id={$anchor->prop_id}\n";
foreach ($ops as $op) {
    echo "  member id={$op->id} prop_id={$op->prop_id}\n";
}

$totSeqId = $db->table('temp_fileno_sequence')->insertGetId([
    'created_by' => 0, 'is_used' => 1, 'created_at' => now(), 'updated_at' => now(),
]);
$totTemp = 'TEMP-' . str_pad((string) $totSeqId, 5, '0', STR_PAD_LEFT);

$tot = app(PraRecordService::class)->createRecord([
    'mlsFNo' => $fileNo, 'fileno' => $fileNo, 'temp_fileno' => $totTemp,
    'force_fresh_prop_id' => true,
    'parent_prop_id' => $anchor->prop_id,
    'source_op_table' => 'pra', 'source_op_id' => (int) $anchor->id,
    'transaction_type' => 'Transfer of Title (OP)',
    'instrument_type' => 'Transfer of Title (OP)',
    'system_source' => 'OSSOPCHANGEOFNAME-SMOKE',
    'merger_group_id' => (string) \Illuminate\Support\Str::uuid(),
    'is_merger_op' => 1,
    'regNo' => '0/0/0', 'serialNo' => '0', 'pageNo' => '0', 'volumeNo' => '0',
    'Grantor' => 'Smoke Merger', 'Grantee' => null,
    'party_1' => 'Smoke Merger', 'party_2' => null,
], 0);

$totId = (int) data_get($tot, 'id');
$totRow = $db->table('pra')->where('id', $totId)->first();

$ok = [
    'fresh prop_id' => (string) $totRow->prop_id !== (string) $anchor->prop_id && !empty($totRow->prop_id),
    'parent_prop_id == anchor.prop_id' => (string) $totRow->parent_prop_id === (string) $anchor->prop_id,
    'source_op_id == anchor.id' => (int) $totRow->source_op_id === (int) $anchor->id,
    'merger flagged' => (int) ($totRow->is_merger_op ?? 0) === 1,
];
$failed = 0;
foreach ($ok as $k => $v) { echo '  [' . ($v ? 'PASS' : 'FAIL') . "] {$k}\n"; if (!$v) $failed++; }
echo "ToT id={$totId} prop_id={$totRow->prop_id} parent={$totRow->parent_prop_id} src={$totRow->source_op_table}#{$totRow->source_op_id}\n";

$db->table('pra')->where('id', $totId)->delete();
echo "Cleaned up.\n";
exit($failed > 0 ? 1 : 0);
