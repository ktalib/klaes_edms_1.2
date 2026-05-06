<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$cols = DB::connection('sqlsrv')->select("
    SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME='pic' 
    AND COLUMN_NAME IN ('party_1','party_2','Grantor','Grantee','Assignor','Assignee','Mortgagor','Mortgagee','Surrenderor','Surrenderee','Lessor','Lessee','Releasor','Releasee','Donor','Donee','Vendor','Purchaser')
    ORDER BY COLUMN_NAME
");

foreach ($cols as $c) {
    echo str_pad($c->COLUMN_NAME, 15) . ": " . $c->DATA_TYPE . "(" . $c->CHARACTER_MAXIMUM_LENGTH . ")\n";
}
