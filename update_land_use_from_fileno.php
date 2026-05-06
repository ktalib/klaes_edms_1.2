<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Derive land_use from file number prefixes across mlsFNo, fileno, kangisFileNo, NewKANGISFileno
// Priority: mlsFNo > fileno > NewKANGISFileno > kangisFileNo

$sql = "
UPDATE rofo_staging
SET land_use = CASE
    -- Residential: RES, RE, RESS, RRES, RSE, REC (common typos)
    WHEN UPPER(mlsFNo) LIKE 'RES[- ]%' OR UPPER(mlsFNo) LIKE 'RESS-%' OR UPPER(mlsFNo) LIKE 'RRES-%' OR UPPER(mlsFNo) LIKE 'RSE-%' OR UPPER(mlsFNo) LIKE 'RE-%' OR UPPER(mlsFNo) LIKE 'REC-%'
        THEN 'Residential'
    WHEN UPPER(fileno) LIKE 'RES[- ]%' OR UPPER(fileno) LIKE 'RESS-%' OR UPPER(fileno) LIKE 'RRES-%'
        THEN 'Residential'
    WHEN UPPER(NewKANGISFileno) LIKE 'RES[- ]%'
        THEN 'Residential'
    WHEN UPPER(kangisFileNo) LIKE 'RES[- ]%'
        THEN 'Residential'

    -- Commercial: COM, CON, C0N, C0M, CONM (common typos with zero instead of O)
    WHEN UPPER(mlsFNo) LIKE 'COM[- ]%' OR UPPER(mlsFNo) LIKE 'CON[- ]%' OR UPPER(mlsFNo) LIKE 'C0N-%' OR UPPER(mlsFNo) LIKE 'C0M-%' OR UPPER(mlsFNo) LIKE 'CONM-%' OR UPPER(mlsFNo) LIKE 'CONRES-%'
        THEN 'Commercial'
    WHEN UPPER(fileno) LIKE 'COM[- ]%' OR UPPER(fileno) LIKE 'CON[- ]%'
        THEN 'Commercial'
    WHEN UPPER(NewKANGISFileno) LIKE 'COM[- ]%' OR UPPER(NewKANGISFileno) LIKE 'CON[- ]%'
        THEN 'Commercial'
    WHEN UPPER(kangisFileNo) LIKE 'COM[- ]%' OR UPPER(kangisFileNo) LIKE 'CON[- ]%'
        THEN 'Commercial'

    -- Industrial: IND
    WHEN UPPER(mlsFNo) LIKE 'IND[- ]%'
        THEN 'Industrial'
    WHEN UPPER(fileno) LIKE 'IND[- ]%'
        THEN 'Industrial'
    WHEN UPPER(NewKANGISFileno) LIKE 'IND[- ]%'
        THEN 'Industrial'
    WHEN UPPER(kangisFileNo) LIKE 'IND[- ]%'
        THEN 'Industrial'

    -- Agricultural: AG, AGR, AGC
    WHEN UPPER(mlsFNo) LIKE 'AG[- ]%' OR UPPER(mlsFNo) LIKE 'AGR[- ]%' OR UPPER(mlsFNo) LIKE 'AGC[- ]%'
        THEN 'Agricultural'
    WHEN UPPER(fileno) LIKE 'AG[- ]%' OR UPPER(fileno) LIKE 'AGR[- ]%' OR UPPER(fileno) LIKE 'AGC[- ]%'
        THEN 'Agricultural'
    WHEN UPPER(NewKANGISFileno) LIKE 'AG[- ]%' OR UPPER(NewKANGISFileno) LIKE 'AGR[- ]%'
        THEN 'Agricultural'
    WHEN UPPER(kangisFileNo) LIKE 'AG[- ]%' OR UPPER(kangisFileNo) LIKE 'AGR[- ]%'
        THEN 'Agricultural'

    ELSE land_use
END
WHERE land_use IS NULL OR land_use = ''
";

// Preview counts before running
echo "=== Preview: rows that WOULD be updated ===\n";

