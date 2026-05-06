<?php
/**
 * Phase 8 smoke test for OP -> ToT prevention plan.
 *
 * Runs against the configured sqlsrv connection (klas_test).
 *
 * What it does:
 *   1. Picks an unmatched OP row from `pra` with system_source != OSSOPCHANGEOFNAME
 *      and a real mlsFNo / fileno, snapshots its prop_id + temp_fileno.
 *   2. Calls PraRecordService::createRecord() with force_fresh_prop_id=true,
 *      parent_prop_id, source_op_table='pra', source_op_id.
 *   3. Asserts: ToT prop_id != OP prop_id, ToT.parent_prop_id == OP.prop_id,
 *      ToT.source_op_id == OP.id, ToT.temp_fileno != OP.temp_fileno.
 *   4. Cleans up the synthetic ToT row at the end.
 *
 * Usage:  php _smoke_test_op_tot_lineage.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\Pra\PraRecordService;

$db = DB::connection('sqlsrv');

$dbName = $db->select("SELECT DB_NAME() AS name")[0]->name ?? 'unknown';
echo "Connected to database: {$dbName}\n";
if (stripos($dbName, 'test') === false) {
    fwrite(STDERR, "ABORT: this script must run against a *_test database.\n");
    exit(2);
}

// 1) pick an unmatched OP candidate
$op = $db->table('pra')
    ->whereNotNull('prop_id')
    ->where('prop_id', '!=', '')
    ->whereNotNull('mlsFNo')
    ->where(function ($q) {
        $q->where('instrument_type', 'like', '%Occupancy Permit%')
          ->orWhere('transaction_type', 'like', '%Occupancy Permit%');
    })
    ->where(function ($q) {
        $q->whereNull('system_source')->orWhere('system_source', '!=', 'OSSOPCHANGEOFNAME');
    })
    ->orderByDesc('id')
    ->first();

if (!$op) {
    fwrite(STDERR, "No unmatched OP candidate found in pra.\n");
    exit(3);
}

echo "Candidate OP: id={$op->id} prop_id={$op->prop_id} mlsFNo={$op->mlsFNo} temp_fileno=" . ($op->temp_fileno ?: '(null)') . "\n";

// 2) allocate a separate temp_fileno for the synthetic ToT
$totSeqId = $db->table('temp_fileno_sequence')->insertGetId([
    'created_by' => 0,
    'is_used'    => 1,
    'created_at' => now(),
    'updated_at' => now(),
]);
$totTempFileno = 'TEMP-' . str_pad((string) $totSeqId, 5, '0', STR_PAD_LEFT);
echo "Allocated ToT temp_fileno: {$totTempFileno}\n";

$praService = app(PraRecordService::class);
$totRow = $praService->createRecord([
    'mlsFNo'              => $op->mlsFNo,
    'fileno'              => $op->mlsFNo,
    'temp_fileno'         => $totTempFileno,
    'force_fresh_prop_id' => true,
    'parent_prop_id'      => $op->prop_id,
    'source_op_table'     => 'pra',
    'source_op_id'        => (int) $op->id,
    'transaction_type'    => 'Transfer of Title (OP)',
    'instrument_type'     => 'Transfer of Title (OP)',
    'regNo'               => '0/0/0',
    'serialNo'            => '0',
    'pageNo'              => '0',
    'volumeNo'            => '0',
    'system_source'       => 'OSSOPCHANGEOFNAME-SMOKE',
    'source'              => 'SMOKE TEST',
    'Grantor'             => 'Smoke Allottee',
    'Grantee'             => 'Smoke New Holder',
    'party_1'             => 'Smoke Allottee',
    'party_2'             => 'Smoke New Holder',
], 0);

$totId = (int) data_get($totRow, 'id', 0);
if (!$totId) {
    fwrite(STDERR, "createRecord did not return a ToT id\n");
    exit(4);
}
echo "Synthetic ToT id={$totId}\n";

$tot = $db->table('pra')->where('id', $totId)->first();

$assertions = [
    'ToT.prop_id is not empty' => !empty($tot->prop_id),
    'ToT.prop_id != OP.prop_id' => (string) $tot->prop_id !== (string) $op->prop_id,
    'ToT.parent_prop_id == OP.prop_id' => (string) $tot->parent_prop_id === (string) $op->prop_id,
    'ToT.source_op_table == pra' => $tot->source_op_table === 'pra',
    'ToT.source_op_id == OP.id'  => (int) $tot->source_op_id === (int) $op->id,
    'ToT.temp_fileno != OP.temp_fileno' => (string) $tot->temp_fileno !== (string) ($op->temp_fileno ?? ''),
    'ToT.temp_fileno == allocated' => (string) $tot->temp_fileno === $totTempFileno,
];

$failed = 0;
foreach ($assertions as $label => $ok) {
    $mark = $ok ? 'PASS' : 'FAIL';
    if (!$ok) { $failed++; }
    echo "  [{$mark}] {$label}\n";
}

echo "OP  : id={$op->id}   prop_id={$op->prop_id}   temp=" . ($op->temp_fileno ?: '(null)') . "\n";
echo "ToT : id={$tot->id}  prop_id={$tot->prop_id}  temp={$tot->temp_fileno}  parent={$tot->parent_prop_id}  src={$tot->source_op_table}#{$tot->source_op_id}\n";

// cleanup
$db->table('pra')->where('id', $totId)->delete();
echo "Cleaned up synthetic ToT id={$totId}\n";

if ($failed > 0) {
    fwrite(STDERR, "{$failed} assertion(s) failed\n");
    exit(1);
}
echo "All assertions passed.\n";
exit(0);
