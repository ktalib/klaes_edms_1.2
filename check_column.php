<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$exists = Schema::connection('sqlsrv')->hasColumn('subapplications', 'improvement_value');
echo $exists ? 'COLUMN_EXISTS' : 'COLUMN_MISSING';
