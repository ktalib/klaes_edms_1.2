<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- SEARCHING FOR MERGER SOURCES ---\n";

$plots = ['1044A', '1044B', '1044'];
$names = ['ABDURRAUF YAKUBU', 'DAHIRU DAMBU SA\'ADU', 'DAHIRU IDRIS'];

$results = DB::connection('sqlsrv')->table('pra')
    ->where(function($q) use ($plots) {
        foreach ($plots as $p) $q->orWhere('plot_no', 'LIKE', "%$p%");
    })
    ->orWhere(function($q) use ($names) {
        foreach ($names as $n) $q->orWhere('party_2', 'LIKE', "%$n%");
    })
    ->get(['id', 'prop_id', 'party_2', 'plot_no', 'instrument_type']);

print_r($results->toArray());
