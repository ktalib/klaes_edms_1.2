<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$cols = DB::connection('sqlsrv')->getSchemaBuilder()->getColumnListing('pra');
foreach ($cols as $c) {
    if (stripos($c, 'comment') !== false || stripos($c, 'remark') !== false) {
        echo $c . PHP_EOL;
    }
}
echo "---DONE---\n";
