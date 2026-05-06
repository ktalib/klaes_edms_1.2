<?php
/**
 * Diagnostic: prop_id 69576339 / file RES-2023-969
 *
 * Goal: explain why the OP details modal shows 1 OP + 4 TOT rows that all
 * carry the same File No (RES-2023-969). Inspect:
 *   - instrument_capture rows for this file / prop
 *   - pra rows for this file / prop
 *   - mlsf_file_numbers row(s) for the file
 *   - any deed_registrations / pic linkage
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$fileNo = 'RES-2023-969';
$propId = '69576339';

function header_line(string $title): void
{
    echo "\n" . str_repeat('=', 80) . "\n";
    echo strtoupper($title) . "\n";
    echo str_repeat('=', 80) . "\n";
}

function dump_rows(string $title, $rows): void
{
    header_line($title);
    if (empty($rows)) { echo "(no rows)\n"; return; }
    echo "Count: " . count($rows) . "\n\n";
    foreach ($rows as $i => $r) {
        echo "--- Row " . ($i + 1) . " ---\n";
        foreach ((array) $r as $k => $v) {
            if (is_string($v) && strlen($v) > 200) { $v = substr($v, 0, 200) . '…'; }
            echo sprintf("  %-30s : %s\n", $k, is_null($v) ? 'NULL' : $v);
        }
        echo "\n";
    }
}

try {
    /* 1a. fileNumber (legacy) */
    $fn = DB::connection('sqlsrv')->select(
        "SELECT * FROM fileNumber
         WHERE CAST(mlsfNo AS nvarchar(100)) = ?",
        [$fileNo]
    );
    dump_rows("1a. fileNumber (by mlsfNo)", $fn);

    /* 1b. mls_file_no */
    $mfn = DB::connection('sqlsrv')->select(
        "SELECT * FROM mls_file_no
         WHERE CAST(full_file_number AS nvarchar(100)) = ?",
        [$fileNo]
    );
    dump_rows("1b. mls_file_no (by full_file_number)", $mfn);

    /* 2. instrument_capture */
    $ic = DB::connection('sqlsrv')->select(
        "SELECT * FROM instrument_capture
         WHERE CAST(mlsFNo AS nvarchar(100)) = ?
            OR CAST(prop_id AS nvarchar(50)) = ?
         ORDER BY id ASC",
        [$fileNo, $propId]
    );
    /* Reduce IC dump to key fields only */
    $icSlim = array_map(function($r) {
        $obj = new stdClass();
        foreach (['id','mlsFNo','temp_fileno','prop_id','instrument_type','party_1_name','party_2_name','PlotNo','plot_no','LandUse','land_use','deed_date','RegNo','registration_no','op_serial_no','op_no','TPNo','tp_no','is_deleted','created_at','created_by','updated_at','updated_by','sub_application_id','application_id'] as $k) {
            if (property_exists($r, $k)) { $obj->$k = $r->$k; }
        }
        return $obj;
    }, $ic);
    dump_rows("2. instrument_capture (key fields)", $icSlim);

    /* 3. pra */
    $pra = DB::connection('sqlsrv')->select(
        "SELECT * FROM pra
         WHERE CAST(prop_id AS nvarchar(50)) = ?
            OR CAST(mlsFNo AS nvarchar(100)) = ?
            OR CAST(fileno AS nvarchar(100)) = ?
         ORDER BY id ASC",
        [$propId, $fileNo, $fileNo]
    );
    $praSlim = array_map(function($r) {
        $obj = new stdClass();
        foreach (['id','file_no','fileno','mlsFNo','prop_id','instrument_type','transaction_type','party_1','party_2','Grantor','Grantee','PlotNo','plot_no','LandUse','land_use','system_source','instrument_capture_id','temp_fileno','created_at','created_by','is_deleted'] as $k) {
            if (property_exists($r, $k)) { $obj->$k = $r->$k; }
        }
        return $obj;
    }, $pra);
    dump_rows("3. pra (key fields)", $praSlim);

    /* 4. deed_registrations linked via instrument_capture ids */
    if (!empty($ic)) {
        $icIds = array_map(fn($r) => $r->id, $ic);
        $in    = implode(',', array_map('intval', $icIds));
        $dr = DB::connection('sqlsrv')->select(
            "SELECT id, instrument_capture_id, registration_no, page, volume,
                    instrument_type, created_at
             FROM deed_registrations
             WHERE instrument_capture_id IN ($in)
             ORDER BY id ASC"
        );
        dump_rows("4. deed_registrations linked to IC rows", $dr);
    }

    /* 5. Look for duplicate detection on prop_id / file_no across systems */
    header_line("5. SUMMARY DIAGNOSIS");

    $icCount  = count($ic);
    $praCount = count($pra);
    $fnCount  = count($fn);

    $opCount  = 0; $totCount = 0;
    foreach ($ic as $r) {
        $t = strtolower((string) $r->instrument_type);
        if (str_contains($t, 'transfer') || str_contains($t, 'tot')) { $totCount++; }
        elseif (str_contains($t, 'occupancy') || str_contains($t, 'op')) { $opCount++; }
    }

    echo "instrument_capture rows : $icCount  (OP-ish: $opCount, ToT-ish: $totCount)\n";
    echo "pra rows                : $praCount\n";
    echo "mlsf_file_numbers rows  : $fnCount\n\n";

    /* duplicate temp_filenos */
    $temps = [];
    foreach ($ic as $r) { if (!empty($r->temp_fileno)) { $temps[$r->temp_fileno] = ($temps[$r->temp_fileno] ?? 0) + 1; } }
    foreach ($temps as $t => $c) {
        if ($c > 1) { echo "DUP temp_fileno: $t  ×$c IC rows\n"; }
    }

    /* duplicate prop_id rows */
    $props = [];
    foreach ($ic as $r) { if (!empty($r->prop_id)) { $props[$r->prop_id] = ($props[$r->prop_id] ?? 0) + 1; } }
    foreach ($props as $p => $c) {
        if ($c > 1) { echo "DUP prop_id in IC: $p  ×$c\n"; }
    }

    /* every IC row: same parties? */
    if ($icCount > 1) {
        $sigs = [];
        foreach ($ic as $r) {
            $sig = trim(($r->party_1_name ?? '') . '|' . ($r->party_2_name ?? '') . '|' . ($r->plot_no ?? ''));
            $sigs[$sig] = ($sigs[$sig] ?? 0) + 1;
        }
        foreach ($sigs as $sig => $c) {
            if ($c > 1) { echo "Identical IC signature x$c -> $sig\n"; }
        }
    }

    echo "\nDone.\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
