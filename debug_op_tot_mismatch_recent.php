<?php
/**
 * debug_op_tot_mismatch_recent.php  —  READ-ONLY
 *
 * Tighter audit:
 *   - Date scope: ToT rows commissioned today or yesterday only.
 *   - Mismatch fields: property identity + party_2 (party_1 excluded —
 *     OP grantee legitimately becomes ToT grantor).
 *   - Land-use codes normalized (RES ≡ RESIDENTIAL, COM ≡ COMMERCIAL,
 *     IND ≡ INDUSTRIAL, AGR ≡ AGRICULTURE).
 *   - plot_no / tp_no stripped of non-alphanumerics before compare.
 *
 * Output: storage/logs/op-tot-mismatch-recent-report.md
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$conn = DB::connection('sqlsrv');

/* ------------------------------------------------------------------ */
/* helpers                                                             */
/* ------------------------------------------------------------------ */
$strip = fn ($v) => preg_replace('/[^A-Z0-9]/', '', strtoupper((string) ($v ?? '')));
$norm  = fn ($v) => strtoupper(trim((string) ($v ?? '')));

$landUse = function ($v) {
    $u = strtoupper(trim((string) ($v ?? '')));
    if ($u === '') return '';
    if (str_starts_with($u, 'RES')) return 'RESIDENTIAL';
    if (str_starts_with($u, 'COM')) return 'COMMERCIAL';
    if (str_starts_with($u, 'IND')) return 'INDUSTRIAL';
    if (str_starts_with($u, 'AGR')) return 'AGRICULTURE';
    return $u;
};

$diffStr   = function ($a, $b) use ($norm) { $a = $norm($a); $b = $norm($b); return $a !== '' && $b !== '' && $a !== $b; };
$diffAlnum = function ($a, $b) use ($strip) { $a = $strip($a); $b = $strip($b); return $a !== '' && $b !== '' && $a !== $b; };
$diffLU    = function ($a, $b) use ($landUse) { $a = $landUse($a); $b = $landUse($b); return $a !== '' && $b !== '' && $a !== $b; };

$canonical = function ($rows, callable $isMatch) {
    $matches = array_values(array_filter($rows, $isMatch));
    if (!$matches) return null;
    usort($matches, fn ($a, $b) => ($b->id ?? 0) <=> ($a->id ?? 0));
    return $matches[0];
};

/* ------------------------------------------------------------------ */
/* date window: yesterday 00:00 (server time) onward                   */
/* ------------------------------------------------------------------ */
$since = Carbon::yesterday()->startOfDay();
echo "Cutoff (ToT created_at >=): {$since}\n";

/* ------------------------------------------------------------------ */
/* Load PRA scope. Restrict ToT side to created_at >= $since.          */
/* OP side has no date restriction (the OP can be older than its ToT). */
/* ------------------------------------------------------------------ */
echo "Loading PRA OPs (OSSOPCHANGEOFNAME)...\n";
$praAll = $conn->table('pra')
    ->where('system_source', 'OSSOPCHANGEOFNAME')
    ->whereNotNull('prop_id')->where('prop_id', '!=', '')
    ->select([
        'id', 'prop_id', 'mlsFNo', 'fileno', 'temp_fileno',
        'instrument_type', 'transaction_type',
        'Grantor', 'Grantee', 'party_1', 'party_2',
        'regNo', 'op_serial_number',
        'land_use', 'plot_no', 'tp_no', 'lgsaOrCity',
        'location', 'property_description',
        'created_at',
    ])
    ->orderBy('prop_id')->orderBy('id')->get();
echo "  pra rows total: " . $praAll->count() . "\n";

$isOpPra  = fn ($r) => stripos((string) $r->instrument_type, 'Occupancy Permit') !== false
                    || stripos((string) $r->transaction_type, 'Occupancy Permit') !== false;
$isTotPra = fn ($r) => stripos((string) $r->instrument_type, 'Transfer of Title') !== false
                    || stripos((string) $r->transaction_type, 'Transfer of Title') !== false;
$isOpAny  = fn ($r) => stripos((string) ($r->instrument_type ?? ''), 'Occupancy Permit') !== false;
$isTotAny = fn ($r) => stripos((string) ($r->instrument_type ?? ''), 'Transfer of Title') !== false;

// PRA ToT rows in the date window
$praTotRecent = $praAll->filter(fn ($r) => $isTotPra($r) && Carbon::parse($r->created_at) >= $since);
echo "  pra ToT rows in window: " . $praTotRecent->count() . "\n";

$propIds = $praTotRecent->pluck('prop_id')->unique()->values();
echo "  distinct prop_ids in window: " . $propIds->count() . "\n";

if ($propIds->isEmpty()) {
    file_put_contents(__DIR__ . '/storage/logs/op-tot-mismatch-recent-report.md',
        "# OSS OP → ToT Recent Mismatch Audit\n\n_Generated: " . date('Y-m-d H:i:s')
        . "_\n\nNo Transfer of Title rows commissioned since {$since}.\n");
    echo "No recent ToTs. Report written.\n";
    exit;
}

