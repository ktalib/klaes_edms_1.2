<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app = require_once dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

try {
    Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
        $table->boolean('printed')->default(0)->after('status');
    });
    echo "DONE\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
