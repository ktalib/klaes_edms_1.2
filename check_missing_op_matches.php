<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$targets = [395, 25];

foreach ($targets as $serial) {
    echo "=== op_serial_number {$serial} ===\n";

    $ic = DB::connection('sqlsrv')->select(
        "SELECT TOP 20 id, instrument_type, temp_fileno, prop_id, op_serial_number, reg_date, serial_no, page_no, volume_no, party_1_name, party_2_name
         FROM dbo.instrument_capture
         WHERE LTRIM(RTRIM(CAST(op_serial_number AS NVARCHAR(100)))) = ?
         ORDER BY id DESC",
        [(string) $serial]
    );

    echo "instrument_capture rows: " . count($ic) . "\n";
    foreach ($ic as $r) {
        echo json_encode((array)$r) . "\n";
    }

    $pra = DB::connection('sqlsrv')->select(
        "SELECT TOP 30 id, prop_id, temp_fileno, mlsFNo, transaction_type, instrument_type, op_serial_number, regNo, serialNo, pageNo, volumeNo, Grantor, Grantee, party_1, party_2
         FROM dbo.pra
         WHERE LTRIM(RTRIM(CAST(op_serial_number AS NVARCHAR(100)))) = ?
         ORDER BY id DESC",
        [(string) $serial]
    );

    echo "pra rows: " . count($pra) . "\n";
    foreach ($pra as $r) {
        echo json_encode((array)$r) . "\n";
    }

    echo "\n";
}
