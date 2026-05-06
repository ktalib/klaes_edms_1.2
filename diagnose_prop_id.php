<?php
/**
 * Prop ID Diagnostic Script
 * Scans the database for corrupted prop_id assignments.
 */

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Starting Prop_ID Diagnostic Scan...\n";
echo "====================================\n\n";

$report = [
    'summary' => [
        'total_conflicts_found' => 0,
        'conflicts_on_target_date' => 0,
    ],
    'duplicate_prop_ids' => [],
    'mismatches' => [],
    'tot_op_conflicts' => []
];

// 1. Find prop_ids that have multiple completely unrelated files (ignoring known aliases)
echo "1. Scanning for Duplicate prop_id values assigned to different file numbers...\n";

// We use CTEs to pull all files, but we join against PropID_Master to ensure we don't flag valid aliases.
$sql = "
    WITH CombinedFiles AS (
        SELECT TRY_CAST(prop_id AS BIGINT) as prop_id, UPPER(LTRIM(RTRIM(mlsFNo))) as file_no, created_at 
        FROM pra WHERE mlsFNo IS NOT NULL AND mlsFNo != '' AND prop_id IS NOT NULL
        UNION
        SELECT TRY_CAST(prop_id AS BIGINT) as prop_id, UPPER(LTRIM(RTRIM(mlsFNo))) as file_no, created_at 
        FROM instrument_capture WHERE mlsFNo IS NOT NULL AND mlsFNo != '' AND prop_id IS NOT NULL
        UNION
        SELECT TRY_CAST(prop_id AS BIGINT) as prop_id, UPPER(LTRIM(RTRIM(mlsFNo))) as file_no, created_at 
        FROM file_history_staging WHERE mlsFNo IS NOT NULL AND mlsFNo != '' AND prop_id IS NOT NULL
    )
    SELECT 
        c.prop_id, 
        COUNT(DISTINCT c.file_no) as distinct_files,
        STRING_AGG(CAST(c.file_no AS NVARCHAR(MAX)), ', ') as file_numbers,
        MAX(c.created_at) as latest_created_at
    FROM CombinedFiles c
    LEFT JOIN PropID_Master m ON c.prop_id = m.prop_id
    WHERE c.prop_id IS NOT NULL 
      AND c.file_no NOT IN ('N/A', 'NA', 'NONE', 'NULL', 'NIL', '-', '.', '0', 'TBD')
      -- Ignore files that are explicitly registered as aliases in the Master table
      AND c.file_no NOT IN (
          ISNULL(m.primary_file_number, ''), 
          ISNULL(m.mlsFNo, ''), 
          ISNULL(m.kangisFileNo, ''), 
          ISNULL(m.temp_fileno, '')
      )
    GROUP BY c.prop_id
    HAVING COUNT(DISTINCT c.file_no) > 1
";

$duplicates = DB::connection('sqlsrv')->select($sql);

$report['summary']['total_conflicts_found'] = count($duplicates);

foreach ($duplicates as $dup) {
    $fileNumbers = array_unique(explode(', ', $dup->file_numbers));
    $isTargetDate = strpos($dup->latest_created_at, '2026-05-04') !== false;
    
    if ($isTargetDate) {
        $report['summary']['conflicts_on_target_date']++;
    }

    $report['duplicate_prop_ids'][] = [
        'prop_id' => $dup->prop_id,
        'file_numbers' => array_values($fileNumbers),
        'latest_created_at' => $dup->latest_created_at,
        'severity' => 'HIGH',
        'is_target_date' => $isTargetDate
    ];
}

echo "Found " . count($duplicates) . " prop_ids with true unaliased distinct file numbers.\n";

// 2. Scan for TOTs and OPs crossing over
echo "2. Scanning for TOT records with invalid/extra OP entries...\n";
$totSql = "
    SELECT t.id as tot_id, t.mlsFNo as tot_file, t.prop_id,
           o.id as op_id, o.mlsFNo as op_file
    FROM pra t
    JOIN pra o ON t.prop_id = o.prop_id
    WHERE t.instrument_type LIKE '%Transfer of Title%'
      AND o.instrument_type LIKE '%Occupancy Permit%'
      AND UPPER(LTRIM(RTRIM(t.mlsFNo))) != UPPER(LTRIM(RTRIM(o.mlsFNo)))
      AND t.mlsFNo NOT IN ('N/A', 'NONE', '-')
      AND o.mlsFNo NOT IN ('N/A', 'NONE', '-')
";
$totConflicts = DB::connection('sqlsrv')->select($totSql);

echo "Found " . count($totConflicts) . " TOT records with conflicting OP files.\n\n";

foreach ($totConflicts as $conf) {
    $report['tot_op_conflicts'][] = [
        'prop_id' => $conf->prop_id,
        'tot_file_no' => $conf->tot_file,
        'tot_record_id' => $conf->tot_id,
        'op_file_no' => $conf->op_file,
        'op_record_id' => $conf->op_id,
    ];
}

file_put_contents('prop_id_diagnostic_report.json', json_encode($report, JSON_PRETTY_PRINT));

echo "Scan complete! Detailed report saved to: " . __DIR__ . "/prop_id_diagnostic_report.json\n\n";
echo "--- SUMMARY ---\n";
echo "Total True prop_id Conflicts: " . $report['summary']['total_conflicts_found'] . "\n";
echo "Conflicts on Target Date (2026-05-04): " . $report['summary']['conflicts_on_target_date'] . "\n";
echo "TOT vs OP Conflicts: " . count($totConflicts) . "\n";