/* IC + DR rows scoped to those prop_ids only */
$ic = collect();
foreach ($propIds->chunk(500) as $chunk) {
    $rows = $conn->table('instrument_capture')
        ->whereIn('prop_id', $chunk->all())
        ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })
        ->select([
            'id', 'prop_id', 'mlsFNo', 'temp_fileno',
            'instrument_type', 'registration_number',
            'op_serial_number',
            'party_1_name', 'party_2_name',
            'land_use', 'plot_number as plot_no', 'tp_no',
            'lga as lgsaOrCity', 'property_location as location',
            'property_description', 'created_at',
        ])->get();
    $ic = $ic->concat($rows);
}
echo "  ic rows (in scope): " . $ic->count() . "\n";

$dr = collect();
foreach ($propIds->chunk(500) as $chunk) {
    $rows = $conn->table('deed_registrations')
        ->whereIn('prop_id', $chunk->all())
        ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })
        ->select([
            'id', 'prop_id', 'fileno',
            'instrument_type', 'registration_number',
            'grantor', 'grantee',
            'plot_number as plot_no', 'lga as lgsaOrCity',
            'property_description', 'instrument_capture_id', 'created_at',
        ])->get();
    $dr = $dr->concat($rows);
}
echo "  dr rows (in scope): " . $dr->count() . "\n";

/* group everything by prop_id */
$praByProp = $praAll->groupBy('prop_id');
$icByProp  = $ic->groupBy('prop_id');
$drByProp  = $dr->groupBy('prop_id');

/* ------------------------------------------------------------------ */
/* Build pairs for each in-window prop_id                              */
/* ------------------------------------------------------------------ */
$pairs = [];
foreach ($propIds as $pid) {
    $praRows = $praByProp->get($pid, collect())->all();
    $icRows  = $icByProp->get($pid, collect())->all();
    $drRows  = $drByProp->get($pid, collect())->all();

    // canonical OP
    $opSrc = null; $op = null;
    if ($r = $canonical($praRows, $isOpPra))    { $op = $r; $opSrc = 'pra'; }
    elseif ($r = $canonical($icRows,  $isOpAny)){ $op = $r; $opSrc = 'ic';  }

    // canonical ToT must be one of the in-window rows
    $totSrc = null; $tot = null;
    $totCandidates = $praTotRecent->where('prop_id', $pid)->all();
    if ($totCandidates) {
        usort($totCandidates, fn ($a, $b) => ($b->id ?? 0) <=> ($a->id ?? 0));
        $tot = $totCandidates[0]; $totSrc = 'pra';
    }
    if (!$op || !$tot) continue;

    $opF = [
        'party_2'  => $op->party_2   ?? $op->Grantee       ?? $op->party_2_name ?? $op->grantee ?? null,
        'land_use' => $op->land_use  ?? null,
        'plot_no'  => $op->plot_no   ?? null,
        'tp_no'    => $op->tp_no     ?? null,
        'lga'      => $op->lgsaOrCity ?? null,
        'location' => $op->location  ?? $op->property_description ?? null,
        'party_1'  => $op->party_1   ?? $op->Grantor       ?? $op->party_1_name ?? $op->grantor ?? null,
        'temp'     => $op->temp_fileno ?? null,
        'fileno'   => ($op->mlsFNo ?? null) ?: ($op->fileno ?? null),
        'id'       => $op->id,
    ];
    $totF = [
        'party_2'  => $tot->party_2   ?? $tot->Grantee       ?? $tot->party_2_name ?? $tot->grantee ?? null,
        'land_use' => $tot->land_use  ?? null,
        'plot_no'  => $tot->plot_no   ?? null,
        'tp_no'    => $tot->tp_no     ?? null,
        'lga'      => $tot->lgsaOrCity ?? null,
        'location' => $tot->location  ?? $tot->property_description ?? null,
        'party_1'  => $tot->party_1   ?? $tot->Grantor       ?? $tot->party_1_name ?? $tot->grantor ?? null,
        'temp'     => $tot->temp_fileno ?? null,
        'fileno'   => ($tot->mlsFNo ?? null) ?: ($tot->fileno ?? null),
        'id'       => $tot->id,
        'created_at' => $tot->created_at,
    ];

    $diffs = [];
    if ($diffStr  ($opF['party_2'],  $totF['party_2']))  $diffs['party_2']  = ['op' => trim((string) $opF['party_2']),  'tot' => trim((string) $totF['party_2'])];
    if ($diffLU   ($opF['land_use'], $totF['land_use'])) $diffs['land_use'] = ['op' => trim((string) $opF['land_use']), 'tot' => trim((string) $totF['land_use'])];
    if ($diffAlnum($opF['plot_no'],  $totF['plot_no']))  $diffs['plot_no']  = ['op' => trim((string) $opF['plot_no']),  'tot' => trim((string) $totF['plot_no'])];
    if ($diffAlnum($opF['tp_no'],    $totF['tp_no']))    $diffs['tp_no']    = ['op' => trim((string) $opF['tp_no']),    'tot' => trim((string) $totF['tp_no'])];
    if ($diffStr  ($opF['lga'],      $totF['lga']))      $diffs['lga']      = ['op' => trim((string) $opF['lga']),      'tot' => trim((string) $totF['lga'])];
    if ($diffStr  ($opF['location'], $totF['location'])) $diffs['location'] = ['op' => trim((string) $opF['location']), 'tot' => trim((string) $totF['location'])];

    if (!$diffs) continue;

    $pairs[] = [
        'prop_id'   => $pid,
        'op_source' => $opSrc,
        'tot_source'=> $totSrc,
        'op'        => $opF,
        'tot'       => $totF,
        'diffs'     => $diffs,
        'same_temp' => $opF['temp']   && $opF['temp']   === $totF['temp'],
        'same_file' => $opF['fileno'] && $opF['fileno'] === $totF['fileno'],
    ];
}

