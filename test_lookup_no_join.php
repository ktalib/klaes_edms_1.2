<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$fileNumber = 'IND-RC-2026-76';
$start = microtime(true);

$record = DB::connection('sqlsrv')
    ->table('fileNumber as fn')
    ->select(['fn.id', 'fn.mlsfNo', 'fn.FileName'])
    ->where('fn.mlsfNo', $fileNumber)
    ->first();

$end = microtime(true);
echo "Result: Found\n";
echo "Time: " . ($end - $start) . "s\n";
