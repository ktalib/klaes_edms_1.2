<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('sqlsrv');

$seq = $db->table('temp_fileno_sequence')->count();
$praT = $db->table('pra')->where('temp_fileno', 'like', 'TEMP-%')->count();
$picT = $db->table('pic')->where('temp_fileno', 'like', 'TEMP-%')->count();
$icT = $db->table('instrument_capture')->where('temp_fileno', 'like', 'TEMP-%')->count();

echo "VERIFY: seq={$seq}, pra_temp={$praT}, pic_temp={$picT}, ic_temp={$icT}\n";
