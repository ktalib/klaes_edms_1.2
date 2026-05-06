<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = app(App\Services\BulkSmsNigeriaService::class);
$msg = 'Test SMS from KANGIS new_kangis tracker - BulkSMS Nigeria fallback. ' . date('Y-m-d H:i');
$result = $service->send('07017467902', $msg);
echo 'Sent: ' . ($result ? 'YES' : 'NO') . PHP_EOL;
