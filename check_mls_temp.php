<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$db = DB::connection('sqlsrv');

$count = $db->table('pra')->where('mlsFNo', 'like', 'TEMP-%')->count();
echo "pra rows with TEMP- in mlsFNo: $count\n";

$samples = $db->table('pra')->where('mlsFNo', 'like', 'TEMP-%')->take(10)->get(['id','mlsFNo','temp_fileno','kangisFileNo']);
foreach($samples as $r) {
    echo "  id={$r->id} mlsFNo={$r->mlsFNo} temp_fileno=" . ($r->temp_fileno ?? 'NULL') . " kangis=" . ($r->kangisFileNo ?? 'NULL') . "\n";
}
