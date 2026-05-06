<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$landCount = DB::connection('sqlsrv')->table('land_recommendations')->where('rofo_print_count', '>', 0)->update(['rofo_print_count' => 0]);
echo "Land ROFO: reset {$landCount} record(s).\n";

$stCount = DB::connection('sqlsrv')->table('rofo')->where('print_counter', '>', 0)->update(['print_counter' => 0]);
echo "ST ROFO: reset {$stCount} record(s).\n";

echo "Done.\n";
