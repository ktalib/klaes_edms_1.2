<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$db = DB::connection('sqlsrv');

// Check what columns the >2027 records have for transaction_date
echo "=== >2027 Records: created_at vs transaction_date ===\n\n";
$future = $db->select("
    SELECT TOP 28 id, created_at, transaction_date, instrument_type, party_1
    FROM pra
    WHERE created_at IS NOT NULL 
      AND created_at != ''
      AND YEAR(TRY_CAST(created_at AS DATETIME)) > 2027
    ORDER BY id
");
foreach ($future as $f) {
    echo "  ID {$f->id}: created_at=[{$f->created_at}] transaction_date=[{$f->transaction_date}] type=[{$f->instrument_type}]\n";
}

// Check pre-2024 (OP import) records sample - what's their transaction_date?
echo "\n=== Pre-2024 OP Import Records Sample (10) ===\n\n";
$old = $db->select("
    SELECT TOP 10 id, created_at, transaction_date, instrument_type, party_1, source
    FROM pra
    WHERE created_at IS NOT NULL 
      AND created_at != ''
      AND YEAR(TRY_CAST(created_at AS DATETIME)) < 2024
    ORDER BY id
");
foreach ($old as $o) {
    echo "  ID {$o->id}: created_at=[{$o->created_at}] transaction_date=[{$o->transaction_date}] type=[{$o->instrument_type}] source=[{$o->source}]\n";
}

// Check the transaction_date column type
$col = $db->select("SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pra' AND COLUMN_NAME = 'transaction_date'");
echo "\ntransaction_date column type: " . $col[0]->DATA_TYPE . " (max_length: " . ($col[0]->CHARACTER_MAXIMUM_LENGTH ?? 'N/A') . ")\n";

// Count totals for each category to confirm
echo "\n=== Final Counts ===\n";
echo "NULL created_at: " . $db->table('pra')->whereNull('created_at')->count() . "\n";
echo ">2027 rows: " . $db->selectOne("SELECT COUNT(*) as cnt FROM pra WHERE created_at IS NOT NULL AND created_at != '' AND YEAR(TRY_CAST(created_at AS DATETIME)) > 2027")->cnt . "\n";
echo "<2024 rows: " . $db->selectOne("SELECT COUNT(*) as cnt FROM pra WHERE created_at IS NOT NULL AND created_at != '' AND YEAR(TRY_CAST(created_at AS DATETIME)) < 2024")->cnt . "\n";
