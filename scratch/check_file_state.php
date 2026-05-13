<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$fileNo = 'RES-2015-3564';

$results = [];

$results['file_indexings'] = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $fileNo)->get();
$results['pra'] = DB::connection('sqlsrv')->table('pra')->where('mlsFNo', $fileNo)->get();
$results['fileNumber'] = DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $fileNo)->get();
$results['customers_staging'] = DB::connection('sqlsrv')->table('customers_staging')->where('file_number', $fileNo)->get();
$results['entities_staging'] = DB::connection('sqlsrv')->table('entities_staging')->where('file_number', $fileNo)->get();
$results['CofO_staging'] = DB::connection('sqlsrv')->table('CofO_staging')->where('mlsFNo', $fileNo)->get();

echo json_encode($results, JSON_PRETTY_PRINT);
