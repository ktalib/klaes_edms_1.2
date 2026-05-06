<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$request = new Illuminate\Http\Request([
    'prop_id' => '80000800',
    'mls_fileno' => 'COM-2025-756'
]);

$controller = app()->make(App\Http\Controllers\LandsOneStopShop\OpResettlementApplicationController::class);
$response = $controller->praTransactions($request);

echo json_encode(json_decode($response->getContent()), JSON_PRETTY_PRINT);
