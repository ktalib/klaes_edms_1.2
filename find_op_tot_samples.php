<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

function printResult($title, $results) {
    echo "=== $title ===\n";
    foreach ($results as $r) {
        echo json_encode($r) . "\n";
    }
    echo "\n";
}

// 1 OP → 2 ToTs
$q1 = DB::connection('sqlsrv')->select("
    WITH OP AS (
        SELECT id, COALESCE(NULLIF(mlsFNo,''), fileno) as file_no, prop_id
        FROM pra
        WHERE (instrument_type LIKE '%Occupancy Permit%' OR transaction_type LIKE '%Occupancy Permit%')
          AND (is_deleted IS NULL OR is_deleted = 0)
    ),
    TOT AS (
        SELECT id, source_op_id, prop_id, COALESCE(NULLIF(mlsFNo,''), fileno) as file_no
        FROM pra
        WHERE (instrument_type LIKE '%Transfer of Title%' OR transaction_type LIKE '%Transfer of Title%')
          AND (is_deleted IS NULL OR is_deleted = 0)
    )
    SELECT TOP 5 op.file_no, op.id as op_id, COUNT(tot.id) as tot_count
    FROM OP op
    JOIN TOT tot ON (tot.source_op_id = op.id OR (tot.prop_id = op.prop_id AND op.prop_id IS NOT NULL AND op.prop_id != ''))
    GROUP BY op.file_no, op.id
    HAVING COUNT(tot.id) > 1
");
printResult("1 OP -> 2 ToTs (Sample File Numbers)", $q1);

// 1 OP → 2 Different Files
$q2 = DB::connection('sqlsrv')->select("
    WITH OP AS (
        SELECT id, COALESCE(NULLIF(mlsFNo,''), fileno) as file_no, prop_id
        FROM pra
        WHERE (instrument_type LIKE '%Occupancy Permit%' OR transaction_type LIKE '%Occupancy Permit%')
          AND (is_deleted IS NULL OR is_deleted = 0)
    ),
    TOT AS (
        SELECT id, source_op_id, prop_id, COALESCE(NULLIF(mlsFNo,''), fileno) as file_no
        FROM pra
        WHERE (instrument_type LIKE '%Transfer of Title%' OR transaction_type LIKE '%Transfer of Title%')
          AND (is_deleted IS NULL OR is_deleted = 0)
    )
    SELECT TOP 5 op.id as op_id, op.file_no as op_file_no, tot.file_no as tot_file_no
    FROM OP op
    JOIN TOT tot ON (tot.source_op_id = op.id OR (tot.prop_id = op.prop_id AND op.prop_id IS NOT NULL AND op.prop_id != ''))
    WHERE op.file_no != tot.file_no
      AND op.file_no IS NOT NULL AND op.file_no != ''
      AND tot.file_no IS NOT NULL AND tot.file_no != ''
");
printResult("1 OP -> 2 Different Files (OP file_no vs TOT file_no)", $q2);

// 2 OPs → 1 File
$q3 = DB::connection('sqlsrv')->select("
    SELECT TOP 5 COALESCE(NULLIF(mlsFNo,''), fileno) as file_no, COUNT(id) as op_count
    FROM pra
    WHERE (instrument_type LIKE '%Occupancy Permit%' OR transaction_type LIKE '%Occupancy Permit%')
      AND (is_deleted IS NULL OR is_deleted = 0)
      AND COALESCE(NULLIF(mlsFNo,''), fileno) IS NOT NULL
      AND COALESCE(NULLIF(mlsFNo,''), fileno) != ''
    GROUP BY COALESCE(NULLIF(mlsFNo,''), fileno)
    HAVING COUNT(id) > 1
");
printResult("2 OPs -> 1 File (Sample File Numbers)", $q3);
