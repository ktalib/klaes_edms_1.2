<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check existing land_use values that ARE populated
$existing = DB::connection('sqlsrv')->table('rofo_staging')
    ->select(DB::raw('land_use, COUNT(*) as cnt'))
    ->whereNotNull('land_use')
    ->where('land_use', '!=', '')
    ->groupBy('land_use')
    ->orderByDesc('cnt')
    ->get();

echo "\n=== Existing land_use values ===\n";
foreach ($existing as $r) {
    echo "$r->land_use: $r->cnt\n";
}

// Check file number prefixes from mlsFNo
$prefixes = DB::connection('sqlsrv')->select("
    SELECT 
        CASE WHEN CHARINDEX('-', mlsFNo) > 0 THEN UPPER(LEFT(mlsFNo, CHARINDEX('-', mlsFNo)-1)) ELSE UPPER(mlsFNo) END as prefix,
        COUNT(*) as cnt
    FROM rofo_staging
    WHERE mlsFNo IS NOT NULL AND mlsFNo != ''
    GROUP BY CASE WHEN CHARINDEX('-', mlsFNo) > 0 THEN UPPER(LEFT(mlsFNo, CHARINDEX('-', mlsFNo)-1)) ELSE UPPER(mlsFNo) END
    ORDER BY cnt DESC
");

echo "\n=== mlsFNo prefixes ===\n";
foreach ($prefixes as $r) {
    echo "$r->prefix: $r->cnt\n";
}

// Also check kangisFileNo and NewKANGISFileno prefixes
$prefixes2 = DB::connection('sqlsrv')->select("
    SELECT 
        CASE WHEN CHARINDEX('-', kangisFileNo) > 0 THEN UPPER(LEFT(kangisFileNo, CHARINDEX('-', kangisFileNo)-1)) ELSE UPPER(kangisFileNo) END as prefix,
        COUNT(*) as cnt
    FROM rofo_staging
    WHERE kangisFileNo IS NOT NULL AND kangisFileNo != ''
    GROUP BY CASE WHEN CHARINDEX('-', kangisFileNo) > 0 THEN UPPER(LEFT(kangisFileNo, CHARINDEX('-', kangisFileNo)-1)) ELSE UPPER(kangisFileNo) END
    ORDER BY cnt DESC
");

echo "\n=== kangisFileNo prefixes ===\n";
foreach ($prefixes2 as $r) {
    echo "$r->prefix: $r->cnt\n";
}

$prefixes3 = DB::connection('sqlsrv')->select("
    SELECT 
        CASE WHEN CHARINDEX('-', fileno) > 0 THEN UPPER(LEFT(fileno, CHARINDEX('-', fileno)-1)) ELSE UPPER(fileno) END as prefix,
        COUNT(*) as cnt
    FROM rofo_staging
    WHERE fileno IS NOT NULL AND fileno != ''
    GROUP BY CASE WHEN CHARINDEX('-', fileno) > 0 THEN UPPER(LEFT(fileno, CHARINDEX('-', fileno)-1)) ELSE UPPER(fileno) END
    ORDER BY cnt DESC
");

echo "\n=== fileno prefixes ===\n";
foreach ($prefixes3 as $r) {
    echo "$r->prefix: $r->cnt\n";
}

// Count NULLs
$nullCount = DB::connection('sqlsrv')->table('rofo_staging')
    ->where(function($q) {
        $q->whereNull('land_use')->orWhere('land_use', '');
    })->count();
$total = DB::connection('sqlsrv')->table('rofo_staging')->count();
echo "\nNULL/empty land_use: $nullCount / $total total\n";
