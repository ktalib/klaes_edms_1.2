<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$controller = app()->make('App\Http\Controllers\LandsOneStopShop\OpResettlementApplicationController');

$tests = [
    ['prop_id' => '80000839', 'mls_fileno' => 'RES-2026-2112'],
    ['prop_id' => '69578851', 'mls_fileno' => 'RES-2026-2111'],
    ['prop_id' => '69575565', 'mls_fileno' => 'RES-2026-2109'],
];

foreach ($tests as $test) {
    $request = new Illuminate\Http\Request($test);
    $response = $controller->praTransactions($request);
    $data = json_decode($response->getContent(), true);

    echo "\n=== {$test['mls_fileno']} ===\n";
    foreach ($data['data'] as $tx) {
        echo "  [{$tx['source_table']}] {$tx['instrument_type']} | party2: {$tx['party_2_name']} | plot: {$tx['plot_number']}\n";
    }
}
