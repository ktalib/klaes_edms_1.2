<?php
/**
 * Preview: PIC → CofO_staging migration
 * DRY RUN — no data is modified. Shows what would happen.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$conn = DB::connection('sqlsrv');

echo "============================================================\n";
echo "  PIC → CofO_staging Migration Preview (DRY RUN)\n";
echo "============================================================\n\n";

// --- Current counts ---
$picCount = $conn->selectOne("SELECT COUNT(*) AS cnt FROM pic")->cnt;
$cofoCount = $conn->selectOne("SELECT COUNT(*) AS cnt FROM CofO_staging")->cnt;
echo "Current row counts:\n";
echo "  pic:           {$picCount}\n";
echo "  CofO_staging:  {$cofoCount}\n\n";

// --- Check which columns need to be added ---
$cofoColumns = collect($conn->select("
    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'CofO_staging'
"))->pluck('COLUMN_NAME')->map(fn($c) => strtolower($c))->toArray();

$newColumns = [
    'streetName'          => 'nvarchar(255)',
    'house_no'            => 'nvarchar(100)',
    'districtName'        => 'nvarchar(255)',
    'tp_no'               => 'nvarchar(100)',
    'lpkn_no'             => 'nvarchar(100)',
    'approved_plan_no'    => 'nvarchar(100)',
    'plot_size'           => 'nvarchar(100)',
    'date_recommended'    => 'nvarchar(50)',
    'date_approved'       => 'nvarchar(50)',
    'lease_begins'        => 'nvarchar(50)',
    'lease_expires'       => 'nvarchar(50)',
    'metric_sheet'        => 'nvarchar(255)',
    'caveat_id'           => 'nvarchar(100)',
    'source'              => 'nvarchar(255)',
    'related_file_number' => 'nvarchar(100)',
    'deeds_date'          => 'nvarchar(100)',
    'deeds_time'          => 'nvarchar(100)',
    'party_1'             => 'nvarchar(50)',
    'party_2'             => 'nvarchar(50)',
    'party_3'             => 'nvarchar(50)',
    'Donor'               => 'nvarchar(50)',
    'Donee'               => 'nvarchar(50)',
    'party_4'             => 'nvarchar(500)',
    'Mortgagor_2'         => 'nvarchar(500)',
    'Surrenderor_2'       => 'nvarchar(500)',
    'Releasor'            => 'nvarchar(500)',
    'Releasee'            => 'nvarchar(500)',
    'Vendor'              => 'nvarchar(500)',
    'Purchaser'           => 'nvarchar(500)',
    'is_mapped'           => 'bit',
];

$missing = [];
$alreadyExist = [];
foreach ($newColumns as $col => $type) {
    if (in_array(strtolower($col), $cofoColumns)) {
        $alreadyExist[] = $col;
    } else {
        $missing[$col] = $type;
    }
}

echo "Columns to ADD to CofO_staging: " . count($missing) . "\n";
foreach ($missing as $col => $type) {
    echo "  + {$col} ({$type})\n";
}
if (!empty($alreadyExist)) {
    echo "\nColumns already exist (will skip): " . count($alreadyExist) . "\n";
    foreach ($alreadyExist as $col) {
        echo "  ✓ {$col}\n";
    }
}

// --- Duplicate detection preview ---
echo "\n--- Duplicate detection ---\n";
$dupeCount = $conn->selectOne("
    SELECT COUNT(*) AS cnt
    FROM pic p
    WHERE EXISTS (
        SELECT 1 FROM CofO_staging c
        WHERE ISNULL(c.mlsFNo, '') = ISNULL(p.mlsFNo, '')
          AND ISNULL(c.serialNo, '') = ISNULL(p.serialNo, '')
          AND ISNULL(c.pageNo, '') = ISNULL(p.pageNo, '')
          AND ISNULL(c.volumeNo, '') = ISNULL(p.volumeNo, '')
          AND ISNULL(c.fileno, '') = ISNULL(p.fileno, '')
    )
")->cnt;

$toInsert = $picCount - $dupeCount;
echo "  PIC records that match existing CofO_staging (duplicates): {$dupeCount}\n";
echo "  PIC records to INSERT (new):                               {$toInsert}\n";
echo "  Expected CofO_staging count after migration:               " . ($cofoCount + $toInsert) . "\n";

// --- Sample of records to be inserted ---
echo "\n--- Sample PIC records that would be inserted (first 10) ---\n";
$samples = $conn->select("
    SELECT TOP 10 p.id, p.mlsFNo, p.fileno, p.serialNo, p.pageNo, p.volumeNo,
           p.transaction_type, p.Grantor, p.Grantee, p.prop_id
    FROM pic p
    WHERE NOT EXISTS (
        SELECT 1 FROM CofO_staging c
        WHERE ISNULL(c.mlsFNo, '') = ISNULL(p.mlsFNo, '')
          AND ISNULL(c.serialNo, '') = ISNULL(p.serialNo, '')
          AND ISNULL(c.pageNo, '') = ISNULL(p.pageNo, '')
          AND ISNULL(c.volumeNo, '') = ISNULL(p.volumeNo, '')
          AND ISNULL(c.fileno, '') = ISNULL(p.fileno, '')
    )
    ORDER BY p.id
");

if (empty($samples)) {
    echo "  (no new records to insert)\n";
} else {
    echo str_pad("ID", 8) . str_pad("mlsFNo", 22) . str_pad("fileno", 22)
       . str_pad("S/P/V", 16) . str_pad("Type", 24) . str_pad("prop_id", 14) . "\n";
    echo str_repeat("-", 106) . "\n";
    foreach ($samples as $s) {
        $spv = ($s->serialNo ?: '-') . '/' . ($s->pageNo ?: '-') . '/' . ($s->volumeNo ?: '-');
        echo str_pad($s->id, 8)
           . str_pad($s->mlsFNo ?: '-', 22)
           . str_pad($s->fileno ?: '-', 22)
           . str_pad($spv, 16)
           . str_pad(substr($s->transaction_type ?: '-', 0, 22), 24)
           . str_pad($s->prop_id ?: '-', 14)
           . "\n";
    }
}

// --- Sample duplicates ---
echo "\n--- Sample duplicates that would be SKIPPED (first 5) ---\n";
$dupes = $conn->select("
    SELECT TOP 5 p.id, p.mlsFNo, p.fileno, p.serialNo, p.pageNo, p.volumeNo
    FROM pic p
    WHERE EXISTS (
        SELECT 1 FROM CofO_staging c
        WHERE ISNULL(c.mlsFNo, '') = ISNULL(p.mlsFNo, '')
          AND ISNULL(c.serialNo, '') = ISNULL(p.serialNo, '')
          AND ISNULL(c.pageNo, '') = ISNULL(p.pageNo, '')
          AND ISNULL(c.volumeNo, '') = ISNULL(p.volumeNo, '')
          AND ISNULL(c.fileno, '') = ISNULL(p.fileno, '')
    )
    ORDER BY p.id
");

if (empty($dupes)) {
    echo "  (no duplicates found)\n";
} else {
    echo str_pad("PIC ID", 10) . str_pad("mlsFNo", 22) . str_pad("fileno", 22) . str_pad("S/P/V", 16) . "\n";
    echo str_repeat("-", 70) . "\n";
    foreach ($dupes as $d) {
        $spv = ($d->serialNo ?: '-') . '/' . ($d->pageNo ?: '-') . '/' . ($d->volumeNo ?: '-');
        echo str_pad($d->id, 10) . str_pad($d->mlsFNo ?: '-', 22) . str_pad($d->fileno ?: '-', 22) . str_pad($spv, 16) . "\n";
    }
}

echo "\n============================================================\n";
echo "  DRY RUN COMPLETE — No data was modified.\n";
echo "  To proceed, run: php migrate_pic_to_cofo.php\n";
echo "============================================================\n";
