<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$sources = DB::connection('sqlsrv')
    ->table('fileNumber')
    ->select('SOURCE')
    ->distinct()
    ->get()
    ->pluck('SOURCE');

echo "Distinct Sources found:\n";
foreach ($sources as $source) {
    if ($source === null)
        echo "[NULL]\n";
    else
        echo "'$source'\n";
}
