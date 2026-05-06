<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = DB::connection('sqlsrv');

echo "=== KNML count in file_indexings.awaiting_file_no ===\n";
echo $db->table('file_indexings')->where('awaiting_file_no', 'like', 'KNML%')->count() . "\n";

echo "\n=== Sample awaiting_file_no values ===\n";
$rows = $db->table('file_indexings')->whereNotNull('awaiting_file_no')->where('awaiting_file_no', '!=', '')->limit(3)->get(['awaiting_file_no', 'file_number']);
foreach ($rows as $r) { print_r((array)$r); }

echo "\n=== tracking_id match (TRK-FD42EE3-00CA5) count ===\n";
echo $db->table('file_indexings')->where('tracking_id', 'TRK-FD42EE3-00CA5')->count() . "\n";

echo "\n=== Direct kangis_grouping join file_indexings via tracking_id (top 3) ===\n";
$rows = $db->table('kangis_grouping as g')
    ->join('file_indexings as fi', 'fi.tracking_id', '=', 'g.tracking_id')
    ->where('g.kangis_awaiting_fileno', 'like', 'KNML%')
    ->limit(3)
    ->get(['g.kangis_awaiting_fileno', 'fi.id', 'fi.file_number', 'fi.tracking_id']);
foreach ($rows as $r) { print_r((array)$r); }
if ($rows->isEmpty()) echo "(no matches via tracking_id join)\n";


// 1. See what file_number looks like for KNML in file_indexings
echo "=== file_indexings with KNML (top 5) ===\n";
$rows = $db->table('file_indexings')->where('file_number', 'like', 'KNML%')->limit(5)->get(['id','file_number','batch_no']);
foreach ($rows as $r) { print_r((array)$r); }

echo "\n=== Total KNML in file_indexings ===\n";
echo $db->table('file_indexings')->where('file_number', 'like', 'KNML%')->count() . "\n";

// 2. See all columns in file_indexings that might hold the file number
echo "\n=== Columns in file_indexings ===\n";
$cols = $db->select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='file_indexings' ORDER BY ORDINAL_POSITION");
foreach ($cols as $c) echo "  " . $c->COLUMN_NAME . "\n";

// 3. Check if kangis_grouping.tracking_id matches file_indexings.tracking_id
echo "\n=== file_indexings matching kangis_grouping tracking_id (top 5) ===\n";
$tids = $db->table('kangis_grouping')->where('kangis_awaiting_fileno','like','KNML%')->where('sys_batch_no',1)->limit(5)->pluck('tracking_id');
echo "tracking_ids from kangis_grouping: " . implode(', ', $tids->all()) . "\n";
$fi = $db->table('file_indexings')->whereIn('tracking_id', $tids->all())->limit(5)->get(['id','file_number','tracking_id']);
foreach ($fi as $r) { print_r((array)$r); }

// 4. Sample a file_indexings row to see what's there
echo "\n=== Sample file_indexings row ===\n";
$row = $db->table('file_indexings')->limit(1)->first();
print_r((array)$row);
s