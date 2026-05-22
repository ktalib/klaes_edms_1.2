<?php
/**
 * _fix_op_detach.php
 *
 * Fixes two wrongly-linked Occupancy Permit records.
 * OPs live in instrument_capture; TOTs live in pra.
 *
 *   Prop 69579486 — wrong OP TEMP-50177 (B80 Srtucture)  attached to TOT RES-2026-2220 (Aisha Abubakar Salisu)
 *   Prop 69574082 — wrong OP TEMP-50114 (Gali Rabiu)     attached to TOT RES-2026-2219 (Aisha Abubakar Salisu)
 *
 * For each:
 *   1. Detach the wrong OP — give instrument_capture row a new prop_id so it
 *      is no longer grouped with the TOT that belongs to a different person.
 *   2. Insert a new instrument_capture row (OP) under the original prop_id
 *      using the TOT's party / plot / land-use details.
 *
 * Usage (dry-run / preview):   php _fix_op_detach.php
 * Usage (commit):               php _fix_op_detach.php --execute
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\PropertyIdAllocationService;

$dryRun = !in_array('--execute', $argv ?? []);
$db     = DB::connection('sqlsrv');

echo "==========================================================\n";
echo " OP Detach Fix" . ($dryRun ? " [DRY-RUN — no writes]" : " [EXECUTE]") . "\n";
echo "==========================================================\n\n";

$targets = [
    ['prop_id' => 69579486, 'op_temp' => 'TEMP-50177', 'tot_mls' => 'RES-2026-2220'],
    ['prop_id' => 69574082, 'op_temp' => 'TEMP-50114', 'tot_mls' => 'RES-2026-2219'],
];

foreach ($targets as $target) {
    $propId = $target['prop_id'];
    $opTemp = $target['op_temp'];
    $totMls = $target['tot_mls'];

    echo "---\nProp ID : {$propId}\nWrong OP: {$opTemp}\nTOT     : {$totMls}\n\n";

    // --- Load the wrong OP from instrument_capture ---
    $op = $db->table('instrument_capture')
        ->whereRaw("TRY_CAST([prop_id] AS bigint) = ?", [$propId])
        ->where(function ($q) use ($opTemp) {
            $q->where('temp_fileno', $opTemp)->orWhere('mlsFNo', $opTemp);
        })
        ->whereRaw("instrument_type NOT LIKE '%Transfer%'")
        ->orderByDesc('id')
        ->first();

    if (!$op) {
        echo "  [SKIP] Could not find OP in instrument_capture for {$opTemp} / prop_id {$propId}\n\n";
        continue;
    }

    echo "  Found OP (instrument_capture.id={$op->id}):\n";
    echo "    instrument_type : " . ($op->instrument_type ?? '—') . "\n";
    echo "    op_type         : " . ($op->op_type ?? '—') . "\n";
    echo "    op_serial_number: " . ($op->op_serial_number ?? '—') . "\n";
    echo "    party_2_name    : " . ($op->party_2_name ?? '—') . "\n";
    echo "    land_use        : " . ($op->land_use ?? '—') . "\n";
    echo "    plot_number     : " . ($op->plot_number ?? '—') . "\n";
    echo "    current prop_id : {$op->prop_id}\n\n";

    // --- Load the TOT from pra ---
    $tot = $db->table('pra')
        ->whereRaw("TRY_CAST([prop_id] AS bigint) = ?", [$propId])
        ->where(function ($q) use ($totMls) {
            $q->where('mlsFNo', $totMls)->orWhere('fileno', $totMls);
        })
        ->whereRaw("instrument_type LIKE '%Transfer%'")
        ->orderByDesc('id')
        ->first();

    if (!$tot) {
        echo "  [SKIP] Could not find TOT in pra for {$totMls} / prop_id {$propId}\n\n";
        continue;
    }

    echo "  Found TOT (pra.id={$tot->id}):\n";
    echo "    instrument_type : " . ($tot->instrument_type ?? '—') . "\n";
    echo "    op_type         : " . ($tot->op_type ?? '—') . "\n";
    echo "    party_1         : " . ($tot->party_1 ?? '—') . "\n";
    echo "    party_2         : " . ($tot->party_2 ?? '—') . "\n";
    echo "    land_use        : " . ($tot->land_use ?? '—') . "\n";
    echo "    plot_no         : " . ($tot->plot_no ?? '—') . "\n";
    echo "    property_desc   : " . ($tot->property_description ?? '—') . "\n";
    echo "    location        : " . ($tot->location ?? '—') . "\n";
    echo "    mlsFNo          : " . ($tot->mlsFNo ?? '—') . "\n";
    echo "    temp_fileno     : " . ($tot->temp_fileno ?? '—') . "\n\n";

    // --- Allocate a new prop_id for the detached OP ---
    /** @var PropertyIdAllocationService $allocator */
    $allocator = app(PropertyIdAllocationService::class);
    $newPropId = $allocator->allocateOrRetrievePropId(
        primaryFileNumber: $opTemp,
        mlsFNo: $opTemp,
        options: ['allow_temp_only' => true, 'skip_lookup' => true, 'temp_fileno' => $opTemp]
    );

    echo "  Plan:\n";
    echo "    UPDATE instrument_capture SET prop_id={$newPropId} WHERE id={$op->id}  (detach wrong OP)\n";
    echo "    INSERT new OP into instrument_capture  (instrument_type='Occupancy Permit (OP)', prop_id={$propId}, based on TOT details)\n\n";

    if ($dryRun) {
        echo "  [DRY-RUN] No changes written. Re-run with --execute to apply.\n\n";
        continue;
    }

    // --- Execute in a transaction ---
    $db->beginTransaction();
    try {
        // 1. Detach the wrong OP — give it the new prop_id
        $updated = $db->table('instrument_capture')
            ->where('id', $op->id)
            ->update(['prop_id' => $newPropId, 'updated_at' => now()]);

        echo "  [OK] instrument_capture.id={$op->id} → prop_id={$newPropId}  ({$updated} row)\n";

        // 2. Insert new OP under the original prop_id using TOT details
        $newOp = [
            'prop_id'        => $propId,
            'mlsFNo'         => $tot->mlsFNo ?? $totMls,
            'temp_fileno'    => $tot->temp_fileno ?? $opTemp,
            'instrument_type'=> 'Occupancy Permit (OP)',
            'op_type'        => $tot->op_type ?? null,
            'party_1_name'   => 'Kano State Government',
            'party_2_name'   => $tot->party_1 ?? null,
            'land_use'       => $tot->land_use ?? null,
            'plot_number'    => $tot->plot_no ?? null,
            'created_at'     => now(),
            'updated_at'     => now(),
        ];

        $newIcId = $db->table('instrument_capture')->insertGetId($newOp);

        echo "  [OK] New OP inserted instrument_capture.id={$newIcId}  prop_id={$propId}\n\n";

        $db->commit();
        echo "  [COMMITTED]\n\n";
    } catch (\Throwable $e) {
        $db->rollBack();
        echo "  [ERROR] " . $e->getMessage() . "\n";
        echo "  [ROLLED BACK]\n\n";
    }
}

echo "==========================================================\n";
echo " Done" . ($dryRun ? " (dry-run — nothing was written)" : " (changes committed)") . "\n";
echo "==========================================================\n";
