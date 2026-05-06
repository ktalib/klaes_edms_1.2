<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (['deed_registrations', 'instrument_capture'] as $t) {
    $cols = DB::connection('sqlsrv')->getSchemaBuilder()->getColumnListing($t);
    echo $t . ":\n  " . implode(', ', $cols) . "\n\n";
}
