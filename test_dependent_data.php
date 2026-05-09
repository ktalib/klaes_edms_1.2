<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Purpose;
use App\Models\Prefix;

$landUseId = 1;
$purposes = Purpose::where('landuseid', $landUseId)->get(['id', 'name', 'code']);
$prefixes = Prefix::where('land_use_id', $landUseId)->get(['id', 'prefix']);

echo json_encode([
    'success' => true,
    'purposes' => $purposes,
    'prefixes' => $prefixes
], JSON_PRETTY_PRINT);
