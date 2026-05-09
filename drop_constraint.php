<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    DB::connection('sqlsrv')->statement("
        IF EXISTS (SELECT * FROM sys.objects WHERE name = 'uq_kangis_batch_prefix_sysbatchno' AND parent_object_id = OBJECT_ID('kangis_print_label_batches'))
        BEGIN
            ALTER TABLE kangis_print_label_batches DROP CONSTRAINT uq_kangis_batch_prefix_sysbatchno;
        END
    ");
    echo 'Constraint dropped successfully.' . PHP_EOL;
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
