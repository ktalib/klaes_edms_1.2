<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$allExpected = [
    'res_addr_plot', 'res_addr_street', 'res_addr_street_other',
    'res_addr_district', 'res_addr_district_other', 'res_addr_lga', 'res_addr_state',
    'res_corr_plot', 'res_corr_street', 'res_corr_street_other',
    'res_corr_district', 'res_corr_district_other', 'res_corr_lga', 'res_corr_state',
    'res_biz_plot', 'res_biz_street', 'res_biz_street_other',
    'res_biz_district', 'res_biz_district_other', 'res_biz_lga', 'res_biz_state',
    'com_corr_plot', 'com_corr_street', 'com_corr_street_other',
    'com_corr_district', 'com_corr_district_other', 'com_corr_lga', 'com_corr_state',
    'ind_corr_plot', 'ind_corr_street', 'ind_corr_street_other',
    'ind_corr_district', 'ind_corr_district_other', 'ind_corr_lga', 'ind_corr_state',
];

// Check which columns already exist
$existing = DB::connection('sqlsrv')->select(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'oss_applications'"
);
$existingNames = array_map(fn($c) => $c->COLUMN_NAME, $existing);

$missing = array_diff($allExpected, $existingNames);

if (empty($missing)) {
    echo "All 35 address builder columns already exist!\n";
    exit(0);
}

echo "Missing columns: " . count($missing) . "\n";
foreach ($missing as $col) {
    echo "  Adding: $col\n";
    $size = str_contains($col, '_plot') ? 100 : 255;
    DB::connection('sqlsrv')->statement(
        "ALTER TABLE oss_applications ADD [$col] NVARCHAR($size) NULL"
    );
}

// Verify
$afterExisting = DB::connection('sqlsrv')->select(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'oss_applications' AND COLUMN_NAME LIKE '%corr%' OR COLUMN_NAME LIKE '%biz%' ORDER BY COLUMN_NAME"
);
echo "\nVerification - corr/biz columns now:\n";
foreach ($afterExisting as $c) {
    echo "  - " . $c->COLUMN_NAME . "\n";
}
echo "Done!\n";
