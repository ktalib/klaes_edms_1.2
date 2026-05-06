<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Migrating file numbers from SOURCE='Commissioned' to 'MLS_Commissioned'...\n";

$query = DB::connection('sqlsrv')
    ->table('fileNumber')
    ->where('SOURCE', 'Commissioned');

$count = $query->count();
echo "Found {$count} records to migrate.\n";

if ($count > 0) {
    $updated = $query->update(['SOURCE' => 'MLS_Commissioned']);
    echo "Successfully migrated {$updated} records.\n";
} else {
    echo "No records needed migration.\n";
}
