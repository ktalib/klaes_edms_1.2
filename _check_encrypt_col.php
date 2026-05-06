<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$cols = DB::connection('sqlsrv')->select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'grouping' AND COLUMN_NAME LIKE '%encrypt%'");
echo "Columns with 'encrypt' in grouping table:\n";
foreach ($cols as $c) {
    echo "  - " . $c->COLUMN_NAME . "\n";
}
echo "Count: " . count($cols) . "\n\n";

// Also check a sample row
$sample = DB::connection('sqlsrv')->select("SELECT TOP 1 * FROM [klas].[dbo].[grouping] WHERE awaiting_fileno IS NOT NULL");
if ($sample) {
    $keys = array_keys((array)$sample[0]);
    echo "All columns in grouping table:\n";
    foreach ($keys as $k) {
        echo "  - $k\n";
    }
}
