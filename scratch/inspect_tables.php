<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$tables = [
    'change_of_purpose_applications',
    'change_of_name_applications',
    'plot_subdivision_applications',
    'plot_merger_applications',
    'plot_extension_applications'
];

foreach ($tables as $table) {
    $exists = Schema::connection('sqlsrv')->hasTable($table);
    echo "Table '$table': " . ($exists ? "EXISTS" : "DOES NOT EXIST") . "\n";
    if ($exists) {
        $columns = Schema::connection('sqlsrv')->getColumnListing($table);
        echo "Columns: " . implode(', ', $columns) . "\n\n";
    }
}
