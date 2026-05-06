<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$row = \Illuminate\Support\Facades\DB::connection('sqlsrv')->table('pra')->where('mlsFNo', 'RES-2026-24')->first();
print_r($row);
$ic = \Illuminate\Support\Facades\DB::connection('sqlsrv')->table('instrument_capture')->where('mlsFNo', 'RES-2026-24')->first();
print_r($ic);
