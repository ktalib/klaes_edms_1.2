<?php
/**
 * debug_op_tot_mismatch_full.php
 *
 * Cross-source OSS Change-of-Name OP -> ToT mismatch audit covering:
 *   - pra (system_source = 'OSSOPCHANGEOFNAME')
 *   - instrument_capture (ic)
 *   - deed_registrations (dr)
 *
 * Pairs OP and ToT records that share the same prop_id but disagree on
 * party_1, party_2, land_use, plot_no, tp_no, location, lga.
 *
 * READ-ONLY. No INSERT / UPDATE / DELETE is executed.
 *
 * Output: storage/logs/op-tot-mismatch-full-report.md
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$conn = DB::connection('sqlsrv');

/* ------------------------------------------------------------------ */
/* helpers                                                             */
/* ------------------------------------------------------------------ */
$norm = fn ($v) => strtoupper(trim((string) ($v ?? '')));
$diff = function ($a, $b) use ($norm) {
    $a = $norm($a); $b = $norm($b);
    return $a !== '' && $b !== '' && $a !== $b;
};

/** Reduce one source's rows into a canonical OP record (most recent). */
$canonicalRow = function ($rows, callable $isMatch) {
    $matches = array_values(array_filter($rows, $isMatch));
    if (!$matches) return null;
    usort($matches, fn ($a, $b) => ($b->id ?? 0) <=> ($a->id ?? 0));
    return $matches[0];
};

/* ------------------------------------------------------------------ */
/* 1. Load PRA, IC, DR rows that participate in OSS Change-of-Name     */
/*    Use prop_id as the join key.                                     */
/* ------------------------------------------------------------------ */
echo "Loading PRA rows (OSSOPCHANGEOFNAME)...\n";
$pra = $conn->table('pra')
    ->where('system_source', 'OSSOPCHANGEOFNAME')
    ->whereNotNull('prop_id')->where('prop_id', '!=', '')
    ->select([
        'id', 'prop_id', 'mlsFNo', 'fileno', 'temp_fileno',
        'instrument_type', 'transaction_type',
        'Grantor', 'Grantee', 'party_1', 'party_2',
        'regNo', 'op_type', 'op_serial_number',
        'land_use', 'plot_no', 'tp_no', 'lgsaOrCity',
        'location', 'property_description',
        'created_at', 'created_by',
    ])
    ->orderBy('prop_id')->orderBy('id')->get();
echo "  pra rows: " . $pra->count() . "\n";

// Collect prop_ids in scope from PRA
$propIds = $pra->pluck('prop_id')->unique()->filter(fn ($p) => $p !== '' && $p !== null)->values();

echo "Loading IC rows (prop_id matches PRA scope)...\n";
$ic = collect();
foreach ($propIds->chunk(500) as $chunk) {
    $rows = $conn->table('instrument_capture')
        ->whereIn('prop_id', $chunk->all())
        ->where(function ($q) {
            $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
        })
        ->select([
            'id', 'prop_id', 'mlsFNo', 'temp_fileno',
            'instrument_type', 'registration_number',
            'op_type', 'op_serial_number',
            'party_1_name', 'party_2_name',
            'land_use', 'plot_number as plot_no', 'tp_no',
            'lga as lgsaOrCity', 'property_location as location',
            'property_description',
            'created_at', 'created_by',
        ])
        ->get();
    $ic = $ic->concat($rows);
}
echo "  ic rows: " . $ic->count() . "\n";

echo "Loading DR rows (prop_id matches PRA scope)...\n";
$dr = collect();
foreach ($propIds->chunk(500) as $chunk) {
    $rows = $conn->table('deed_registrations')
        ->whereIn('prop_id', $chunk->all())
        ->where(function ($q) {
            $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
        })
        ->select([
            'id', 'prop_id', 'fileno',
            'instrument_type', 'registration_number',
            'grantor', 'grantee',
            'plot_number as plot_no', 'lga as lgsaOrCity',
            'property_description',
            'instrument_capture_id',
            'created_at', 'created_by',
        ])
        ->get();
    $dr = $dr->concat($rows);
}
echo "  dr rows: " . $dr->count() . "\n";

