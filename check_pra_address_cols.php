<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cols = Illuminate\Support\Facades\Schema::connection('sqlsrv')->getColumnListing('pra');
$addressCols = array_filter($cols, function($c) {
    return preg_match('/address|street|state|district|lga|city|location/i', $c);
});
echo "PRA address-related columns:\n";
foreach ($addressCols as $c) echo "  - $c\n";

echo "\nInstrument capture address-related columns:\n";
$cols2 = Illuminate\Support\Facades\Schema::connection('sqlsrv')->getColumnListing('instrument_capture');
$addressCols2 = array_filter($cols2, function($c) {
    return preg_match('/address|street|state|district|lga|city|location/i', $c);
});
foreach ($addressCols2 as $c) echo "  - $c\n";
