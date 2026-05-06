<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

function normalizeSearch($value) {
    $value = strtoupper(trim((string) $value));
    if ($value === '') return '';
    return str_replace(['-', '/', ' ', '.', '\\'], '', $value);
}

$search = "AG-2026-8";
$normalizedSearch = normalizeSearch($search);
$excludeMatched = "st";

$query = Illuminate\Support\Facades\DB::connection('sqlsrv')
    ->table('fileNumber')
    ->select('mlsfNo', 'pp_st_matching')
    ->whereNotNull('mlsfNo')
    ->where('mlsfNo', '!=', '');

if (!empty($excludeMatched)) {
    switch (strtolower($excludeMatched)) {
        case 'st':
            $query->where(function($q) {
                $q->whereNull('pp_st_matching')
                  ->orWhere(function ($q2) {
                        $q2->where('pp_st_matching', '!=', 1)
                           ->where('pp_st_matching', '!=', '1');
                  });
            });
            break;
    }
}

if (!empty($search)) {
    $upperSearch = strtoupper($search);
    $searchWildcard = '%' . $upperSearch . '%';
    $normalizedWildcard = '%' . $normalizedSearch . '%';

    $query->where(function ($q) use ($searchWildcard, $normalizedWildcard) {
        $q->whereRaw('UPPER(mlsfNo) LIKE ?', [$searchWildcard])
          ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(UPPER(mlsfNo), '-', ''), '/', ''), ' ', ''), '.', '') LIKE ?", [$normalizedWildcard]);
    });
}

$results = $query->get()->toArray();

var_dump("Query count for AG-2026-8 with ST excluded:");
var_dump(count($results));
var_dump($results);
