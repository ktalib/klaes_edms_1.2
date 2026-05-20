<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

$sessionId = 'IRB-20260515191808-YVFAXI';
try {
    $rows = DB::connection('mysql')->table('deed_registrations')
        ->select('id','fileno','batch_session_id')
        ->where('batch_session_id',$sessionId)
        ->orderBy('id')
        ->get();

    echo 'Count: ' . count($rows) . "\n";
    foreach($rows as $r) {
        echo sprintf("%d | %s | %s\n", $r->id, $r->fileno ?? '-', $r->batch_session_id ?? '-');
    }
} catch (Exception $e) {
    echo 'Query failed: ' . $e->getMessage() . "\n";
}
