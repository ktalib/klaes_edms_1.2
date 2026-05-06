<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$request = new Illuminate\Http\Request([
    'prop_id' => '80000907',
    'mls_fileno' => 'RES-2026-2110'
]);

$controller = app()->make('App\Http\Controllers\LandsOneStopShop\OpResettlementApplicationController');
$response = $controller->praTransactions($request);

$data = json_decode($response->getContent(), true);
echo "Total cards: " . count($data['data']) . "\n";
foreach ($data['data'] as $tx) {
    echo "  [{$tx['source_table']}] {$tx['instrument_type']} | party2: {$tx['party_2_name']} | plot: {$tx['plot_number']} | merger: {$tx['is_merger_op']}\n";
}
