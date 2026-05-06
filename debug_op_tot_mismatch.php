<?php
/**
 * debug_op_tot_mismatch.php
 *
 * Audits OSS-commissioned OP -> ToT pairs where the OP and ToT rows share the
 * same prop_id (and often the same temp_fileno) but have mismatching property
 * facts (party_1, party_2, land_use, plot_no, tp_no, location).
 *
 * Two specific pairs from the user's screenshots are pinpointed:
 *   1) TEMP-34056 / COM-2026-193   prop_id = 69572020
 *   2) TEMP-34021 / RES-2026-2087  prop_id = 87946
 *
 * Run from the project root:
 *      php debug_op_tot_mismatch.php
 *
 * Outputs a markdown report at: storage/logs/op-tot-mismatch-report.md
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$conn = DB::connection('sqlsrv');

/** Compare two strings ignoring whitespace + case; treat empty/null as match-any. */
$diffField = function ($op, $tot) {
    $a = strtoupper(trim((string) ($op ?? '')));
    $b = strtoupper(trim((string) ($tot ?? '')));
    if ($a === '' || $b === '') return false; // can't compare
    return $a !== $b;
};

/** Return list of mismatched logical fields between an OP row and a ToT row. */
$compareRows = function (object $op, object $tot) use ($diffField): array {
    $checks = [
        'party_1'   => [$op->party_1   ?? $op->Grantor ?? null, $tot->party_1   ?? $tot->Grantor ?? null],
        'party_2'   => [$op->party_2   ?? $op->Grantee ?? null, $tot->party_2   ?? $tot->Grantee ?? null],
        'land_use'  => [$op->land_use  ?? null, $tot->land_use  ?? null],
        'plot_no'   => [$op->plot_no   ?? null, $tot->plot_no   ?? null],
        'tp_no'     => [$op->tp_no     ?? null, $tot->tp_no     ?? null],
        'location'  => [$op->location  ?? $op->property_description ?? null,
                        $tot->location ?? $tot->property_description ?? null],
        'lgsaOrCity' => [$op->lgsaOrCity ?? null, $tot->lgsaOrCity ?? null],
    ];
    $mismatches = [];
    foreach ($checks as $field => [$a, $b]) {
        if ($diffField($a, $b)) {
            $mismatches[$field] = ['op' => trim((string) $a), 'tot' => trim((string) $b)];
        }
    }
    return $mismatches;
};

/* ------------------------------------------------------------------ */
/* 1. Pull all OSS PRA rows; group by prop_id; find prop_ids that have */
/*    BOTH an OP row and a ToT row.                                    */
/* ------------------------------------------------------------------ */
echo "Loading OSS PRA rows...\n";

$rows = $conn->table('pra')
    ->where('system_source', 'OSSOPCHANGEOFNAME')
    ->whereNotNull('prop_id')
    ->where('prop_id', '!=', '')
    ->select([
        'id', 'prop_id', 'mlsFNo', 'fileno', 'temp_fileno',
        'instrument_type', 'transaction_type',
        'Grantor', 'Grantee', 'party_1', 'party_2',
        'regNo', 'op_type', 'op_serial_number',
        'land_use', 'plot_no', 'tp_no', 'lgsaOrCity',
        'location', 'property_description',
        'created_at', 'created_by',
    ])
    ->orderBy('prop_id')
    ->orderBy('id')
    ->get();

echo "  fetched " . $rows->count() . " rows\n";

$byProp = [];
foreach ($rows as $r) {
    $byProp[$r->prop_id][] = $r;
}

$isOp  = fn ($r) => stripos((string) $r->instrument_type, 'Occupancy Permit') !== false
                || stripos((string) $r->transaction_type, 'Occupancy Permit') !== false;
$isTot = fn ($r) => stripos((string) $r->instrument_type, 'Transfer of Title') !== false
                || stripos((string) $r->transaction_type, 'Transfer of Title') !== false;

