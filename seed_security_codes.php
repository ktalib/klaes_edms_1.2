<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

foreach(range(1, 50) as $n) {
    DB::table('security_paper_codes')->insert([
        'paper_code' => str_pad($n, 4, '0', STR_PAD_LEFT),
        'created_at' => now(),
        'updated_at' => now()
    ]);
}
echo "Seeded 50 security paper codes.\n";
