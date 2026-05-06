<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$deleted = DB::connection('sqlsrv')->table('file_indexings')->where('id', 47445)->delete();
echo "Deleted orphan row id=47445: {$deleted} row(s)\n";
