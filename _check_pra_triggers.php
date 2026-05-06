<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$triggers = DB::connection('sqlsrv')->select("
    SELECT name, OBJECT_DEFINITION(object_id) as definition
    FROM sys.triggers
    WHERE parent_id = OBJECT_ID('pra')
");

echo count($triggers) . ' trigger(s) found on pra table' . PHP_EOL;
foreach ($triggers as $t) {
    echo '=== ' . $t->name . ' ===' . PHP_EOL;
    echo $t->definition . PHP_EOL . PHP_EOL;
}