$mismatchPairs = [];
foreach ($byProp as $propId => $group) {
    $ops  = array_values(array_filter($group, $isOp));
    $tots = array_values(array_filter($group, $isTot));
    if (!$ops || !$tots) continue;

    // Use the most recent OP and the most recent ToT (matches the page's RN=1 logic).
    usort($ops,  fn ($a, $b) => $b->id <=> $a->id);
    usort($tots, fn ($a, $b) => $b->id <=> $a->id);
    $op  = $ops[0];
    $tot = $tots[0];

    $diffs = $compareRows($op, $tot);
    if (!empty($diffs)) {
        $mismatchPairs[] = [
            'prop_id'      => $propId,
            'op_temp'      => $op->temp_fileno,
            'op_fileno'    => $op->mlsFNo ?: $op->fileno,
            'tot_temp'     => $tot->temp_fileno,
            'tot_fileno'   => $tot->mlsFNo ?: $tot->fileno,
            'op_id'        => $op->id,
            'tot_id'       => $tot->id,
            'op_created'   => (string) $op->created_at,
            'tot_created'  => (string) $tot->created_at,
            'same_temp'    => trim((string) $op->temp_fileno) !== ''
                              && trim((string) $op->temp_fileno) === trim((string) $tot->temp_fileno),
            'same_fileno'  => ($op->mlsFNo ?: $op->fileno)
                              && ($op->mlsFNo ?: $op->fileno) === ($tot->mlsFNo ?: $tot->fileno),
            'mismatches'   => $diffs,
        ];
    }
}

echo "  found " . count($mismatchPairs) . " mismatch pair(s)\n";

/* ------------------------------------------------------------------ */
/* 2. Sibling-OP audit: find all OP rows that share a prop_id (the    */
/*    duplicate-prop_id pathology that produced these mismatches).    */
/* ------------------------------------------------------------------ */
echo "Scanning for duplicate prop_id OP siblings...\n";

$dupPropIds = $conn->table('pra')
    ->where('system_source', 'OSSOPCHANGEOFNAME')
    ->where(function ($q) {
        $q->where('instrument_type', 'LIKE', '%Occupancy Permit%')
          ->orWhere('transaction_type', 'LIKE', '%Occupancy Permit%');
    })
    ->whereNotNull('prop_id')
    ->where('prop_id', '!=', '')
    ->groupBy('prop_id')
    ->havingRaw('COUNT(*) > 1')
    ->pluck('prop_id');

echo "  duplicate-prop_id OP groups: " . $dupPropIds->count() . "\n";

$dupGroups = [];
foreach ($dupPropIds as $pid) {
    $siblings = $conn->table('pra')
        ->where('system_source', 'OSSOPCHANGEOFNAME')
        ->where('prop_id', $pid)
        ->where(function ($q) {
            $q->where('instrument_type', 'LIKE', '%Occupancy Permit%')
              ->orWhere('transaction_type', 'LIKE', '%Occupancy Permit%');
        })
        ->orderBy('id')
        ->get([
            'id', 'temp_fileno', 'mlsFNo', 'fileno',
            'party_1', 'party_2', 'Grantor', 'Grantee',
            'land_use', 'plot_no', 'tp_no', 'regNo', 'op_serial_number',
            'created_at',
        ]);
    if ($siblings->count() < 2) continue;
    $dupGroups[$pid] = $siblings;
}

/* ------------------------------------------------------------------ */
/* 3. Pinpoint the two screenshot pairs explicitly.                    */
/* ------------------------------------------------------------------ */
$targets = [
    ['prop_id' => '69572020', 'temp' => 'TEMP-34056', 'fileno' => 'COM-2026-193'],
    ['prop_id' => '87946',    'temp' => 'TEMP-34021', 'fileno' => 'RES-2026-2087'],
];

$targetReports = [];
foreach ($targets as $t) {
    $set = $conn->table('pra')
        ->where('system_source', 'OSSOPCHANGEOFNAME')
        ->where(function ($q) use ($t) {
            $q->where('prop_id', $t['prop_id'])
              ->orWhere('temp_fileno', $t['temp'])
              ->orWhere('mlsFNo', $t['fileno'])
              ->orWhere('fileno', $t['fileno']);
        })
        ->orderBy('id')
        ->get([
            'id', 'prop_id', 'temp_fileno', 'mlsFNo', 'fileno',
            'instrument_type', 'transaction_type',
            'Grantor', 'Grantee', 'party_1', 'party_2',
            'land_use', 'plot_no', 'tp_no', 'regNo', 'op_serial_number',
            'location', 'property_description', 'lgsaOrCity',
            'created_at', 'created_by',
        ]);

    $targetReports[] = ['target' => $t, 'rows' => $set];
}

