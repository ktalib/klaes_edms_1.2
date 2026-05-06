<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$sql = "SELECT id, prop_id, mlsFNo, temp_fileno FROM file_history_staging WHERE mlsFNo = 'RES-2000-4828'";
$data = Illuminate\Support\Facades\DB::connection('sqlsrv')->select($sql);
echo "file_history_staging:\n";
echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
