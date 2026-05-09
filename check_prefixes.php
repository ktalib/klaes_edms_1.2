<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$prefixes = \App\Models\Prefix::limit(20)->get();
foreach ($prefixes as $p) {
    echo "{$p->prefix} ({$p->land_use_id})\n";
}
