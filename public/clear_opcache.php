<?php
/**
 * OPcache Clear Script - DELETE THIS FILE AFTER USE!
 * 
 * Access: http://localhost/clear_opcache.php
 */

header('Content-Type: text/plain; charset=utf-8');

echo "==============================================\n";
echo "CLEARING ALL CACHES\n";
echo "==============================================\n\n";

// Clear OPcache
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "✅ OPcache cleared successfully!\n";
    } else {
        echo "❌ Failed to clear OPcache\n";
    }
} else {
    echo "⚠️  OPcache extension is not enabled.\n";
}

// Clear Laravel caches
echo "\n--- Clearing Laravel Caches ---\n";
$output = [];
$return_var = 0;

chdir(__DIR__ . '/..');
exec('php artisan optimize:clear 2>&1', $output, $return_var);

foreach ($output as $line) {
    echo $line . "\n";
}

echo "\n==============================================\n";
if ($return_var === 0) {
    echo "✅ SUCCESS! All caches cleared.\n";
    echo "==============================================\n\n";
    echo "NEXT STEPS:\n";
    echo "1. Close this tab\n";
    echo "2. Refresh your application\n";
    echo "3. DELETE THIS FILE for security!\n";
} else {
    echo "⚠️  Some issues occurred.\n";
    echo "==============================================\n";
}

echo "\nTimestamp: " . date('Y-m-d H:i:s') . "\n";

