<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $indexes = DB::connection('sqlsrv')->select("
        SELECT 
            i.name AS IndexName,
            OBJECT_NAME(i.object_id) AS TableName,
            COL_NAME(ic.object_id, ic.column_id) AS ColumnName
        FROM 
            sys.indexes i
        INNER JOIN 
            sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
        WHERE 
            i.name = 'uq_kangis_batch_prefix_sysbatchno'
    ");
    foreach ($indexes as $idx) {
        echo "Index: {$idx->IndexName}, Table: {$idx->TableName}, Column: {$idx->ColumnName}" . PHP_EOL;
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
