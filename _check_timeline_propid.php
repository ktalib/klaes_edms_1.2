<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$db = DB::connection('sqlsrv');

$file = 'IND-1983-3';

echo "=== PRA ===\n";
$rows = $db->select("SELECT id, fileno, mlsFNo, CAST(prop_id AS NVARCHAR(50)) as prop_id FROM pra WHERE UPPER(fileno) = ? OR UPPER(mlsFNo) = ?", [strtoupper($file), strtoupper($file)]);
foreach ($rows as $r) echo "  id={$r->id} fileno={$r->fileno} mls={$r->mlsFNo} prop_id={$r->prop_id}\n";

echo "\n=== file_history_staging ===\n";
$rows = $db->select("SELECT id, fileno, mlsFNo, CAST(prop_id AS NVARCHAR(50)) as prop_id FROM file_history_staging WHERE UPPER(fileno) = ? OR UPPER(mlsFNo) = ?", [strtoupper($file), strtoupper($file)]);
foreach ($rows as $r) echo "  id={$r->id} fileno={$r->fileno} mls={$r->mlsFNo} prop_id={$r->prop_id}\n";

echo "\n=== CofO_staging ===\n";
$rows = $db->select("SELECT id, fileno, mlsFNo, CAST(prop_id AS NVARCHAR(50)) as prop_id FROM CofO_staging WHERE UPPER(fileno) = ? OR UPPER(mlsFNo) = ?", [strtoupper($file), strtoupper($file)]);
foreach ($rows as $r) echo "  id={$r->id} fileno={$r->fileno} mls={$r->mlsFNo} prop_id={$r->prop_id}\n";

echo "\n=== deed_registrations ===\n";
$rows = $db->select("SELECT id, fileno, CAST(prop_id AS NVARCHAR(50)) as prop_id FROM deed_registrations WHERE UPPER(fileno) = ?", [strtoupper($file)]);
foreach ($rows as $r) echo "  id={$r->id} fileno={$r->fileno} prop_id={$r->prop_id}\n";

// Now check what the correlated subquery would return for prop_id 14781
echo "\n=== Correlated subquery check for prop_id=14781 ===\n";
$propId = '14781';
$fh = $db->selectOne("SELECT COUNT(*) as cnt FROM file_history_staging WHERE CAST(prop_id AS NVARCHAR(50)) = ?", [$propId]);
$pra = $db->selectOne("SELECT COUNT(*) as cnt FROM pra WHERE CAST(prop_id AS NVARCHAR(50)) = ? AND (is_deleted IS NULL OR is_deleted = 0)", [$propId]);
$dr = $db->selectOne("SELECT COUNT(*) as cnt FROM deed_registrations WHERE CAST(prop_id AS NVARCHAR(50)) = ?", [$propId]);
$cofo = $db->selectOne("SELECT COUNT(*) as cnt FROM CofO_staging WHERE CAST(prop_id AS NVARCHAR(50)) = ?", [$propId]);
echo "  file_history_staging: {$fh->cnt}\n";
echo "  pra: {$pra->cnt}\n";
echo "  deed_registrations: {$dr->cnt}\n";
echo "  CofO_staging: {$cofo->cnt}\n";
echo "  TOTAL: " . ($fh->cnt + $pra->cnt + $dr->cnt + $cofo->cnt) . "\n";