/* ------------------------------------------------------------------ */
/* 2. Group everything by prop_id                                      */
/* ------------------------------------------------------------------ */
$praByProp = $pra->groupBy('prop_id');
$icByProp  = $ic->groupBy('prop_id');
$drByProp  = $dr->groupBy('prop_id');

$isOpPra  = fn ($r) => stripos((string) $r->instrument_type, 'Occupancy Permit') !== false
                    || stripos((string) $r->transaction_type, 'Occupancy Permit') !== false;
$isTotPra = fn ($r) => stripos((string) $r->instrument_type, 'Transfer of Title') !== false
                    || stripos((string) $r->transaction_type, 'Transfer of Title') !== false;
$isOpAny  = fn ($r) => stripos((string) ($r->instrument_type ?? ''), 'Occupancy Permit') !== false;
$isTotAny = fn ($r) => stripos((string) ($r->instrument_type ?? ''), 'Transfer of Title') !== false;

/* ------------------------------------------------------------------ */
/* 3. Build a canonical OP and canonical ToT per prop_id, preferring   */
/*    the most authoritative source for each leg:                      */
/*       OP  : pra OP -> ic OP                                         */
/*       ToT : pra ToT -> dr ToT -> ic ToT                             */
/* ------------------------------------------------------------------ */
$pairs = [];
foreach ($propIds as $pid) {
    $praRows = $praByProp->get($pid, collect())->all();
    $icRows  = $icByProp->get($pid, collect())->all();
    $drRows  = $drByProp->get($pid, collect())->all();

    // Canonical OP
    $opSource = null; $op = null;
    if ($r = $canonicalRow($praRows, $isOpPra))    { $op = $r; $opSource = 'pra'; }
    elseif ($r = $canonicalRow($icRows, $isOpAny)) { $op = $r; $opSource = 'ic'; }

    // Canonical ToT
    $totSource = null; $tot = null;
    if ($r = $canonicalRow($praRows, $isTotPra))    { $tot = $r; $totSource = 'pra'; }
    elseif ($r = $canonicalRow($drRows, $isTotAny)) { $tot = $r; $totSource = 'dr'; }
    elseif ($r = $canonicalRow($icRows, $isTotAny)) { $tot = $r; $totSource = 'ic'; }

    if (!$op || !$tot) continue;

    // Normalize fields across the 3 source schemas
    $opFields = [
        'party_1'  => $op->party_1   ?? $op->Grantor       ?? $op->party_1_name ?? $op->grantor ?? null,
        'party_2'  => $op->party_2   ?? $op->Grantee       ?? $op->party_2_name ?? $op->grantee ?? null,
        'land_use' => $op->land_use  ?? null,
        'plot_no'  => $op->plot_no   ?? null,
        'tp_no'    => $op->tp_no     ?? null,
        'lga'      => $op->lgsaOrCity ?? null,
        'location' => $op->location  ?? $op->property_description ?? null,
        'temp'     => $op->temp_fileno ?? null,
        'fileno'   => ($op->mlsFNo ?? null) ?: ($op->fileno ?? null),
        'reg_no'   => $op->regNo ?? $op->registration_number ?? null,
        'op_serial'=> $op->op_serial_number ?? null,
        'id'       => $op->id,
    ];
    $totFields = [
        'party_1'  => $tot->party_1   ?? $tot->Grantor       ?? $tot->party_1_name ?? $tot->grantor ?? null,
        'party_2'  => $tot->party_2   ?? $tot->Grantee       ?? $tot->party_2_name ?? $tot->grantee ?? null,
        'land_use' => $tot->land_use  ?? null,
        'plot_no'  => $tot->plot_no   ?? null,
        'tp_no'    => $tot->tp_no     ?? null,
        'lga'      => $tot->lgsaOrCity ?? null,
        'location' => $tot->location  ?? $tot->property_description ?? null,
        'temp'     => $tot->temp_fileno ?? null,
        'fileno'   => ($tot->mlsFNo ?? null) ?: ($tot->fileno ?? null),
        'reg_no'   => $tot->regNo ?? $tot->registration_number ?? null,
        'op_serial'=> $tot->op_serial_number ?? null,
        'id'       => $tot->id,
    ];

    $diffs = [];
    foreach (['party_1','party_2','land_use','plot_no','tp_no','lga','location'] as $f) {
        if ($diff($opFields[$f], $totFields[$f])) {
            $diffs[$f] = ['op' => trim((string) $opFields[$f]), 'tot' => trim((string) $totFields[$f])];
        }
    }
    if (!$diffs) continue;

    $pairs[] = [
        'prop_id'    => $pid,
        'op_source'  => $opSource,
        'tot_source' => $totSource,
        'op'         => $opFields,
        'tot'        => $totFields,
        'diffs'      => $diffs,
        'same_temp'  => $opFields['temp']   && $opFields['temp']   === $totFields['temp'],
        'same_file'  => $opFields['fileno'] && $opFields['fileno'] === $totFields['fileno'],
        'has_pra_op' => (bool) $canonicalRow($praRows, $isOpPra),
        'has_pra_tot'=> (bool) $canonicalRow($praRows, $isTotPra),
        'has_ic_op'  => (bool) $canonicalRow($icRows,  $isOpAny),
        'has_ic_tot' => (bool) $canonicalRow($icRows,  $isTotAny),
        'has_dr'     => count($drRows) > 0,
    ];
}

