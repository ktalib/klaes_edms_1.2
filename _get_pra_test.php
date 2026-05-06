<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = \DB::connection('sqlsrv');
$rows = $db->select("SELECT TOP 20 id, instrument_type, temp_fileno, mlsFNo, Grantor, Grantee, party_1, party_2, created_at, created_by 
FROM pra 
WHERE (temp_fileno = 'TEMP-04080' OR temp_fileno = 'TEMP-33977' OR temp_fileno = 'TEMP-33765') 
AND system_source = 'OSSOPCHANGEOFNAME' 
ORDER BY created_at DESC");
foreach ($rows as $r) {
    print_r($r);
}
