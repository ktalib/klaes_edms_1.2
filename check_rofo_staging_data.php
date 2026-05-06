<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$count = DB::connection('sqlsrv')->table('rofo_staging')->count();
echo "rofo_staging count: $count\n";

$sample = DB::connection('sqlsrv')->table('rofo_staging')
    ->select('id','mlsFNo','kangisFileNo','NewKANGISFileno','instrument_type','transaction_type',
             'Grantor','Grantee','property_description','location','lgsaOrCity','land_use','plot_no',
             'created_at','fileno','temp_fileno')
    ->limit(3)
    ->get();

foreach ($sample as $r) {
    echo json_encode($r) . "\n";
}

// Check distinct instrument_types and transaction_types
echo "\n--- instrument_types ---\n";
$types = DB::connection('sqlsrv')->table('rofo_staging')
    ->select(DB::raw('instrument_type, COUNT(*) as cnt'))
    ->groupBy('instrument_type')
    ->orderByDesc('cnt')
    ->limit(20)
    ->get();
foreach ($types as $t) {
    echo ($t->instrument_type ?? 'NULL') . ": $t->cnt\n";
}

echo "\n--- transaction_types ---\n";
$ttypes = DB::connection('sqlsrv')->table('rofo_staging')
    ->select(DB::raw('transaction_type, COUNT(*) as cnt'))
    ->groupBy('transaction_type')
    ->orderByDesc('cnt')
    ->limit(20)
    ->get();
foreach ($ttypes as $t) {
    echo ($t->transaction_type ?? 'NULL') . ": $t->cnt\n";
}
