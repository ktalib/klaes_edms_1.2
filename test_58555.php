<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$request = new Illuminate\Http\Request([
    'prop_id' => '58555',
    'mls_fileno' => 'IND-2026-24'
]);

$controller = app()->make('App\Http\Controllers\LandsOneStopShop\OpResettlementApplicationController');
$response = $controller->praTransactions($request);

echo json_encode(json_decode($response->getContent()), JSON_PRETTY_PRINT);
