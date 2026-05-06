<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = \DB::connection('sqlsrv');
echo "DB: " . $db->select("SELECT DB_NAME() AS n")[0]->n . "\n";
echo "pra rows with parent_prop_id IS NOT NULL: "
    . $db->table('pra')->whereNotNull('parent_prop_id')->count() . "\n";
foreach ($db->select("SELECT TOP 5 id, prop_id, parent_prop_id, source_op_table, source_op_id, instrument_type FROM pra WHERE parent_prop_id IS NOT NULL ORDER BY id DESC") as $r) {
    echo json_encode($r) . "\n";
}
