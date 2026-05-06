<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$r = DB::connection('sqlsrv')->selectOne("
    SELECT 
        COUNT(CASE WHEN LTRIM(RTRIM(ISNULL(property_description,''))) != '' THEN 1 END) as pd,
        COUNT(CASE WHEN LTRIM(RTRIM(ISNULL(location,''))) != '' THEN 1 END) as loc,
        COUNT(CASE WHEN LTRIM(RTRIM(ISNULL(streetName,''))) != '' THEN 1 END) as st,
        COUNT(CASE WHEN LTRIM(RTRIM(ISNULL(districtName,''))) != '' THEN 1 END) as dist,
        COUNT(CASE WHEN LTRIM(RTRIM(ISNULL(plot_no,''))) != '' THEN 1 END) as plt,
        COUNT(CASE WHEN LTRIM(RTRIM(ISNULL(house_no,''))) != '' THEN 1 END) as hno
    FROM rofo_staging
");

echo "property_description: $r->pd\n";
echo "location: $r->loc\n";
echo "streetName: $r->st\n";
echo "districtName: $r->dist\n";
echo "plot_no: $r->plt\n";
echo "house_no: $r->hno\n";

// Show a few rows with street/district data
$samples = DB::connection('sqlsrv')->table('rofo_staging')
    ->select('id','streetName','districtName','plot_no','house_no','location','property_description')
    ->whereRaw("LTRIM(RTRIM(ISNULL(streetName,''))) != '' OR LTRIM(RTRIM(ISNULL(districtName,''))) != ''")
    ->limit(5)
    ->get();

echo "\n--- Samples with street/district data ---\n";
foreach ($samples as $s) {
    echo json_encode($s) . "\n";
}
