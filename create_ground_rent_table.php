<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $exists = DB::connection('sqlsrv')->select("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'ls_ground_rent_staging'");
    if (!empty($exists)) {
        echo "Table already exists.\n";
        exit;
    }
    DB::connection('sqlsrv')->statement("
        CREATE TABLE ls_ground_rent_staging (
            id BIGINT IDENTITY(1,1) PRIMARY KEY,
            prop_id NVARCHAR(50) NULL,
            file_number NVARCHAR(100) NULL,
            comment NVARCHAR(MAX) NULL,
            amount DECIMAL(18,2) NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )
    ");
    DB::connection('sqlsrv')->statement("CREATE INDEX idx_ls_grs_prop_id ON ls_ground_rent_staging (prop_id)");
    DB::connection('sqlsrv')->statement("CREATE INDEX idx_ls_grs_file_number ON ls_ground_rent_staging (file_number)");
    echo "Table created successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
