<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$lu = DB::connection('sqlsrv')->table('land_uses')->select('landuse')->get()->pluck('landuse')->toArray();
echo "land_uses (" . count($lu) . "): " . json_encode($lu) . PHP_EOL;

$d = DB::connection('sqlsrv')->table('districts')->select('name')->get()->pluck('name')->toArray();
echo "districts (" . count($d) . "): " . json_encode($d) . PHP_EOL;
