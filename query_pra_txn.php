<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$request = new \Illuminate\Http\Request();
$request->merge([
    'prop_id' => '74296',
    'mls_fileno' => 'RES-2026-2099',
    'temp_fileno' => 'TEMP-36519',
]);

$controller = app()->make(\App\Http\Controllers\LandsOneStopShop\OpResettlementApplicationController::class);
$response = $controller->praTransactions($request);
echo json_encode(json_decode($response->getContent(), true), JSON_PRETTY_PRINT);
