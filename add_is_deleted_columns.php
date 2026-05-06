<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$conn = \DB::connection('sqlsrv');
$tables = ['file_history_staging', 'CofO_staging', 'pra', 'deed_registrations'];

foreach ($tables as $table) {
    try {
        $hasCol = $conn->select(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = 'is_deleted'",
            [$table]
        );
        if (empty($hasCol)) {
            $conn->statement("ALTER TABLE [{$table}] ADD is_deleted BIT DEFAULT 0");
            echo "Added is_deleted to {$table}\n";
        } else {
            echo "is_deleted already exists on {$table}\n";
        }
    } catch (\Exception $e) {
        echo "Error on {$table}: " . $e->getMessage() . "\n";
    }
}
echo "Done.\n";
