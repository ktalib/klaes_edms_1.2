<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Simulate what resolveGeneralRegistryValue does now
function resolveGeneralRegistryValue(?string $registryCode): ?string
{
    if ($registryCode === null || trim($registryCode) === '') return null;
    $code = trim($registryCode);
    if (str_ends_with($code, ' Registry') || str_ends_with($code, ' registry')) return $code;
    if (strtoupper($code) === 'LANDS') return 'Lands Registry';

    $row = DB::connection('sqlsrv')->table('registries')->where('code', $code)->select('name')->first();
    if ($row && !empty($row->name)) {
        $name = trim($row->name);
        if (str_ends_with($name, ' Registry') || str_ends_with($name, ' registry')) return $name;
        return $name . ' Registry';
    }
    return $code . ' Registry';
}

$resolved = resolveGeneralRegistryValue('KANGIS');
echo "Resolved: '{$resolved}'\n";

$count = DB::connection('sqlsrv')->table('file_indexings')
    ->where('general_registry', $resolved)
    ->where(function ($q) {
        $q->whereNull('shelf_location')->orWhere('shelf_location', '');
    })
    ->count();
echo "Unassigned records for '{$resolved}': {$count}\n";