$preview = DB::connection('sqlsrv')->select("
    SELECT 
        CASE
            WHEN UPPER(mlsFNo) LIKE 'RES[- ]%' OR UPPER(mlsFNo) LIKE 'RESS-%' OR UPPER(mlsFNo) LIKE 'RRES-%' OR UPPER(mlsFNo) LIKE 'RSE-%' OR UPPER(mlsFNo) LIKE 'RE-%' OR UPPER(mlsFNo) LIKE 'REC-%'
                OR UPPER(fileno) LIKE 'RES[- ]%' OR UPPER(fileno) LIKE 'RESS-%' OR UPPER(fileno) LIKE 'RRES-%'
                OR UPPER(NewKANGISFileno) LIKE 'RES[- ]%' OR UPPER(kangisFileNo) LIKE 'RES[- ]%'
                THEN 'Residential'
            WHEN UPPER(mlsFNo) LIKE 'COM[- ]%' OR UPPER(mlsFNo) LIKE 'CON[- ]%' OR UPPER(mlsFNo) LIKE 'C0N-%' OR UPPER(mlsFNo) LIKE 'C0M-%' OR UPPER(mlsFNo) LIKE 'CONM-%' OR UPPER(mlsFNo) LIKE 'CONRES-%'
                OR UPPER(fileno) LIKE 'COM[- ]%' OR UPPER(fileno) LIKE 'CON[- ]%'
                OR UPPER(NewKANGISFileno) LIKE 'COM[- ]%' OR UPPER(NewKANGISFileno) LIKE 'CON[- ]%'
                OR UPPER(kangisFileNo) LIKE 'COM[- ]%' OR UPPER(kangisFileNo) LIKE 'CON[- ]%'
                THEN 'Commercial'
            WHEN UPPER(mlsFNo) LIKE 'IND[- ]%' OR UPPER(fileno) LIKE 'IND[- ]%' OR UPPER(NewKANGISFileno) LIKE 'IND[- ]%' OR UPPER(kangisFileNo) LIKE 'IND[- ]%'
                THEN 'Industrial'
            WHEN UPPER(mlsFNo) LIKE 'AG[- ]%' OR UPPER(mlsFNo) LIKE 'AGR[- ]%' OR UPPER(mlsFNo) LIKE 'AGC[- ]%'
                OR UPPER(fileno) LIKE 'AG[- ]%' OR UPPER(fileno) LIKE 'AGR[- ]%' OR UPPER(fileno) LIKE 'AGC[- ]%'
                OR UPPER(NewKANGISFileno) LIKE 'AG[- ]%' OR UPPER(NewKANGISFileno) LIKE 'AGR[- ]%'
                OR UPPER(kangisFileNo) LIKE 'AG[- ]%' OR UPPER(kangisFileNo) LIKE 'AGR[- ]%'
                THEN 'Agricultural'
            ELSE 'UNMATCHED'
        END as derived_land_use,
        COUNT(*) as cnt
    FROM rofo_staging
    WHERE land_use IS NULL OR land_use = ''
    GROUP BY CASE
            WHEN UPPER(mlsFNo) LIKE 'RES[- ]%' OR UPPER(mlsFNo) LIKE 'RESS-%' OR UPPER(mlsFNo) LIKE 'RRES-%' OR UPPER(mlsFNo) LIKE 'RSE-%' OR UPPER(mlsFNo) LIKE 'RE-%' OR UPPER(mlsFNo) LIKE 'REC-%'
                OR UPPER(fileno) LIKE 'RES[- ]%' OR UPPER(fileno) LIKE 'RESS-%' OR UPPER(fileno) LIKE 'RRES-%'
                OR UPPER(NewKANGISFileno) LIKE 'RES[- ]%' OR UPPER(kangisFileNo) LIKE 'RES[- ]%'
                THEN 'Residential'
            WHEN UPPER(mlsFNo) LIKE 'COM[- ]%' OR UPPER(mlsFNo) LIKE 'CON[- ]%' OR UPPER(mlsFNo) LIKE 'C0N-%' OR UPPER(mlsFNo) LIKE 'C0M-%' OR UPPER(mlsFNo) LIKE 'CONM-%' OR UPPER(mlsFNo) LIKE 'CONRES-%'
                OR UPPER(fileno) LIKE 'COM[- ]%' OR UPPER(fileno) LIKE 'CON[- ]%'
                OR UPPER(NewKANGISFileno) LIKE 'COM[- ]%' OR UPPER(NewKANGISFileno) LIKE 'CON[- ]%'
                OR UPPER(kangisFileNo) LIKE 'COM[- ]%' OR UPPER(kangisFileNo) LIKE 'CON[- ]%'
                THEN 'Commercial'
            WHEN UPPER(mlsFNo) LIKE 'IND[- ]%' OR UPPER(fileno) LIKE 'IND[- ]%' OR UPPER(NewKANGISFileno) LIKE 'IND[- ]%' OR UPPER(kangisFileNo) LIKE 'IND[- ]%'
                THEN 'Industrial'
            WHEN UPPER(mlsFNo) LIKE 'AG[- ]%' OR UPPER(mlsFNo) LIKE 'AGR[- ]%' OR UPPER(mlsFNo) LIKE 'AGC[- ]%'
                OR UPPER(fileno) LIKE 'AG[- ]%' OR UPPER(fileno) LIKE 'AGR[- ]%' OR UPPER(fileno) LIKE 'AGC[- ]%'
                OR UPPER(NewKANGISFileno) LIKE 'AG[- ]%' OR UPPER(NewKANGISFileno) LIKE 'AGR[- ]%'
                OR UPPER(kangisFileNo) LIKE 'AG[- ]%' OR UPPER(kangisFileNo) LIKE 'AGR[- ]%'
                THEN 'Agricultural'
            ELSE 'UNMATCHED'
        END
    ORDER BY cnt DESC
");

foreach ($preview as $r) {
    echo "  $r->derived_land_use: $r->cnt\n";
}

// Run the update
echo "\n=== Running UPDATE... ===\n";
$affected = DB::connection('sqlsrv')->update($sql);
echo "Rows updated: $affected\n";

// Verify after
$after = DB::connection('sqlsrv')->table('rofo_staging')
    ->select(DB::raw('land_use, COUNT(*) as cnt'))
    ->groupBy('land_use')
    ->orderByDesc('cnt')
    ->get();

echo "\n=== land_use distribution after update ===\n";
foreach ($after as $r) {
    $lu = $r->land_use ?? 'NULL';
    echo "  '$lu': $r->cnt\n";
}
