<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $exists = DB::connection('sqlsrv')->select("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'ls_ground_rent_staging'");
    if (empty($exists)) {
        echo "ls_ground_rent_staging does not exist. Checking ls_comment_staging...\n";
        $exists2 = DB::connection('sqlsrv')->select("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'ls_comment_staging'");
        echo !empty($exists2) ? "ls_comment_staging already exists.\n" : "Neither table exists.\n";
        exit;
    }
    // Add comment_type column before rename
    DB::connection('sqlsrv')->statement("ALTER TABLE ls_ground_rent_staging ADD comment_type NVARCHAR(50) NULL DEFAULT 'ground_rent'");
    DB::connection('sqlsrv')->statement("EXEC sp_rename 'ls_ground_rent_staging', 'ls_comment_staging'");
    echo "Table renamed successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
