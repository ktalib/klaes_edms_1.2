<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Check distinct values of general_registry in file_indexings to understand format
$results = DB::connection('sqlsrv')->table('file_indexings')
    ->selectRaw('DISTINCT general_registry')
    ->whereNotNull('general_registry')
    ->whereRaw("general_registry != ''")
    ->get();

echo "Distinct general_registry values:\n";
foreach ($results as $row) {
    echo "  '{$row->general_registry}'\n";
}
