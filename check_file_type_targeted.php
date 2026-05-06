<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$fileNumber = 'CON/2021/0001'; // Just a guess for testing

try {
    $results = DB::connection('sqlsrv')->select("SELECT TOP 1 file_number, file_type FROM file_indexings WHERE file_number = ? OR st_fillno = ?", [$fileNumber, $fileNumber]);
    echo json_encode(['success' => true, 'data' => $results]);
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
