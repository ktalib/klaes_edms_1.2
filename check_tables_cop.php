<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$oldFile = 'CON-AG-2020-140';
$newFile = 'CON-COM-2026-227';

echo "\n--- COLUMNS IN file_indexings ---\n";
print_r(Schema::connection('sqlsrv')->getColumnListing('file_indexings'));

echo "\n--- COLUMNS IN pra ---\n";
print_r(Schema::connection('sqlsrv')->getColumnListing('pra'));

echo "\n--- Old File ($oldFile) status ---\n";
$oldFn = DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $oldFile)->first();
echo "fileNumber: " . ($oldFn ? 'Exists' : 'Not Found') . "\n";

$oldIdx = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $oldFile)->first();
echo "file_indexings: " . ($oldIdx ? 'Exists' : 'Not Found') . "\n";

echo "\n--- New File ($newFile) status ---\n";
$newFn = DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $newFile)->first();
echo "fileNumber: " . ($newFn ? 'Exists' : 'Not Found') . "\n";

$newIdx = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $newFile)->first();
echo "file_indexings: " . ($newIdx ? 'Exists' : 'Not Found') . "\n";
