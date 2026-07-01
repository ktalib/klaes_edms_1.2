<?php
/**
 * _fix_62880_baballiya.php
 *
 * prop_id 62880 wrongly groups Salma Abba Hassan's OP (instrument_capture id=5199)
 * with the Baballiya H/Q -> Lawan Shafiu Muhammad TOT (pra id=152033).
 *
 *  1. DETACH Salma's OP -> give instrument_capture id=5199 a fresh prop_id.
 *  2. CREATE a correct OP for BABALLIYA H/Q under prop_id 62880, from the TOT's details.
 *  3. Repoint the TOT's dead lineage (source_op_id 123584 / parent_prop_id 74205)
 *     to the new OP.
 *
 * Usage:  php _fix_62880_baballiya.php            (dry-run preview)
 *         php _fix_62880_baballiya.php --execute   (commit)
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$dryRun = !in_array('--execute', $argv ?? []);
$db = DB::connection('sqlsrv');

echo "==== Fix prop_id 62880 (Salma OP vs Baballiya TOT) " . ($dryRun ? "[DRY-RUN]" : "[EXECUTE]") . " ====\n\n";

$salma = $db->table('instrument_capture')->where('id', 5199)->first();
$tot   = $db->table('pra')->where('id', 152033)->first();

if (!$salma || (int)$salma->prop_id !== 62880) { exit("ABORT: Salma OP ic 5199 not on prop_id 62880 (got " . ($salma->prop_id ?? 'missing') . ")\n"); }
if (!$tot   || (int)$tot->prop_id   !== 62880) { exit("ABORT: TOT pra 152033 not on prop_id 62880\n"); }
if (strcasecmp(trim($salma->party_2_name), 'SALMA ABBA HASSAN') !== 0) { exit("ABORT: ic 5199 party_2 is not Salma\n"); }
if (strcasecmp(trim($tot->party_1), 'BABALLIYA H/Q') !== 0)           { exit("ABORT: TOT party_1 is not Baballiya H/Q\n"); }

$newSalmaProp = ((int) $db->table('PropID_Master')->where('prop_id','<',2147483647)->max('prop_id')) + 1;

$newOp = [
    'instrument_type'      => 'Occupancy Permit (OP)',
    'prop_id'              => 62880,
    'mlsFNo'               => $tot->mlsFNo,            // RES-2024-531
    'temp_fileno'          => $tot->temp_fileno,       // TEMP-16158
    'op_type'              => $tot->op_type,           // Direct Allocation
    'op_serial_number'     => $tot->op_serial_number,  // 1248
    'party_1_name'         => 'Kano State Government',
    'party_2_name'         => $tot->party_1,           // BABALLIYA H/Q
    'land_use'             => $tot->land_use,           // RESIDENTIAL
    'plot_number'          => $tot->plot_no,            // 992
    'property_description'  => $tot->property_description, // KUDIDDIFAWA EXT, Kano
    'property_location'    => $tot->location,           // KUDIDDIFAWA EXT, Kano
    'tp_no'                => $tot->tp_no,              // TP/KN/215M
    'is_deleted'           => 0,
    'created_by'           => $tot->created_by,
    'created_at'           => now(),
    'updated_at'           => now(),
];

echo "Plan:\n";
echo "  1) UPDATE instrument_capture SET prop_id={$newSalmaProp} WHERE id=5199   (detach Salma)\n";
echo "  2) INSERT new Baballiya H/Q OP under prop_id 62880:\n";
foreach ($newOp as $k=>$v) echo "        {$k} = " . ($v ?? 'NULL') . "\n";
echo "  3) UPDATE pra id=152033 SET source_op_table='instrument_capture', source_op_id=<newOpId>, instrument_capture_id=<newOpId>, parent_prop_id=62880\n\n";

if ($dryRun) { echo "[DRY-RUN] nothing written. Re-run with --execute.\n"; exit; }

$db->beginTransaction();
try {
    $u1 = $db->table('instrument_capture')->where('id',5199)->where('prop_id',62880)
             ->update(['prop_id'=>$newSalmaProp, 'updated_at'=>now()]);
    if ($u1 !== 1) throw new Exception("detach updated {$u1} rows (expected 1)");

    $newOpId = $db->table('instrument_capture')->insertGetId($newOp);

    $u3 = $db->table('pra')->where('id',152033)->update([
        'source_op_table'       => 'instrument_capture',
        'source_op_id'          => $newOpId,
        'instrument_capture_id' => $newOpId,
        'parent_prop_id'        => 62880,
        'updated_at'            => now(),
    ]);

    echo "  detached Salma ic 5199 -> prop_id {$newSalmaProp}\n";
    echo "  new Baballiya OP inserted: instrument_capture id={$newOpId} (prop_id 62880)\n";
    echo "  TOT 152033 lineage repointed to new OP ({$u3} row)\n";

    $db->commit();
    echo "\n[COMMITTED]\n\n";

    echo "Verify - rows now under prop_id 62880:\n";
    foreach ($db->table('instrument_capture')->where('prop_id',62880)->get(['id','instrument_type','party_2_name','plot_number','land_use']) as $r)
        echo "  ic  {$r->id} | {$r->instrument_type} | {$r->party_2_name} | plot {$r->plot_number} | {$r->land_use}\n";
    foreach ($db->table('pra')->whereRaw('TRY_CAST(prop_id AS bigint)=?',[62880])->get(['id','instrument_type','party_1','party_2','plot_no']) as $r)
        echo "  pra {$r->id} | {$r->instrument_type} | {$r->party_1} -> {$r->party_2} | plot {$r->plot_no}\n";
    echo "\nSalma OP now under prop_id {$newSalmaProp}:\n";
    foreach ($db->table('instrument_capture')->where('id',5199)->get(['id','prop_id','party_2_name','plot_number']) as $r)
        echo "  ic {$r->id} | prop {$r->prop_id} | {$r->party_2_name} | plot {$r->plot_number}\n";
} catch (\Throwable $e) {
    $db->rollBack();
    echo "\n[ERROR] " . $e->getMessage() . "\n[ROLLED BACK]\n";
}
