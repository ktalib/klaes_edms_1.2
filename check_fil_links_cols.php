<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cols = DB::connection('sqlsrv')->select(
    "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = N'file_indexing_links' ORDER BY ORDINAL_POSITION"
);
echo "file_indexing_links columns:\n";
foreach ($cols as $c) {
    echo "  {$c->COLUMN_NAME} ({$c->DATA_TYPE})\n";
}
