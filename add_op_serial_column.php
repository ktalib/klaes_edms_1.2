<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

$tableName = 'instrument_capture';
$columnName = 'op_serial_number';

echo "Checking table '$tableName'...\n";

if (Schema::connection('sqlsrv')->hasTable($tableName)) {
    if (!Schema::connection('sqlsrv')->hasColumn($tableName, $columnName)) {
        echo "Adding column '$columnName' to '$tableName'...\n";

        Schema::connection('sqlsrv')->table($tableName, function (Blueprint $table) use ($columnName) {
            $table->string($columnName)->nullable()->after('temp_fileno')->comment('Serial ID for Occupancy Permit');
        });

        echo "Column added successfully.\n";
    } else {
        echo "Column '$columnName' already exists.\n";
    }
} else {
    echo "Table '$tableName' not found.\n";
}

// Generate raw SQL for production
echo "\n--- RAW SQL FOR PRODUCTION ---\n";
echo "ALTER TABLE [$tableName] ADD [$columnName] NVARCHAR(255) NULL;\n";
