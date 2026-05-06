<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$registries = Illuminate\Support\Facades\DB::connection('sqlsrv')->table('registries')->pluck('name')->toArray();
echo "Registries: \n";
print_r($registries);

$recent = App\Models\FileIndexing::orderBy('id', 'desc')->take(10)->get(['id', 'file_number', 'general_registry', 'tracking_id', 'created_at']);
foreach($recent as $r) {
    echo "ID: {$r->id} | FileNo: {$r->file_number} | Registry: {$r->general_registry} | Created: {$r->created_at}\n";
}
