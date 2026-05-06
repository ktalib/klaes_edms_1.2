<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('sqlsrv');

echo "=== deed_registrations columns ===" . PHP_EOL;
$cols = $db->select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'deed_registrations' ORDER BY ORDINAL_POSITION");
foreach($cols as $c) echo "  " . $c->COLUMN_NAME . PHP_EOL;

echo PHP_EOL . "=== instrument_capture columns ===" . PHP_EOL;
$cols2 = $db->select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'instrument_capture' ORDER BY ORDINAL_POSITION");
foreach($cols2 as $c) echo "  " . $c->COLUMN_NAME . PHP_EOL;

echo PHP_EOL . "=== deed_registrations with TEMP fileno ===" . PHP_EOL;
$tempRecs = $db->table('deed_registrations')->where('fileno', 'like', 'TEMP%')->get();
echo "Count: " . $tempRecs->count() . PHP_EOL;
foreach($tempRecs as $r) {
    echo "  id={$r->id}, fileno={$r->fileno}, instrument_type={$r->instrument_type}, instrument_capture_id=" . ($r->instrument_capture_id ?? 'NULL') . PHP_EOL;
}

echo PHP_EOL . "=== instrument_capture records ===" . PHP_EOL;
$icRecs = $db->table('instrument_capture')
    ->where(function($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
    ->get();
echo "Count: " . $icRecs->count() . PHP_EOL;
foreach($icRecs as $r) {
    echo "  id={$r->id}, mlsFNo=" . ($r->mlsFNo ?? 'NULL') 
         . ", kangisFileNo=" . ($r->kangisFileNo ?? 'NULL')
         . ", NewKANGISFileno=" . ($r->NewKANGISFileno ?? 'NULL')
         . ", temp_fileno=" . ($r->temp_fileno ?? 'NULL')
         . ", instrument_type=" . ($r->instrument_type ?? 'NULL')
         . PHP_EOL;
}
