<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$fileNumber = 'IND-RC-2026-76';
$start = microtime(true);

$record = DB::connection('sqlsrv')
    ->table('fileNumber as fn')
    ->leftJoin('users as creator_users', function ($join) {
        $join->on('creator_users.id', '=', DB::raw('TRY_CONVERT(INT, fn.created_by)'));
    })
    ->select(['fn.id', 'fn.mlsfNo', 'fn.FileName'])
    ->where('fn.mlsfNo', $fileNumber)
    ->first();

$end = microtime(true);
echo "Result: " . ($record ? "Found" : "Not Found") . "\n";
echo "Time: " . ($end - $start) . "s\n";
if ($record) {
    print_r($record);
}
