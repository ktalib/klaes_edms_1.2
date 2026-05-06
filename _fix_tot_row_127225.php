<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Restore row 127225: instrument_type and transaction_type overwritten by the buggy update
$affected = DB::connection('sqlsrv')->table('pra')->where('id', 127225)->update([
    'instrument_type'  => 'Transfer of Title (OP)',
    'transaction_type' => 'Transfer of Title (OP)',
    'tp_no'            => 'TP/3/30',
    'updated_at'       => now(),
]);

echo "Rows restored: {$affected}" . PHP_EOL;
