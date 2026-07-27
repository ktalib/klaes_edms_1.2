<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$propIds = ['63874', '63286', '63288'];

foreach ($propIds as $pid) {
    echo "=== prop_id: $pid ===\n";
    $rows = DB::connection('sqlsrv')->table('pra')
        ->where('prop_id', $pid)
        ->orWhere('parent_prop_id', $pid)
        ->select(['id', 'prop_id', 'parent_prop_id', 'mlsFNo', 'fileno', 'temp_fileno', 'instrument_type', 'transaction_type', 'Grantor', 'Grantee', 'party_1', 'party_2', 'op_serial_number', 'system_source', 'created_at'])
        ->get();
    foreach ($rows as $row) {
        echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
    }
}
