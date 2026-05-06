<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$generated = DB::connection('sqlsrv')->table('fileNumber')->where('SOURCE', 'Generated')->count();
$commissioned = DB::connection('sqlsrv')->table('fileNumber')->where('SOURCE', 'Commissioned')->count();
$mlsCommissioned = DB::connection('sqlsrv')->table('fileNumber')->where('SOURCE', 'MLS_Commissioned')->count();

echo "Current Counts:\n";
echo "Generated: $generated\n";
echo "Commissioned: $commissioned\n";
echo "MLS_Commissioned: $mlsCommissioned\n";
