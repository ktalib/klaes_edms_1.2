<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $rows = DB::connection('sqlsrv')->table('Rack_Shelf_Labels')->whereNotNull('assigned')->take(5)->get();
    foreach ($rows as $row) {
        echo "Label: {$row->full_label}, Counter: {$row->counter}, Assigned: {$row->assigned}\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