/* ------------------------------------------------------------------ */
/* 4. Render markdown report.                                          */
/* ------------------------------------------------------------------ */
$reportPath = __DIR__ . '/storage/logs/op-tot-mismatch-report.md';
@mkdir(dirname($reportPath), 0775, true);

$md = [];
$md[] = "# OSS OP → ToT Party / Property Mismatch Audit";
$md[] = "";
$md[] = "_Generated: " . date('Y-m-d H:i:s') . "_";
$md[] = "_Source filter: `pra.system_source = 'OSSOPCHANGEOFNAME'`_";
$md[] = "";
$md[] = "## 1. Screenshot Cases";
$md[] = "";

foreach ($targetReports as $tr) {
    $t = $tr['target'];
    $md[] = "### prop_id `{$t['prop_id']}` — `{$t['temp']}` / `{$t['fileno']}`";
    $md[] = "";
    if ($tr['rows']->isEmpty()) {
        $md[] = "_No matching `pra` rows were found for this target._";
        $md[] = "";
        continue;
    }

    $md[] = "| pra.id | instrument_type | temp_fileno | file_no | party_1 | party_2 | land_use | plot_no | tp_no | regNo | op_serial | created_at |";
    $md[] = "|---|---|---|---|---|---|---|---|---|---|---|---|";
    foreach ($tr['rows'] as $r) {
        $fileNo = $r->mlsFNo ?: $r->fileno ?: '';
        $p1     = $r->party_1 ?: $r->Grantor ?: '';
        $p2     = $r->party_2 ?: $r->Grantee ?: '';
        $md[] = "| {$r->id} | " . ($r->instrument_type ?: ($r->transaction_type ?: '-'))
              . " | {$r->temp_fileno} | {$fileNo} | {$p1} | {$p2} | {$r->land_use} | {$r->plot_no} | {$r->tp_no} | {$r->regNo} | {$r->op_serial_number} | {$r->created_at} |";
    }
    $md[] = "";

    // Diagnose the OP vs ToT mismatch on this target's prop_id specifically
    $ops  = $tr['rows']->filter(fn ($r) => stripos((string) $r->instrument_type, 'Occupancy Permit') !== false
                                        || stripos((string) $r->transaction_type, 'Occupancy Permit') !== false)->values();
    $tots = $tr['rows']->filter(fn ($r) => stripos((string) $r->instrument_type, 'Transfer of Title') !== false
                                        || stripos((string) $r->transaction_type, 'Transfer of Title') !== false)->values();
    if ($ops->count() && $tots->count()) {
        $diffs = $compareRows($ops->last(), $tots->last());
        if ($diffs) {
            $md[] = "**Mismatched fields (latest OP vs latest ToT):**";
            $md[] = "";
            $md[] = "| Field | OP value | ToT value |";
            $md[] = "|---|---|---|";
            foreach ($diffs as $field => $vals) {
                $md[] = "| `{$field}` | {$vals['op']} | {$vals['tot']} |";
            }
            $md[] = "";
        }
    }
}

$md[] = "## 2. All OP↔ToT Pairs With Field Mismatches";
$md[] = "";
$md[] = "Total mismatch pairs: **" . count($mismatchPairs) . "**";
$md[] = "";
if (count($mismatchPairs)) {
    $md[] = "| prop_id | OP id | ToT id | OP temp / file | ToT temp / file | same temp? | same file? | mismatched fields |";
    $md[] = "|---|---|---|---|---|---|---|---|";
    foreach ($mismatchPairs as $p) {
        $md[] = sprintf(
            "| %s | %s | %s | %s / %s | %s / %s | %s | %s | %s |",
            $p['prop_id'], $p['op_id'], $p['tot_id'],
            $p['op_temp'] ?: '-',  $p['op_fileno']  ?: '-',
            $p['tot_temp'] ?: '-', $p['tot_fileno'] ?: '-',
            $p['same_temp']   ? 'YES' : 'no',
            $p['same_fileno'] ? 'YES' : 'no',
            implode(', ', array_keys($p['mismatches']))
        );
    }
    $md[] = "";
}

