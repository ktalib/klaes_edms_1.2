<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/api/instruments/op-serial-lookup?op_serial_number=306', 'GET');
$request->headers->set('Accept', 'application/json');
$response = $kernel->handle($request);
echo $response->getContent() . "\n";
