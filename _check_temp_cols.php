<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Check file_indexings columns
$cols1 = DB::connection('sqlsrv')->select("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME IN ('has_temp_file','temp_file_no')");
echo "=== file_indexings columns ===\n";
foreach ($cols1 as $col) {
    echo $col->COLUMN_NAME . ' -> ' . $col->DATA_TYPE . "\n";
}

// Check fileNumber columns
$cols2 = DB::connection('sqlsrv')->select("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'fileNumber' AND COLUMN_NAME IN ('has_temp_file','temp_file_no')");
echo "\n=== fileNumber columns ===\n";
foreach ($cols2 as $col) {
    echo $col->COLUMN_NAME . ' -> ' . $col->DATA_TYPE . "\n";
}

// Check recent file_indexings records
$recent = DB::connection('sqlsrv')->select("SELECT TOP 5 id, file_number, has_temp_file, temp_file_no FROM file_indexings ORDER BY id DESC");
echo "\n=== Recent file_indexings records ===\n";
foreach ($recent as $r) {
    echo "id={$r->id}, file_number={$r->file_number}, has_temp_file={$r->has_temp_file}, temp_file_no={$r->temp_file_no}\n";
}

// Check fileNumber table columns
$fnCols = DB::connection('sqlsrv')->select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'fileNumber' ORDER BY ORDINAL_POSITION");
echo "\n=== fileNumber table columns ===\n";
foreach ($fnCols as $c) {
    echo $c->COLUMN_NAME . "\n";
}

// Fix existing fileNumber rows where has_temp_file is wrong based on file_indexings truth
$fixed = DB::connection('sqlsrv')->statement("
    UPDATE fn
    SET fn.has_temp_file = CAST(fi.has_temp_file AS tinyint),
        fn.temp_file_no   = fi.temp_file_no
    FROM fileNumber fn
    JOIN file_indexings fi ON fn.mlsfNo = fi.file_number
    WHERE fi.has_temp_file = 1
      AND (fn.has_temp_file = 0 OR fn.has_temp_file IS NULL OR fn.temp_file_no IS NULL OR fn.temp_file_no = '')
");
echo "\n=== Backfill result: " . ($fixed ? 'OK' : 'No rows matched') . " ===\n";

// Verify after fix
$after = DB::connection('sqlsrv')->select("SELECT TOP 10 id, mlsfNo, has_temp_file, temp_file_no FROM fileNumber WHERE has_temp_file = 1 ORDER BY id DESC");
echo "\n=== fileNumber records with has_temp_file=1 after fix ===\n";
foreach ($after as $r) {
    echo "id={$r->id}, mlsfNo={$r->mlsfNo}, has_temp_file={$r->has_temp_file}, temp_file_no={$r->temp_file_no}\n";
}