$md[] = "## 3. Duplicate-prop_id OP Sibling Groups";
$md[] = "";
$md[] = "Distinct `prop_id`s with more than one OP row in `pra`: **" . count($dupGroups) . "**";
$md[] = "";
if (count($dupGroups)) {
    $shown = 0;
    foreach ($dupGroups as $pid => $sibs) {
        if ($shown++ >= 25) {
            $md[] = "_… truncated, see CSV below for the full list_";
            break;
        }
        $md[] = "### prop_id `{$pid}` ({$sibs->count()} OP rows)";
        $md[] = "";
        $md[] = "| pra.id | temp_fileno | file_no | party_1 | party_2 | land_use | plot_no | tp_no | regNo | created_at |";
        $md[] = "|---|---|---|---|---|---|---|---|---|---|";
        foreach ($sibs as $r) {
            $fileNo = $r->mlsFNo ?: $r->fileno ?: '';
            $p1 = $r->party_1 ?: $r->Grantor ?: '';
            $p2 = $r->party_2 ?: $r->Grantee ?: '';
            $md[] = "| {$r->id} | {$r->temp_fileno} | {$fileNo} | {$p1} | {$p2} | {$r->land_use} | {$r->plot_no} | {$r->tp_no} | {$r->regNo} | {$r->created_at} |";
        }
        $md[] = "";
    }
}