echo "Mismatch pairs: " . count($pairs) . "\n";

/* ------------------------------------------------------------------ */
/* 4. Sibling-OP scan: rows that share prop_id within same source      */
/* ------------------------------------------------------------------ */
$dupPra = $pra->filter($isOpPra)->groupBy('prop_id')->filter(fn ($g) => $g->count() > 1);
$dupIc  = $ic ->filter($isOpAny)->groupBy('prop_id')->filter(fn ($g) => $g->count() > 1);

/* ------------------------------------------------------------------ */
/* 5. Pinpoint the two screenshot pairs                                */
/* ------------------------------------------------------------------ */
$targets = [
    ['prop_id' => '69572020', 'temp' => 'TEMP-34056', 'fileno' => 'COM-2026-193'],
    ['prop_id' => '87946',    'temp' => 'TEMP-34021', 'fileno' => 'RES-2026-2087'],
];
$targetReports = [];
foreach ($targets as $t) {
    $entry = ['target' => $t, 'pra' => [], 'ic' => [], 'dr' => []];
    $entry['pra'] = $conn->table('pra')
        ->where(function ($q) use ($t) {
            $q->where('prop_id', $t['prop_id'])
              ->orWhere('temp_fileno', $t['temp'])
              ->orWhere('mlsFNo', $t['fileno'])
              ->orWhere('fileno', $t['fileno']);
        })
        ->orderBy('id')
        ->get(['id','prop_id','temp_fileno','mlsFNo','fileno','instrument_type','transaction_type','Grantor','Grantee','party_1','party_2','land_use','plot_no','tp_no','regNo','op_serial_number','lgsaOrCity','location','created_at']);
    $entry['ic'] = $conn->table('instrument_capture')
        ->where(function ($q) use ($t) {
            $q->where('prop_id', $t['prop_id'])
              ->orWhere('temp_fileno', $t['temp'])
              ->orWhere('mlsFNo', $t['fileno']);
        })
        ->orderBy('id')
        ->get(['id','prop_id','temp_fileno','mlsFNo','instrument_type','party_1_name','party_2_name','land_use','plot_number','tp_no','registration_number','op_serial_number','lga','property_location','is_deleted','created_at']);
    $entry['dr'] = $conn->table('deed_registrations')
        ->where(function ($q) use ($t) {
            $q->where('prop_id', $t['prop_id'])
              ->orWhere('fileno', $t['fileno']);
        })
        ->orderBy('id')
        ->get(['id','prop_id','fileno','instrument_type','registration_number','grantor','grantee','plot_number','lga','property_description','instrument_capture_id','is_deleted','created_at']);
    $targetReports[] = $entry;
}

