<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$request = new Illuminate\Http\Request([
    'prop_id' => '83383',
    'mls_fileno' => 'RES-2026-2100'
]);

$controller = app()->make('App\Http\Controllers\LandsOneStopShop\OpResettlementApplicationController');
$response = $controller->praTransactions($request);

echo json_encode(json_decode($response->getContent()), JSON_PRETTY_PRINT);
