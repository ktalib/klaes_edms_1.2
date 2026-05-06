<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$registry = 'KANGIS';
$isLands = false;
$partitionExpression = 'general_registry';
$table = 'file_indexings';
$tableHint = 'WITH (NOLOCK)';
$selectColumns = "id, registry_batch_no, batch_no, batch_no as sys_batch_no, tracking_id, general_registry, general_registry as registry, file_number as awaiting_fileno, file_number as mls_fileno, land_use_type as landuse, plot_number, district, lga";
$whereClause = "WHERE general_registry = ?";
$localAssignmentFilter = "";
$bindings = [$registry, 1, 500];

$sql = <<<SQL
WITH filtered AS (
    SELECT {$selectColumns},
           ROW_NUMBER() OVER (PARTITION BY {$partitionExpression} ORDER BY id ASC) AS registry_row
    FROM {$table} {$tableHint}
    {$whereClause}
    {$localAssignmentFilter}
)
SELECT *
FROM filtered
WHERE registry_row BETWEEN ? AND ?
ORDER BY {$partitionExpression} ASC, registry_row ASC
SQL;

try {
    $results = DB::connection('sqlsrv')->select($sql, $bindings);
    echo "Success! Found " . count($results) . " records.\n";
} catch (\Exception $e) {
    echo "SQL: " . $sql . "\n";
    echo "Error: " . $e->getMessage() . "\n";
}