/* ------------------------------------------------------------------ */
/* 6. Render markdown                                                  */
/* ------------------------------------------------------------------ */
$path = __DIR__ . '/storage/logs/op-tot-mismatch-full-report.md';
@mkdir(dirname($path), 0775, true);

$out = [];
$out[] = "# OSS OP → ToT Cross-Source Mismatch Audit (PRA · IC · DR)";
$out[] = "";
$out[] = "_Generated: " . date('Y-m-d H:i:s') . "_  |  _Read-only audit (no DB writes)_";
$out[] = "";
$out[] = "Scope: every `prop_id` that appears in `pra` with `system_source = 'OSSOPCHANGEOFNAME'`. For each prop_id, a canonical OP and canonical ToT are picked across `pra` → `ic` → `dr` (PRA preferred, then DR for ToT, then IC). Pair is reported when canonical OP and canonical ToT disagree on any of: party_1, party_2, land_use, plot_no, tp_no, lga, location.";
$out[] = "";
$out[] = "| Source | OP rows considered | ToT rows considered |";
$out[] = "|---|---|---|";
$out[] = "| pra (OSSOPCHANGEOFNAME) | " . $pra->filter($isOpPra)->count() . " | " . $pra->filter($isTotPra)->count() . " |";
$out[] = "| instrument_capture | " . $ic->filter($isOpAny)->count() . " | " . $ic->filter($isTotAny)->count() . " |";
$out[] = "| deed_registrations | " . $dr->filter($isOpAny)->count() . " | " . $dr->filter($isTotAny)->count() . " |";
$out[] = "";
$out[] = "**Mismatch pairs found: " . count($pairs) . "**";
$out[] = "";

/* ---------- Section 1: Screenshot cases ---------- */
$out[] = "## 1. Screenshot Cases";
$out[] = "";
foreach ($targetReports as $tr) {
    $t = $tr['target'];
    $out[] = "### prop_id `{$t['prop_id']}` — `{$t['temp']}` / `{$t['fileno']}`";
    $out[] = "";
    foreach (['pra', 'ic', 'dr'] as $tbl) {
        $rows = $tr[$tbl];
        $out[] = "**`{$tbl}`** (" . $rows->count() . " rows)";
        $out[] = "";
        if ($rows->isEmpty()) { $out[] = "_no rows_"; $out[] = ""; continue; }
        if ($tbl === 'pra') {
            $out[] = "| id | prop_id | type | temp | file | party_1 | party_2 | land_use | plot | tp_no | reg_no | created_at |";
            $out[] = "|---|---|---|---|---|---|---|---|---|---|---|---|";
            foreach ($rows as $r) {
                $type = $r->instrument_type ?: ($r->transaction_type ?: '');
                $file = $r->mlsFNo ?: $r->fileno ?: '';
                $p1 = $r->party_1 ?: $r->Grantor ?: '';
                $p2 = $r->party_2 ?: $r->Grantee ?: '';
                $out[] = "| {$r->id} | {$r->prop_id} | {$type} | {$r->temp_fileno} | {$file} | {$p1} | {$p2} | {$r->land_use} | {$r->plot_no} | {$r->tp_no} | {$r->regNo} | {$r->created_at} |";
            }
        } elseif ($tbl === 'ic') {
            $out[] = "| id | prop_id | type | temp | mlsFNo | party_1_name | party_2_name | land_use | plot | tp_no | reg_no | is_deleted | created_at |";
            $out[] = "|---|---|---|---|---|---|---|---|---|---|---|---|---|";
            foreach ($rows as $r) {
                $out[] = "| {$r->id} | {$r->prop_id} | {$r->instrument_type} | {$r->temp_fileno} | {$r->mlsFNo} | {$r->party_1_name} | {$r->party_2_name} | {$r->land_use} | {$r->plot_number} | {$r->tp_no} | {$r->registration_number} | " . ($r->is_deleted ?? 0) . " | {$r->created_at} |";
            }
        } else {
            $out[] = "| id | prop_id | type | fileno | grantor | grantee | plot | lga | reg_no | ic_id | is_deleted | created_at |";
            $out[] = "|---|---|---|---|---|---|---|---|---|---|---|---|";
            foreach ($rows as $r) {
                $out[] = "| {$r->id} | {$r->prop_id} | {$r->instrument_type} | {$r->fileno} | {$r->grantor} | {$r->grantee} | {$r->plot_number} | {$r->lga} | {$r->registration_number} | {$r->instrument_capture_id} | " . ($r->is_deleted ?? 0) . " | {$r->created_at} |";
            }
        }
        $out[] = "";
    }
}

