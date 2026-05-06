<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = \DB::connection('sqlsrv');
echo "DB: " . $db->select("SELECT DB_NAME() AS n")[0]->n . "\n";

echo "\n=== PRA rows with system_source=OSSOPCHANGEOFNAME created today ===\n";
$rows = $db->select("SELECT TOP 20 id, mlsFNo, fileno, temp_fileno, instrument_type, transaction_type, prop_id, parent_prop_id, source_op_id, Grantor, Grantee, created_at FROM pra WHERE system_source = 'OSSOPCHANGEOFNAME' AND CAST(created_at AS DATE) = CAST(GETDATE() AS DATE) ORDER BY id DESC");
foreach ($rows as $r) { echo json_encode($r) . "\n"; }

echo "\n=== Search RES-2026-2088 across pra/fileNumber/mls_file_no/instrument_capture ===\n";
echo "pra by mlsFNo/fileno: ";
echo json_encode($db->select("SELECT id, mlsFNo, fileno, temp_fileno, system_source, instrument_type FROM pra WHERE mlsFNo='RES-2026-2088' OR fileno='RES-2026-2088'")) . "\n";
echo "fileNumber: ";
echo json_encode($db->select("SELECT id, mlsfNo, FileName, temp_fileno, SOURCE, is_deleted FROM fileNumber WHERE mlsfNo='RES-2026-2088'")) . "\n";
echo "mls_file_no: ";
echo json_encode($db->select("SELECT id, full_file_number, source, source_pra_id, source_instrument_capture_id, customer_type FROM mls_file_no WHERE full_file_number='RES-2026-2088'")) . "\n";
echo "instrument_capture: ";
echo json_encode($db->select("SELECT TOP 5 id, mlsFNo, temp_fileno, instrument_type, prop_id, op_serial_number FROM instrument_capture WHERE mlsFNo='RES-2026-2088' OR op_serial_number='65'")) . "\n";