$md[] = "## 4. Root Cause";
$md[] = "";
$md[] = "All mismatch pairs share the same diagnostic shape:";
$md[] = "";
$md[] = "1. The **OP** PRA row and the **ToT** PRA row carry the **same `prop_id`** (and frequently the same `temp_fileno`), even though they describe physically different properties (different `plot_no`, `land_use`, `party_2`, `tp_no`, etc.).";
$md[] = "2. The OSS *FileNo Commissioning* flow allocated a `prop_id` against an **already-existing OP record**'s `temp_fileno` instead of creating/keeping a unique allocation per OP. This is the duplicate-`prop_id` pathology that has been logged repeatedly in `pra-write-audit.md`.";
$md[] = "3. When the user later commissioned a Transfer of Title, the ToT linker (`MlsFileNoController` / `InstrumentCaptureService`) joined to PRA by `prop_id` only, picked the first matching OP, and inherited that OP's `temp_fileno` + `prop_id` for the ToT row, even though the ToT was actually issued for a *different* OP.";
$md[] = "4. Result: the page shows an OP card and a ToT card under the same prop_id with completely unrelated parties, plot numbers and land uses (the screenshots).";
$md[] = "";
$md[] = "The duplicate-prop_id resolver added in `InstrumentController::resolveOpDuplicates` prevents *new* commissions from inheriting a sibling's prop_id, but rows already written before that fix are still present and need a one-time corrective scan + relink.";
$md[] = "";
$md[] = "## 5. Proposed One-Time Code Fix";
$md[] = "";
$md[] = "Add an artisan command `oss:print-op-tot-mismatch` (read-only, prints all mismatched OP→ToT pairs to stdout / a CSV file in `storage/logs/`) so operations can audit them before any data correction. Pseudocode:";
$md[] = "";
$md[] = "```php";
$md[] = "// app/Console/Commands/PrintOpTotMismatch.php";
$md[] = "namespace App\\Console\\Commands;";
$md[] = "";
$md[] = "use Illuminate\\Console\\Command;";
$md[] = "use Illuminate\\Support\\Facades\\DB;";
$md[] = "";
$md[] = "class PrintOpTotMismatch extends Command";
$md[] = "{";
$md[] = "    protected \$signature = 'oss:print-op-tot-mismatch {--csv : Also write a CSV in storage/logs/}';";
$md[] = "    protected \$description = 'List all OSS OP→ToT pairs that share prop_id but have mismatched property facts.';";
$md[] = "";
$md[] = "    public function handle(): int";
$md[] = "    {";
$md[] = "        \$rows = DB::connection('sqlsrv')->table('pra')";
$md[] = "            ->where('system_source', 'OSSOPCHANGEOFNAME')";
$md[] = "            ->whereNotNull('prop_id')->where('prop_id', '!=', '')";
$md[] = "            ->orderBy('prop_id')->orderBy('id')->get();";
$md[] = "";
$md[] = "        \$byProp = \$rows->groupBy('prop_id');";
$md[] = "        \$pairs = [];";
$md[] = "        foreach (\$byProp as \$pid => \$group) {";
$md[] = "            \$op  = \$group->first(fn(\$r) => stripos(\$r->instrument_type, 'Occupancy Permit') !== false);";
$md[] = "            \$tot = \$group->first(fn(\$r) => stripos(\$r->instrument_type, 'Transfer of Title') !== false);";
$md[] = "            if (!\$op || !\$tot) continue;";
$md[] = "            \$diff = collect(['party_1','party_2','land_use','plot_no','tp_no'])";
$md[] = "                ->filter(fn(\$f) => strtoupper(trim(\$op->\$f ?? '')) !== ''";
$md[] = "                                 && strtoupper(trim(\$tot->\$f ?? '')) !== ''";
$md[] = "                                 && strtoupper(trim(\$op->\$f)) !== strtoupper(trim(\$tot->\$f)));";
$md[] = "            if (\$diff->isEmpty()) continue;";
$md[] = "            \$pairs[] = compact('pid','op','tot','diff');";
$md[] = "        }";
$md[] = "";
$md[] = "        \$this->table(['prop_id','OP id','ToT id','OP file','ToT file','mismatched fields'],";
$md[] = "            collect(\$pairs)->map(fn(\$p) => [";
$md[] = "                \$p['pid'], \$p['op']->id, \$p['tot']->id,";
$md[] = "                \$p['op']->mlsFNo ?: \$p['op']->fileno,";
$md[] = "                \$p['tot']->mlsFNo ?: \$p['tot']->fileno,";
$md[] = "                \$p['diff']->implode(','),";
$md[] = "            ])->all());";
$md[] = "";
$md[] = "        if (\$this->option('csv')) {";
$md[] = "            \$path = storage_path('logs/op-tot-mismatch-' . date('Ymd-His') . '.csv');";
$md[] = "            \$fp = fopen(\$path, 'w');";
$md[] = "            fputcsv(\$fp, ['prop_id','op_id','tot_id','op_file','tot_file','mismatched_fields','op_party_2','tot_party_2','op_plot_no','tot_plot_no','op_land_use','tot_land_use']);";
$md[] = "            foreach (\$pairs as \$p) {";
$md[] = "                fputcsv(\$fp, [";
$md[] = "                    \$p['pid'], \$p['op']->id, \$p['tot']->id,";
$md[] = "                    \$p['op']->mlsFNo ?: \$p['op']->fileno,";
$md[] = "                    \$p['tot']->mlsFNo ?: \$p['tot']->fileno,";
$md[] = "                    \$p['diff']->implode(','),";
$md[] = "                    \$p['op']->party_2,  \$p['tot']->party_2,";
$md[] = "                    \$p['op']->plot_no,  \$p['tot']->plot_no,";
$md[] = "                    \$p['op']->land_use, \$p['tot']->land_use,";
$md[] = "                ]);";
$md[] = "            }";
$md[] = "            fclose(\$fp);";
$md[] = "            \$this->info(\"CSV written: {\$path}\");";
$md[] = "        }";
$md[] = "        \$this->info('Total mismatch pairs: ' . count(\$pairs));";
$md[] = "        return self::SUCCESS;";
$md[] = "    }";
$md[] = "}";
$md[] = "```";
$md[] = "";
$md[] = "Then register it in `app/Console/Kernel.php` and run:";
$md[] = "";
$md[] = "```bash";
$md[] = "php artisan oss:print-op-tot-mismatch --csv";
$md[] = "```";
$md[] = "";
$md[] = "This **prints only** — no data is modified. Operations reviews the CSV, then a follow-up `oss:relink-op-tot` command (separate ticket) reassigns the affected ToT rows to their correct OP via `PropertyIdAllocationService::allocateOrRetrievePropId()` with `allow_temp_only=true, skip_lookup=true` (the same path the duplicate-resolver already uses for siblings).";
$md[] = "";

file_put_contents($reportPath, implode("\n", $md));

echo "\nReport written: {$reportPath}\n";
echo "Mismatch pairs: " . count($mismatchPairs) . "\n";
echo "Duplicate-prop_id OP groups: " . count($dupGroups) . "\n";
