<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = ['pra', 'instrument_capture', 'fileNumber', 'mls_file_no', 'customers_staging', 'file_indexings'];

foreach ($tables as $table) {
    echo "--- Indexes for $table ---\n";
    $results = DB::connection('sqlsrv')->select("
        SELECT 
            i.name AS index_name,
            SUBSTRING(column_names, 1, LEN(column_names)-1) AS column_names,
            i.type_desc,
            i.is_unique
        FROM sys.indexes i
        CROSS APPLY (
            SELECT ic.name + ', '
            FROM sys.index_columns idx_c
            JOIN sys.columns ic ON idx_c.object_id = ic.object_id AND idx_c.column_id = ic.column_id
            WHERE idx_c.object_id = i.object_id AND idx_c.index_id = i.index_id
            FOR XML PATH('')
        ) D (column_names)
        WHERE i.object_id = OBJECT_ID('$table')
    ");
    
    foreach ($results as $r) {
        echo ($r->is_unique ? "[UNIQUE] " : "") . "{$r->index_name}: {$r->column_names} ({$r->type_desc})\n";
    }
    echo "\n";
}
