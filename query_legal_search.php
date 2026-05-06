<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$request = new \Illuminate\Http\Request();
$request->merge([
    'fileno' => 'TEMP-36519',
    // 'fileno' => 'RES-2026-2099'
]);

$controller = app()->make(\App\Http\Controllers\LegalSearchController::class);
$response = $controller->search($request);
echo json_encode(json_decode($response->getContent(), true), JSON_PRETTY_PRINT);