/* ---------- Section 2: All pairs ---------- */
$out[] = "## 2. All Mismatched OP↔ToT Pairs (cross-source)";
$out[] = "";
$out[] = "Total: **" . count($pairs) . "**";
$out[] = "";
if ($pairs) {
    $out[] = "| prop_id | OP src | ToT src | OP id | ToT id | OP temp/file | ToT temp/file | same temp? | same file? | mismatched fields |";
    $out[] = "|---|---|---|---|---|---|---|---|---|---|";
    foreach ($pairs as $p) {
        $out[] = sprintf(
            "| %s | %s | %s | %s | %s | %s / %s | %s / %s | %s | %s | %s |",
            $p['prop_id'], $p['op_source'], $p['tot_source'],
            $p['op']['id'], $p['tot']['id'],
            $p['op']['temp']  ?: '-', $p['op']['fileno']  ?: '-',
            $p['tot']['temp'] ?: '-', $p['tot']['fileno'] ?: '-',
            $p['same_temp'] ? 'YES' : 'no',
            $p['same_file'] ? 'YES' : 'no',
            implode(', ', array_keys($p['diffs']))
        );
    }
    $out[] = "";
}

/* ---------- Section 3: Source-coverage breakdown ---------- */
$bySrc = [];
foreach ($pairs as $p) {
    $key = $p['op_source'] . ' → ' . $p['tot_source'];
    $bySrc[$key] = ($bySrc[$key] ?? 0) + 1;
}
ksort($bySrc);
$out[] = "## 3. Pairs Grouped by Source Combination";
$out[] = "";
$out[] = "| OP source → ToT source | pairs |";
$out[] = "|---|---|";
foreach ($bySrc as $k => $v) { $out[] = "| {$k} | {$v} |"; }
$out[] = "";

/* ---------- Section 4: Same-source duplicate-prop_id OP siblings ---------- */
$out[] = "## 4. Duplicate-prop_id OP Siblings (same table)";
$out[] = "";
$out[] = "Distinct prop_ids with >1 OP row in **pra**: **" . $dupPra->count() . "**  ";
$out[] = "Distinct prop_ids with >1 OP row in **ic**: **" . $dupIc->count() . "**";
$out[] = "";
$shown = 0;
foreach ($dupPra as $pid => $sibs) {
    if ($shown++ >= 10) { $out[] = "_… pra siblings truncated at 10_"; break; }
    $out[] = "### pra · prop_id `{$pid}` ({$sibs->count()} OP rows)";
    $out[] = "";
    $out[] = "| id | temp_fileno | file | party_1 | party_2 | land_use | plot | tp_no | created_at |";
    $out[] = "|---|---|---|---|---|---|---|---|---|";
    foreach ($sibs as $r) {
        $file = $r->mlsFNo ?: $r->fileno ?: '';
        $p1 = $r->party_1 ?: $r->Grantor ?: '';
        $p2 = $r->party_2 ?: $r->Grantee ?: '';
        $out[] = "| {$r->id} | {$r->temp_fileno} | {$file} | {$p1} | {$p2} | {$r->land_use} | {$r->plot_no} | {$r->tp_no} | {$r->created_at} |";
    }
    $out[] = "";
}
$shown = 0;
foreach ($dupIc as $pid => $sibs) {
    if ($shown++ >= 10) { $out[] = "_… ic siblings truncated at 10_"; break; }
    $out[] = "### ic · prop_id `{$pid}` ({$sibs->count()} OP rows)";
    $out[] = "";
    $out[] = "| id | temp_fileno | mlsFNo | party_1_name | party_2_name | land_use | plot | tp_no | created_at |";
    $out[] = "|---|---|---|---|---|---|---|---|---|";
    foreach ($sibs as $r) {
        $out[] = "| {$r->id} | {$r->temp_fileno} | {$r->mlsFNo} | {$r->party_1_name} | {$r->party_2_name} | {$r->land_use} | {$r->plot_no} | {$r->tp_no} | {$r->created_at} |";
    }
    $out[] = "";
}

