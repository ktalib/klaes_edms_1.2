<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$result = Illuminate\Support\Facades\DB::connection('sqlsrv')
    ->table('fileNumber')
    ->where('mlsfNo', 'AG-2026-8')
    ->where(function ($q) {
        $q->whereNull('pp_st_matching')
          ->orWhere(function ($q2) {
              $q2->where('pp_st_matching', '!=', 1)
                 ->where('pp_st_matching', '!=', '1');
          });
    })
    ->exists();

var_dump("Query Result Should Be False:");
var_dump($result);
