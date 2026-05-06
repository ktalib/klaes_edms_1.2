<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

print_r(config('auth.guards'));
echo "default sanctum guard=" . json_encode(config('sanctum.guard')) . "\n";
echo "PAT connection: " . (new \App\Models\PersonalAccessToken)->getConnectionName() . "\n";
echo "PAT count=" . \App\Models\PersonalAccessToken::count() . "\n";
echo "max id=" . \App\Models\PersonalAccessToken::max('id') . "\n";
$last = \App\Models\PersonalAccessToken::orderByDesc('id')->limit(5)->get(['id','tokenable_type','tokenable_id','name','created_at']);
foreach($last as $r){ echo "row: ".json_encode($r)."\n"; }
