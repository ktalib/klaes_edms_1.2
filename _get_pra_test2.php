<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = \DB::connection('sqlsrv');
foreach ($db->select("SELECT id, instrument_type, temp_fileno, mlsFNo, Grantor, Grantee, party_1, party_2, prop_id FROM pra WHERE temp_fileno='TEMP-04080'") as $r) { print_r($r); }
foreach ($db->select("SELECT id, instrument_type, temp_fileno, mlsFNo, party_1_name, prop_id FROM instrument_capture WHERE temp_fileno='TEMP-04080'") as $r) { print_r($r); }
