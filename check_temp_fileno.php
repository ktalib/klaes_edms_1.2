<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tables = ['pra', 'pic', 'instrument_capture'];
$prefix = 'TEMP-';

foreach ($tables as $table) {
    echo "Table: $table\n";
    if (!Schema::connection('sqlsrv')->hasTable($table)) {
        echo "Table does not exist.\n\n";
        continue;
    }

    $colsToCheck = ['temp_fileno', 'mlsFNo'];
    foreach ($colsToCheck as $col) {
        if (!Schema::connection('sqlsrv')->hasColumn($table, $col)) {
            continue;
        }

        $latestValue = DB::connection('sqlsrv')
            ->table($table)
            ->whereNotNull($col)
            ->where($col, 'like', $prefix . '%')
            ->orderByDesc(DB::raw("TRY_CONVERT(INT, REPLACE(CAST(" . $col . " AS VARCHAR), '{$prefix}', ''))"))
            ->orderByDesc($col)
            ->value($col);

        echo "  Column $col - Max value: " . ($latestValue ?: 'none') . "\n";
    }
    echo "\n";
}
