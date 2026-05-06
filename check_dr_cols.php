<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$cols = Schema::connection('sqlsrv')->getColumnListing('deed_registrations');
echo "deed_registrations columns:" . PHP_EOL;
echo implode(', ', $cols) . PHP_EOL;
