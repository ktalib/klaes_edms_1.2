<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check file_indexings columns relevant to file title
$cols = DB::connection('sqlsrv')->select("
    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'file_indexings'
    ORDER BY ORDINAL_POSITION
");
echo "=== file_indexings columns ===\n";
foreach ($cols as $c) echo $c->COLUMN_NAME . "\n";

// Check a sample record to see what file_title looks like
echo "\n=== Sample file_indexings with file title (RES-1982-904) ===\n";
$sample = DB::connection('sqlsrv')->select("
    SELECT TOP 5 id, file_number, file_title, file_name, party_1, party_2
    FROM file_indexings
    WHERE file_number LIKE '%RES-1982-904%'
");
foreach ($sample as $s) {
    echo "id={$s->id} file_number={$s->file_number} file_title=" . ($s->file_title ?? 'NULL') . " file_name=" . ($s->file_name ?? 'NULL') . " party_1=" . ($s->party_1 ?? 'NULL') . " party_2=" . ($s->party_2 ?? 'NULL') . "\n";
}

// Check if file_title column exists
$hasTitle = DB::connection('sqlsrv')->select("
    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME IN ('file_title', 'title', 'file_name', 'party_1', 'party_2')
");
echo "\n=== Title-related columns ===\n";
foreach ($hasTitle as $c) echo $c->COLUMN_NAME . "\n";
