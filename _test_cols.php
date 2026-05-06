<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cols = \Illuminate\Support\Facades\Schema::connection('sqlsrv')->getColumnListing('pra');
echo "PRA cols: " . implode(', ', array_intersect($cols, ['serialNo', 'serial_no', 'pageNo', 'page_no', 'volumeNo', 'volume_no', 'regNo', 'registration_number', 'deeds_date', 'reg_date', 'deeds_time', 'reg_time', 'transaction_date', 'created_at'])) . "\n";

$cols2 = \Illuminate\Support\Facades\Schema::connection('sqlsrv')->getColumnListing('instrument_capture');
echo "IC cols: " . implode(', ', array_intersect($cols2, ['serialNo', 'serial_no', 'pageNo', 'page_no', 'volumeNo', 'volume_no', 'regNo', 'registration_number', 'deeds_date', 'reg_date', 'deeds_time', 'reg_time', 'transaction_date', 'instrument_date'])) . "\n";
