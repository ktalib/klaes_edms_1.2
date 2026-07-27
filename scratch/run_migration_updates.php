<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "Starting update on related_file_number...\n";
    $affected = DB::connection('sqlsrv')->update("
        UPDATE [dbo].[related_file_number]
        SET    transaction_type = 'Land Recertification (File Commissioning',
               comment = related_fileno
        WHERE  transaction_type = 'Ministry of Land & Physical Planning Recertification'
           OR  transaction_type = 'Ministry of Land and Physical Planning Recertification'
           OR  comment LIKE 'MINISTRY OF LAND AND PHYSICAL%'
    ");
    echo "Update complete. Affected rows: $affected\n";
} catch (\Throwable $e) {
    echo "Error running updates: " . $e->getMessage() . "\n";
}
