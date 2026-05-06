<?php
define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = app('db');

// Check sqlsrv
try {
    $has = $db->connection('sqlsrv')->getSchemaBuilder()->hasTable('personal_access_tokens');
    echo "sqlsrv has personal_access_tokens: " . ($has ? 'YES' : 'NO') . PHP_EOL;
} catch (Exception $e) {
    echo "sqlsrv error: " . $e->getMessage() . PHP_EOL;
}

// Check mysql
try {
    $cnt = $db->connection('mysql')->table('personal_access_tokens')->count();
    echo "mysql personal_access_tokens count: " . $cnt . PHP_EOL;
    // Show last few tokens
    $tokens = $db->connection('mysql')->table('personal_access_tokens')->latest()->take(3)->get(['id','tokenable_type','tokenable_id','name','created_at']);
    foreach ($tokens as $t) {
        echo "  token id={$t->id} type={$t->tokenable_type} user_id={$t->tokenable_id} name={$t->name} created={$t->created_at}" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "mysql error: " . $e->getMessage() . PHP_EOL;
}

// Check default connection
echo "Default DB connection: " . config('database.default') . PHP_EOL;

// Check if Sanctum has a custom connection
echo "Sanctum uses guard: " . json_encode(config('sanctum.guard')) . PHP_EOL;
