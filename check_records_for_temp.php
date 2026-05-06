<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('sqlsrv');

echo "=== Records needing temp file numbers ===" . PHP_EOL;

// PRA records without any file number
$pra = $db->select("SELECT id, temp_fileno, created_at FROM pra ORDER BY id");
echo PHP_EOL . "pra (" . count($pra) . " total):" . PHP_EOL;
foreach($pra as $r) echo "  id={$r->id}, temp_fileno=" . ($r->temp_fileno ?? 'NULL') . ", created_at={$r->created_at}" . PHP_EOL;

// PIC records
$pic = $db->select("SELECT id, temp_fileno, created_at FROM pic ORDER BY id");
echo PHP_EOL . "pic (" . count($pic) . " total):" . PHP_EOL;
foreach($pic as $r) echo "  id={$r->id}, temp_fileno=" . ($r->temp_fileno ?? 'NULL') . ", created_at={$r->created_at}" . PHP_EOL;

// instrument_capture records
$ic = $db->select("SELECT id, temp_fileno, mlsFNo, kangisFileNo, NewKANGISFileno, instrument_type, created_at FROM instrument_capture WHERE (is_deleted = 0 OR is_deleted IS NULL) ORDER BY id");
echo PHP_EOL . "instrument_capture (" . count($ic) . " total):" . PHP_EOL;
foreach($ic as $r) echo "  id={$r->id}, temp_fileno=" . ($r->temp_fileno ?? 'NULL') . ", mlsFNo=" . ($r->mlsFNo ?? 'NULL') . ", type=" . ($r->instrument_type ?? 'NULL') . ", created_at={$r->created_at}" . PHP_EOL;

// deed_registrations
$dr = $db->select("SELECT id, fileno, instrument_type, instrument_capture_id, created_at FROM deed_registrations ORDER BY id");
echo PHP_EOL . "deed_registrations (" . count($dr) . " total):" . PHP_EOL;
foreach($dr as $r) echo "  id={$r->id}, fileno=" . ($r->fileno ?? 'NULL') . ", type={$r->instrument_type}, ic_id=" . ($r->instrument_capture_id ?? 'NULL') . ", created_at={$r->created_at}" . PHP_EOL;
