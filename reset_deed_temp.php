<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection('sqlsrv');

echo "=== Clearing TEMP fileno from deed_registrations ===" . PHP_EOL;

$updated = $db->table('deed_registrations')
    ->where('fileno', 'like', 'TEMP%')
    ->update(['fileno' => null]);

echo "deed_registrations: cleared fileno on {$updated} TEMP record(s)" . PHP_EOL;

// Verify
$remaining = $db->table('deed_registrations')->where('fileno', 'like', 'TEMP%')->count();
echo "Remaining TEMP records in deed_registrations: {$remaining}" . PHP_EOL;

echo PHP_EOL . "Done." . PHP_EOL;
