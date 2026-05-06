<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$db = \Illuminate\Support\Facades\DB::connection('sqlsrv');
$ids = [73901,78011,79612,81050,82002,82387,83044,83316,84316,84689,86003,86527,88556];
$coms = ['COM-2026-164','COM-2026-165','COM-2026-166','COM-2026-167','COM-2026-168','COM-2026-169','COM-2026-170','COM-2026-171','COM-2026-172','COM-2026-173','COM-2026-174','COM-2026-175','COM-2026-176'];

echo "=== IC OP rows for today prop_ids ===\n";
$ic = $db->table('instrument_capture')->whereIn('prop_id',$ids)->where('instrument_type','like','%Occupancy Permit%')->select('id','prop_id','instrument_type','party_1_name','party_2_name','temp_fileno')->get();
foreach($ic as $r) echo json_encode($r)."\n";

echo "\n=== mls_file_no for today COMs ===\n";
$mls = $db->table('mls_file_no')->whereIn('full_file_number',$coms)->select('id','full_file_number','source_instrument_capture_id','source_pra_id','source','sub_source')->get();
foreach($mls as $r) echo json_encode($r)."\n";

echo "\n=== IC vs pra ToT collisions ===\n";
$inList = implode(',',$ids);
$cols = $db->select("SELECT ic.id as ic_id,ic.prop_id,ic.temp_fileno as ic_temp,ic.party_1_name,ic.party_2_name,tot.id as pra_tot_id,tot.temp_fileno as tot_temp,tot.Grantor,tot.Grantee,tot.fileno FROM instrument_capture ic JOIN pra tot ON tot.prop_id=ic.prop_id AND tot.instrument_type='Transfer of Title (OP)' AND tot.system_source='OSSOPCHANGEOFNAME' WHERE ic.instrument_type LIKE '%Occupancy Permit%' AND ic.prop_id IN ($inList) ORDER BY ic.prop_id");
foreach($cols as $r) echo json_encode($r)."\n";
