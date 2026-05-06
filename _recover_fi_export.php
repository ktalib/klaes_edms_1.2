<?php
/**
 * Step 1 of recovery: Export deleted file_indexings records to JSON.
 *
 * Source: fileNumber table only (no grouping join).
 * Strategy:
 *   - Find fileNumber rows whose tracking_id is NOT present in file_indexings.
 *   - Optionally filter by date and/or scope to lands files (mlsfNo).
 *
 * Output: storage/app/recovery/deleted_file_indexings.json
 *
 * Run:
 *   php _recover_fi_export.php
 *   php _recover_fi_export.php --date=2026-04-28
 *   php _recover_fi_export.php --include-kangis
 *   php _recover_fi_export.php --debug-cols     (just print fileNumber columns and exit)
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$dateFilter      = null;
$includeNonLands = false;
$debugCols       = false;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--date=')) {
        $dateFilter = substr($arg, 7);
    }
    if ($arg === '--include-kangis') {
        $includeNonLands = true;
    }
    if ($arg === '--debug-cols') {
        $debugCols = true;
    }
}

echo "=== File Indexing Recovery — Export Step ===" . PHP_EOL;
echo "Connection       : " . config('database.connections.sqlsrv.host') . " / " . config('database.connections.sqlsrv.database') . PHP_EOL;

$pdo = DB::connection('sqlsrv')->getPdo();

// Fetch fileNumber columns dynamically (production backup schema may differ)
$colsStmt = $pdo->query("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'fileNumber'
    ORDER BY ORDINAL_POSITION
");
$availableCols = array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');

if ($debugCols) {
    echo PHP_EOL . "fileNumber columns in this database:" . PHP_EOL;
    foreach ($availableCols as $c) echo "  $c" . PHP_EOL;
    exit;
}

// Pick only columns that exist in this database
$wanted = [
    'id', 'tracking_id', 'mlsfNo', 'kangisFileNo', 'NewKANGISFileNo',
    'st_file_no', 'gkn_fileno', 'dciv_fileno',
    'FileName', 'location', 'lga', 'district', 'state', 'plot_no', 'tp_no',
    'temp_fileno', 'temp_file_no', 'has_temp_file',
    'kangis_fileno_placeholder', 'kangis_fileno_resolved',
    'phone_no', 'address', 'rep_phone_no', 'rep_address',
    'type', 'SOURCE', 'created_by', 'updated_by',
    'test_control', 'created_at', 'updated_at',
    'commissioning_date', 'commissioning_time',
    'is_deleted', 'is_decommissioned',
];
$selectCols = array_values(array_intersect($wanted, $availableCols));
$selectList = implode(",\n        ", array_map(fn($c) => "fn.[$c]", $selectCols));

echo "Date filter      : " . ($dateFilter ?: '(none — all orphaned tracking_ids)') . PHP_EOL;
echo "Include non-lands: " . ($includeNonLands ? 'yes' : 'no (lands files only)') . PHP_EOL . PHP_EOL;

$sql = "
    SELECT
        $selectList
    FROM [dbo].[fileNumber] fn
    WHERE
        fn.tracking_id IS NOT NULL
        AND fn.tracking_id != ''
        AND fn.tracking_id LIKE 'TRK-%'
        AND fn.tracking_id NOT IN (
            SELECT fi.tracking_id
            FROM [dbo].[file_indexings] fi
            WHERE fi.tracking_id IS NOT NULL
        )
";

if (!$includeNonLands) {
    $sql .= "
        AND fn.mlsfNo IS NOT NULL
        AND fn.mlsfNo != ''
        AND fn.mlsfNo NOT LIKE 'MLSF/%'
        AND (fn.st_file_no IS NULL OR fn.st_file_no = '')
        AND (fn.kangisFileNo IS NULL OR fn.kangisFileNo = '')
        AND (fn.NewKANGISFileNo IS NULL OR fn.NewKANGISFileNo = '')
    ";
}

if ($dateFilter) {
    $sql .= " AND (CAST(fn.updated_at AS DATE) = :date_filter OR CAST(fn.created_at AS DATE) = :date_filter2) ";
}

$sql .= " ORDER BY fn.updated_at DESC ";

$stmt = $pdo->prepare($sql);
if ($dateFilter) {
    $stmt->bindValue(':date_filter',  $dateFilter);
    $stmt->bindValue(':date_filter2', $dateFilter);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($rows) . " orphaned fileNumber rows (deleted file_indexings)." . PHP_EOL;

// Normalise datetime values + add resolved file_number / file_type
foreach ($rows as &$row) {
    foreach ($row as $k => $v) {
        if ($v instanceof \DateTime || $v instanceof \DateTimeImmutable) {
            $row[$k] = $v->format('Y-m-d H:i:s');
        }
    }
    $row['file_number'] = $row['mlsfNo']
        ?? ($row['NewKANGISFileNo'] ?? null)
        ?? ($row['kangisFileNo'] ?? null)
        ?? ($row['st_file_no'] ?? null)
        ?? ($row['gkn_fileno'] ?? null)
        ?? ($row['dciv_fileno'] ?? null);
    if (empty($row['file_number'])) {
        $row['file_number'] = $row['mlsfNo'] ?: ($row['NewKANGISFileNo'] ?? '') ?: ($row['kangisFileNo'] ?? '') ?: ($row['st_file_no'] ?? '');
    }
    $row['file_type'] = !empty($row['mlsfNo'])               ? 'Lands (MLS)'
        : (!empty($row['NewKANGISFileNo'])                  ? 'New KANGIS'
        : (!empty($row['kangisFileNo'])                     ? 'KANGIS'
        : (!empty($row['st_file_no'])                       ? 'ST'
        : (!empty($row['gkn_fileno'])                       ? 'GKN'
        : (!empty($row['dciv_fileno'])                      ? 'DCIV' : '—')))));
}
unset($row);

// Summary
$typeCounts = [];
foreach ($rows as $r) {
    $t = $r['file_type'] ?? '—';
    $typeCounts[$t] = ($typeCounts[$t] ?? 0) + 1;
}

echo PHP_EOL . "By file type:" . PHP_EOL;
foreach ($typeCounts as $t => $c) echo "  $t : $c" . PHP_EOL;

// Save to JSON
$outDir  = storage_path('app/recovery');
if (!is_dir($outDir)) mkdir($outDir, 0775, true);
$outPath = $outDir . DIRECTORY_SEPARATOR . 'deleted_file_indexings.json';

$payload = [
    'generated_at'      => date('Y-m-d H:i:s'),
    'database'          => config('database.connections.sqlsrv.database'),
    'host'              => config('database.connections.sqlsrv.host'),
    'date_filter'       => $dateFilter,
    'include_non_lands' => $includeNonLands,
    'total_rows'        => count($rows),
    'by_type'           => $typeCounts,
    'rows'              => $rows,
];

file_put_contents($outPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo PHP_EOL . "Exported to: $outPath" . PHP_EOL;
echo "Open the preview at: http://localhost/klaes/_recover_fi_preview.php" . PHP_EOL;
