<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check existing populated land_use values
$existing = DB::connection('sqlsrv')->table('rofo_staging')
    ->select(DB::raw('land_use, COUNT(*) as cnt'))
    ->whereNotNull('land_use')
    ->where('land_use', '!=', '')
    ->groupBy('land_use')
    ->orderByDesc('cnt')
    ->get();

echo "=== Existing land_use values ===\n";
foreach ($existing as $r) {
    echo "  '$r->land_use': $r->cnt\n";
}

// mlsFNo prefixes (only where mlsFNo contains a dash)
$mls = DB::connection('sqlsrv')->select("
    SELECT 
        UPPER(LEFT(mlsFNo, CHARINDEX('-', mlsFNo)-1)) as prefix,
        COUNT(*) as cnt
    FROM rofo_staging
    WHERE mlsFNo IS NOT NULL AND mlsFNo != '' AND CHARINDEX('-', mlsFNo) > 0
    GROUP BY UPPER(LEFT(mlsFNo, CHARINDEX('-', mlsFNo)-1))
    ORDER BY cnt DESC
");
echo "\n=== mlsFNo dash-prefixes ===\n";
foreach ($mls as $r) {
    echo "  $r->prefix: $r->cnt\n";
}

// Check mlsFNo patterns WITHOUT dashes
$nondash = DB::connection('sqlsrv')->table('rofo_staging')
    ->where('mlsFNo', '!=', '')
    ->whereNotNull('mlsFNo')
    ->whereRaw("CHARINDEX('-', mlsFNo) = 0")
    ->count();
echo "\nmlsFNo without dashes: $nondash\n";

// Sample non-dash mlsFNo
$samples = DB::connection('sqlsrv')->select("
    SELECT mlsFNo FROM rofo_staging 
    WHERE mlsFNo IS NOT NULL AND mlsFNo != '' AND CHARINDEX('-', mlsFNo) = 0
    ORDER BY mlsFNo OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY
");
echo "Samples: ";
foreach ($samples as $r) echo "'$r->mlsFNo' ";
echo "\n";

// Check how many records have at least one file number field with a recognizable prefix
$withPrefix = DB::connection('sqlsrv')->select("
    SELECT 
        SUM(CASE WHEN UPPER(mlsFNo) LIKE 'RES-%' OR UPPER(fileno) LIKE 'RES-%' OR UPPER(kangisFileNo) LIKE 'RES-%' OR UPPER(NewKANGISFileno) LIKE 'RES-%' THEN 1 ELSE 0 END) as res_count,
        SUM(CASE WHEN UPPER(mlsFNo) LIKE 'COM-%' OR UPPER(fileno) LIKE 'COM-%' OR UPPER(kangisFileNo) LIKE 'COM-%' OR UPPER(NewKANGISFileno) LIKE 'COM-%' OR UPPER(mlsFNo) LIKE 'CON-%' OR UPPER(fileno) LIKE 'CON-%' THEN 1 ELSE 0 END) as com_count,
        SUM(CASE WHEN UPPER(mlsFNo) LIKE 'IND-%' OR UPPER(fileno) LIKE 'IND-%' OR UPPER(kangisFileNo) LIKE 'IND-%' OR UPPER(NewKANGISFileno) LIKE 'IND-%' THEN 1 ELSE 0 END) as ind_count,
        SUM(CASE WHEN UPPER(mlsFNo) LIKE 'AGR-%' OR UPPER(fileno) LIKE 'AGR-%' OR UPPER(kangisFileNo) LIKE 'AGR-%' OR UPPER(NewKANGISFileno) LIKE 'AGR-%' OR UPPER(mlsFNo) LIKE 'AGC-%' OR UPPER(fileno) LIKE 'AGC-%' THEN 1 ELSE 0 END) as agr_count,
        COUNT(*) as total
    FROM rofo_staging
");
$r = $withPrefix[0];
echo "\n=== Records by land use prefix (across all file number columns) ===\n";
echo "  RES (Residential): $r->res_count\n";
echo "  COM/CON (Commercial): $r->com_count\n";
echo "  IND (Industrial): $r->ind_count\n";
echo "  AGR/AGC (Agricultural): $r->agr_count\n";
echo "  Total: $r->total\n";

// Check NULL/empty land_use
$nullCount = DB::connection('sqlsrv')->table('rofo_staging')
    ->where(function($q) {
        $q->whereNull('land_use')->orWhere('land_use', '');
    })->count();
echo "\nNULL/empty land_use: $nullCount\n";
