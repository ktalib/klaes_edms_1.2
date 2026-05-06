<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$controller = $app->make(App\Http\Controllers\InstrumentController::class);
$resp = $controller->getNextTempFileNo();
echo "InstrumentController Response: " . $resp->getContent() . "\n";

$propController = $app->make(App\Http\Controllers\PropertyRecordController::class);
$request = new \Illuminate\Http\Request();
$resp2 = $propController->fetchTempFileNumber($request);
echo "PropertyRecordController Response: " . $resp2->getContent() . "\n";
