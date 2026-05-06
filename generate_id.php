<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\PropertyIdAllocationService;

$service = app()->make(PropertyIdAllocationService::class);
$newId = $service->allocateOrRetrievePropId(null, 'TEMP-999999');

echo "New ID: $newId\n";
