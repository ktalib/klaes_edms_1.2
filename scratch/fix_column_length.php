<?php
use Illuminate\Support\Facades\DB;

require dirname(__DIR__) . '/vendor/autoload.php';
$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Altering compensated_items...\n";
    DB::connection('sqlsrv')->statement("ALTER TABLE valuation_compensations ALTER COLUMN compensated_items NVARCHAR(MAX)");
    
    echo "Altering compensated_items_other...\n";
    DB::connection('sqlsrv')->statement("ALTER TABLE valuation_compensations ALTER COLUMN compensated_items_other NVARCHAR(MAX)");
    
    echo "Success!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
