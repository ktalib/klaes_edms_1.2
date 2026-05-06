<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
echo Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'op_serial_number') ? 'EXISTS' : 'MISSING';
