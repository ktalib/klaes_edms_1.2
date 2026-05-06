<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Simulate the logic in OpResettlementApplicationController@index for Change of Name
$isChangeOfName = true;
$instrumentFilter = $isChangeOfName 
    ? "AND (pra.instrument_type LIKE '%Transfer of Title%' OR pra.transaction_type LIKE '%Transfer of Title%')" 
    : "";
$instrumentFilterIc = $isChangeOfName 
    ? "AND '0' = '1'" 
    : "";

$baseQuery = "
    SELECT *
    FROM (
        SELECT 
            pra.id,
            pra.prop_id, 
            pra.parent_prop_id,
            pra.mlsFNo as mls_file_no,
            pra.fileno as source_fileno,
            pra.temp_fileno as source_temp_fileno,
            pra.instrument_type as pra_instrument_type,
            pra.party_1,
            pra.party_2,
            'pra' as source_table
        FROM pra
        WHERE pra.system_source = 'OSSOPCHANGEOFNAME'
          AND pra.prop_id IS NOT NULL AND pra.prop_id != ''
          $instrumentFilter

        UNION ALL

        SELECT 
            ic.id,
            ic.prop_id,
            ic.parent_prop_id,
            NULL as mls_file_no,
            ic.mlsFNo as source_fileno, 
            ic.temp_fileno as source_temp_fileno,
            NULL as pra_instrument_type,
            ic.party_1_name as party_1,
            NULL as party_2,
            'instrument_capture' as source_table
        FROM instrument_capture ic
        LEFT JOIN pra px ON px.prop_id = ic.prop_id 
                        AND px.system_source = 'OSSOPCHANGEOFNAME'
                        AND (px.instrument_type IS NULL OR px.instrument_type NOT LIKE '%Transfer of Title%')
                        AND (px.transaction_type IS NULL OR px.transaction_type NOT LIKE '%Transfer of Title%')
        WHERE ic.instrument_type LIKE '%Occupancy Permit%'
          AND ic.prop_id IS NOT NULL AND ic.prop_id != ''
          AND px.id IS NULL
          $instrumentFilterIc
    ) p
    WHERE p.source_temp_fileno = 'TEMP-04080'
";

$results = \DB::connection('sqlsrv')->select($baseQuery);
print_r($results);
