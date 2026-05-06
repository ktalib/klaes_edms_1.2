<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cols = DB::connection('sqlsrv')->select("
    SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME='pra'
      AND COLUMN_NAME IN ('source_op_table','source_op_id','parent_prop_id')
    ORDER BY COLUMN_NAME
");
echo "Columns on pra:\n";
foreach ($cols as $c) {
    printf("  %-18s %-12s len=%-6s nullable=%s\n",
        $c->COLUMN_NAME, $c->DATA_TYPE,
        (string)($c->CHARACTER_MAXIMUM_LENGTH ?? '-'),
        $c->IS_NULLABLE);
}

$ix = DB::connection('sqlsrv')->select("
    SELECT i.name AS ix_name, c.name AS col_name, ic.key_ordinal
    FROM sys.indexes i
    JOIN sys.index_columns ic ON ic.object_id=i.object_id AND ic.index_id=i.index_id
    JOIN sys.columns c ON c.object_id=ic.object_id AND c.column_id=ic.column_id
    WHERE i.object_id = OBJECT_ID('pra')
      AND i.name IN ('IX_pra_source_op','IX_pra_parent_prop_id')
    ORDER BY i.name, ic.key_ordinal
");
echo "\nIndexes:\n";
foreach ($ix as $r) {
    echo "  " . $r->ix_name . " -> " . $r->col_name . " (#" . $r->key_ordinal . ")\n";
}
