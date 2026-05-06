<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$sample = DB::connection('sqlsrv')->select("
    SELECT TOP 3 id, file_number, file_title, current_holder, original_holder
    FROM file_indexings
    WHERE file_number LIKE '%RES-1982-904%'
");

echo "=== file_indexings for RES-1982-904 ===\n";
if (empty($sample)) {
    echo "No records found for RES-1982-904\n";
} else {
    foreach ($sample as $s) {
        echo "id={$s->id}\n";
        echo "  file_number={$s->file_number}\n";
        echo "  file_title=" . ($s->file_title ?? 'NULL') . "\n";
        echo "  current_holder=" . ($s->current_holder ?? 'NULL') . "\n";
        echo "  original_holder=" . ($s->original_holder ?? 'NULL') . "\n\n";
    }
}

// Try a few more to see file_title pattern
$more = DB::connection('sqlsrv')->select("
    SELECT TOP 5 file_number, file_title, current_holder, original_holder
    FROM file_indexings
    WHERE file_title IS NOT NULL AND file_title <> ''
    ORDER BY id DESC
");
echo "=== Recent file_indexings with file_title ===\n";
foreach ($more as $s) {
    echo "file_number={$s->file_number} | file_title={$s->file_title} | current={$s->current_holder} | original={$s->original_holder}\n";
}
