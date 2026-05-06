<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(\Illuminate\Http\Request::capture());

use Illuminate\Support\Facades\DB;

$ready = DB::connection('sqlsrv')
    ->table('duplicate_fileno')
    ->where('comment', 'like', '[[]COFO_READY]%')
    ->count();

$collected = DB::connection('sqlsrv')
    ->table('duplicate_fileno')
    ->where('comment', 'like', '[[]COFO_COLLECTED]%')
    ->count();

echo "CofO Ready: $ready\n";
echo "CofO Collected: $collected\n";

// Show a sample row
$sample = DB::connection('sqlsrv')
    ->table('duplicate_fileno')
    ->where('comment', 'like', '[[]COFO_READY]%')
    ->first();

echo "\nSample row:\n";
print_r($sample);
