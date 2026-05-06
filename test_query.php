<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$record = Illuminate\Support\Facades\DB::connection('sqlsrv')
    ->table('fileNumber')
    ->where('mlsfNo', 'AG-2026-8')
    ->first();

var_dump($record->pp_lands_matching);
var_dump($record->pp_st_matching);
var_dump($record->pp_sltr_matching);
