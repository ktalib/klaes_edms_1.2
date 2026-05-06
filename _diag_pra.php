<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$rows = DB::connection('sqlsrv')->select("
    SELECT p.id, p.prop_id, p.instrument_type, p.system_source, p.temp_fileno, p.Grantor, p.Grantee
    FROM dbo.pra p
    WHERE p.prop_id IN (73901,78011,79612,81050,82002,82387,83044,83316,84316,84689,86003,86527,88556)
    ORDER BY p.prop_id, p.id
");
foreach($rows as $r) {
    echo $r->id . ' | ' . $r->prop_id . ' | ' . $r->instrument_type . ' | ' . $r->system_source . ' | ' . $r->temp_fileno . PHP_EOL;
}
