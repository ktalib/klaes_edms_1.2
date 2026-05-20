<?php
// Laravel bootstrap script to query rds_tracking for a batch session
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$sessionId = 'IRB-20260515191808-YVFAXI';
$sql = "SET NOCOUNT ON; SELECT dr.id, dr.fileno, ISNULL(rt.instrument_id,'') AS instrument_id, ISNULL(rt.print_count,0) AS print_count FROM deed_registrations dr LEFT JOIN rds_tracking rt ON rt.instrument_id = 'deed_reg_' + CAST(dr.id AS VARCHAR(50)) WHERE dr.batch_session_id = '" . $sessionId . "' ORDER BY dr.id;";

try {
    $rows = DB::connection('sqlsrv')->select(DB::raw($sql));
    if (empty($rows)) {
        echo "No rows returned.\n";
    } else {
        foreach ($rows as $r) {
            echo sprintf("%d | %s | %s | %s\n", $r->id, $r->fileno, $r->instrument_id, $r->print_count);
        }
    }
} catch (Exception $e) {
    echo "Query failed: " . $e->getMessage() . "\n";
}