/* ---------- Section 5: Root cause + fix ---------- */
$out[] = "## 5. Root Cause";
$out[] = "";
$out[] = "Across all three tables (`pra`, `instrument_capture`, `deed_registrations`) the affected pairs share the same pattern:";
$out[] = "";
$out[] = "1. The OSS *FileNo Commissioning* flow saved a ToT row whose `prop_id` was inherited from an **unrelated OP** (different parties, different plot, different land use).";
$out[] = "2. The mismatch can be cross-table: the OP card on the page may come from `pra` while the ToT it gets paired with lives in `pra` (or, less commonly, the OP from `instrument_capture` and the ToT from `pra`/`dr`).";
$out[] = "3. `deed_registrations` rows for these ToTs use the same contaminated `prop_id` because they are written from the IC/PRA payload at registration time.";
$out[] = "4. The page query (`OpResettlementApplicationController::index`) joins OP↔ToT by `prop_id`, so the visible card pair shows two unrelated transactions.";
$out[] = "";
$out[] = "The duplicate-prop_id resolver added in `InstrumentController::resolveOpDuplicates` prevents *new* commissions from inheriting a sibling's prop_id (it reassigns conflicting siblings via `temp_fileno_sequence` + `PropertyIdAllocationService`). Pre-existing rows still need a one-time corrective scan.";
$out[] = "";
$out[] = "## 6. Proposed One-Time Fix (read-only first)";
$out[] = "";
$out[] = "Add an artisan command `oss:audit-op-tot-mismatch` that mirrors this script (PRA + IC + DR) and writes a CSV to `storage/logs/`. After ops review, a follow-up command `oss:relink-op-tot` would, **inside a transaction per pair**:";
$out[] = "";
$out[] = "1. Identify the **correct OP** for each affected ToT by matching on `op_serial_number` + `regNo` (or `party_2`/Grantee + `plot_no`) within `instrument_capture` first, then `pra`.";
$out[] = "2. Allocate a fresh `prop_id` and `temp_fileno` for the ToT row via `PropertyIdAllocationService::allocateOrRetrievePropId(['allow_temp_only' => true, 'skip_lookup' => true])` and `temp_fileno_sequence`.";
$out[] = "3. Update the ToT row in **all three tables** consistently:";
$out[] = "   - `pra`: set `prop_id` and (if `temp_fileno` was inherited) `temp_fileno`.";
$out[] = "   - `deed_registrations`: set `prop_id` (column is `fileno`, not `temp_fileno`).";
$out[] = "   - `instrument_capture`: set `prop_id` and `temp_fileno`.";
$out[] = "4. Log every change to `storage/logs/op-tot-relink-YYYYMMDD.log` with old + new values for rollback.";
$out[] = "";
$out[] = "**No DB writes will be performed without explicit ops approval.**";
$out[] = "";

file_put_contents($path, implode("\n", $out));
echo "\nReport: {$path}\n";
echo "Pairs: " . count($pairs) . "\n";
