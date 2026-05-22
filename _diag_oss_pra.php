<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
$db = DB::connection('sqlsrv');

echo "--- distinct application_type ---\n";
$types = $db->table('oss_applications')->select('application_type')->distinct()->pluck('application_type');
foreach($types as $t) { echo "$t\n"; }

$conTypes = $db->table('oss_applications')->where('application_type', 'like', '%name%')->get();
echo "\n--- 'name' types count: " . count($conTypes) . " ---\n";

if (count($conTypes) > 0) {
    echo "Sample system_source: " . $conTypes[0]->system_source . "\n";
    echo "Sample instrument_capture_id: " . $conTypes[0]->instrument_capture_id . "\n";
}

echo "\n--- Checking PRA linkage ---\n";
// Let's see how many PRA records have OSSOPCHANGEOFNAME
$praCount = $db->table('pra')->where('system_source', 'OSSOPCHANGEOFNAME')->count();
echo "PRA count for OSSOPCHANGEOFNAME: $praCount\n";

$backupCount = $db->table('pra_duplicates_backup2')->where('system_source', 'OSSOPCHANGEOFNAME')->count();
echo "Backup count for OSSOPCHANGEOFNAME: $backupCount\n";

// Let's find missing links
$ossCount = $db->table('oss_applications')->where('application_type', 'Change of Name')->count();
echo "OSS 'Change of Name' count: $ossCount\n";

// Let's find how they link. Are they linked by instrument_capture_id?
$linkedToPra = $db->select("
    SELECT COUNT(*) as cnt
    FROM oss_applications o
    JOIN pra p ON o.id = p.instrument_capture_id
    WHERE o.application_type = 'Change of Name'
")[0]->cnt;
echo "OSS linked to PRA by instrument_capture_id: $linkedToPra\n";

$linkedToPraByOpSerial = $db->select("
    SELECT COUNT(*) as cnt
    FROM oss_applications o
    JOIN pra p ON o.op_serial_number = p.op_serial_number
    WHERE o.application_type = 'Change of Name'
")[0]->cnt;
echo "OSS linked to PRA by op_serial_number: $linkedToPraByOpSerial\n";
