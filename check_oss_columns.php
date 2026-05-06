<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
 $cols = DB::connection('sqlsrv')->select(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'oss_applications' AND (COLUMN_NAME LIKE 'res_%' OR COLUMN_NAME LIKE 'com_%' OR COLUMN_NAME LIKE 'ind_%') ORDER BY COLUMN_NAME"
);

echo "Address builder columns in oss_applications:\n";
foreach ($cols as $c) {
    echo "  - " . $c->COLUMN_NAME . "\n";
}
echo "Total: " . count($cols) . "\n";
