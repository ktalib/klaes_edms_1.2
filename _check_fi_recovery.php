<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$pdo = DB::connection('sqlsrv')->getPdo();

echo "=== grouping table columns ===" . PHP_EOL;
$stmt = $pdo->query("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'grouping' ORDER BY ORDINAL_POSITION");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) echo $c['COLUMN_NAME'] . ' (' . $c['DATA_TYPE'] . ')' . PHP_EOL;

echo PHP_EOL . "=== Sample grouping rows ===" . PHP_EOL;
$stmt2 = $pdo->query("SELECT TOP 3 * FROM [klas].[dbo].[grouping]");
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $row) {
    foreach ($row as $k => $v) echo $k . ': ' . $v . PHP_EOL;
    echo "---" . PHP_EOL;
}

echo PHP_EOL . "=== registries table columns ===" . PHP_EOL;
$stmt3 = $pdo->query("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'registries' ORDER BY ORDINAL_POSITION");
foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) as $c) echo $c['COLUMN_NAME'] . ' (' . $c['DATA_TYPE'] . ')' . PHP_EOL;

echo PHP_EOL . "=== All registries rows ===" . PHP_EOL;
$stmt4 = $pdo->query("SELECT * FROM [klas].[dbo].[registries]");
foreach ($stmt4->fetchAll(PDO::FETCH_ASSOC) as $row) {
    foreach ($row as $k => $v) echo $k . ': ' . $v . PHP_EOL;
    echo "---" . PHP_EOL;
}
$stmt = $pdo->query("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'fileNumber' ORDER BY ORDINAL_POSITION");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) echo $c['COLUMN_NAME'] . ' (' . $c['DATA_TYPE'] . ')' . PHP_EOL;

echo PHP_EOL . "=== Sample fileNumber row (source=indexing) ===" . PHP_EOL;
$stmt2 = $pdo->query("SELECT TOP 1 * FROM [klas].[dbo].[fileNumber] WHERE SOURCE = 'indexing'");
$row = $stmt2->fetch(PDO::FETCH_ASSOC);
if ($row) {
    foreach ($row as $k => $v) echo $k . ': ' . $v . PHP_EOL;
} else {
    echo "(no indexing rows)" . PHP_EOL;
    $stmt2b = $pdo->query("SELECT TOP 1 * FROM [klas].[dbo].[fileNumber]");
    $row = $stmt2b->fetch(PDO::FETCH_ASSOC);
    foreach ($row as $k => $v) echo $k . ': ' . $v . PHP_EOL;
}

echo PHP_EOL . "=== Count of fileNumber records updated on 2026-04-28 (tracking_id set) ===" . PHP_EOL;
$stmt3 = $pdo->query("SELECT COUNT(*) AS cnt FROM [klas].[dbo].[fileNumber] WHERE CAST(updated_at AS DATE) = '2026-04-28' AND tracking_id IS NOT NULL AND tracking_id != ''");
$r = $stmt3->fetch(PDO::FETCH_ASSOC);
echo "Updated on 2026-04-28 with tracking_id: " . $r['cnt'] . PHP_EOL;

$stmt4 = $pdo->query("SELECT COUNT(*) AS cnt FROM [klas].[dbo].[fileNumber] WHERE CAST(created_at AS DATE) = '2026-04-28'");
$r2 = $stmt4->fetch(PDO::FETCH_ASSOC);
echo "Created on 2026-04-28: " . $r2['cnt'] . PHP_EOL;
