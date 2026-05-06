<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$results = DB::connection('sqlsrv')->select("
    SELECT 
        TableName = t.name,
        IndexName = ind.name,
        IndexId = ind.index_id,
        ColumnId = ic.index_column_id,
        ColumnName = col.name
    FROM 
        sys.indexes ind 
    INNER JOIN 
        sys.index_columns ic ON  ind.object_id = ic.object_id AND ind.index_id = ic.index_id 
    INNER JOIN 
        sys.columns col ON ic.object_id = col.object_id AND ic.column_id = col.column_id 
    INNER JOIN 
        sys.tables t ON ind.object_id = t.object_id 
    WHERE 
        ind.is_primary_key = 0 
        AND ind.is_unique = 0 
        AND ind.is_unique_constraint = 0 
        AND t.name = 'fileNumber'
    ORDER BY 
        t.name, ind.name, ind.index_id, ic.index_column_id;
");

foreach($results as $r) {
    echo "Table: {$r->TableName}, Index: {$r->IndexName}, Column: {$r->ColumnName}\n";
}