echo "Mismatch pairs (recent, property-identity + party_2): " . count($pairs) . "\n";

/* ------------------------------------------------------------------ */
/* Markdown report                                                     */
/* ------------------------------------------------------------------ */
$path = __DIR__ . '/storage/logs/op-tot-mismatch-recent-report.md';
@mkdir(dirname($path), 0775, true);

$out = [];
$out[] = "# OSS OP → ToT Recent Mismatch Audit (PRA · IC · DR)";
$out[] = "";
$out[] = "_Generated: " . date('Y-m-d H:i:s') . "_  |  _READ-ONLY_";
$out[] = "";
$out[] = "**Date window:** ToT `created_at` ≥ `{$since}`  ";
$out[] = "**Mismatch fields:** `plot_no`, `tp_no`, `location`, `lga`, `land_use`, `party_2` (party_1 excluded — ToT grantor is legitimately the OP grantee).  ";
$out[] = "**Normalization:** land-use prefix-folded (RES↔RESIDENTIAL, COM↔COMMERCIAL, IND↔INDUSTRIAL, AGR↔AGRICULTURE); plot_no/tp_no stripped of non-alphanumerics; case + whitespace insensitive.  ";
$out[] = "**Empty values:** treated as 'cannot compare' (do not produce a mismatch).";
$out[] = "";
$out[] = "| Source | Rows in scope |";
$out[] = "|---|---|";
$out[] = "| pra ToT (in window) | " . $praTotRecent->count() . " |";
$out[] = "| pra OP (full)       | " . $praAll->filter($isOpPra)->count() . " |";
$out[] = "| ic OP (matched)     | " . $ic->filter($isOpAny)->count() . " |";
$out[] = "| dr (matched)        | " . $dr->count() . " |";
$out[] = "";
$out[] = "**Mismatch pairs found: " . count($pairs) . "**";
$out[] = "";

if ($pairs) {
    $out[] = "## Pairs";
    $out[] = "";
    $out[] = "| prop_id | OP src | OP id | ToT id | OP temp/file | ToT temp/file | ToT created_at | mismatched fields |";
    $out[] = "|---|---|---|---|---|---|---|---|";
    foreach ($pairs as $p) {
        $out[] = sprintf(
            "| %s | %s | %s | %s | %s / %s | %s / %s | %s | %s |",
            $p['prop_id'], $p['op_source'],
            $p['op']['id'], $p['tot']['id'],
            $p['op']['temp']  ?: '-', $p['op']['fileno']  ?: '-',
            $p['tot']['temp'] ?: '-', $p['tot']['fileno'] ?: '-',
            (string) $p['tot']['created_at'],
            implode(', ', array_keys($p['diffs']))
        );
    }
    $out[] = "";
    $out[] = "## Field-level diffs";
    $out[] = "";
    foreach ($pairs as $p) {
        $out[] = "### prop_id `{$p['prop_id']}` — OP `{$p['op']['fileno']}` (id {$p['op']['id']}, src {$p['op_source']}) vs ToT `{$p['tot']['fileno']}` (id {$p['tot']['id']})";
        $out[] = "";
        $out[] = "| field | OP value | ToT value |";
        $out[] = "|---|---|---|";
        foreach ($p['diffs'] as $f => $v) {
            $out[] = "| `{$f}` | " . ($v['op'] ?: '_(empty)_') . " | " . ($v['tot'] ?: '_(empty)_') . " |";
        }
        // also show the parties for context (not used in pair-decision)
        $out[] = "| _party_1 (info)_ | " . ($p['op']['party_1']  ?: '-') . " | " . ($p['tot']['party_1'] ?: '-') . " |";
        $out[] = "| _party_2 (info)_ | " . ($p['op']['party_2']  ?: '-') . " | " . ($p['tot']['party_2'] ?: '-') . " |";
        $out[] = "";
    }
}

file_put_contents($path, implode("\n", $out));
echo "\nReport: {$path}\n";
