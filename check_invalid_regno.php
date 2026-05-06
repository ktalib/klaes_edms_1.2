<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$sql = "
SELECT id, regNo, mlsFNo, instrument_type, party_1, party_2
FROM pra
WHERE regNo IS NOT NULL
  AND regNo LIKE '%/%/%'
  AND (
    TRY_CAST(PARSENAME(REPLACE(regNo, '/', '.'), 3) AS INT) > 300
    OR TRY_CAST(PARSENAME(REPLACE(regNo, '/', '.'), 2) AS INT) > 300
    OR TRY_CAST(PARSENAME(REPLACE(regNo, '/', '.'), 1) AS INT) > 300
  )
ORDER BY id DESC
";

$rows = DB::connection('sqlsrv')->select($sql);

echo "Total invalid regNo records: " . count($rows) . "\n";
echo str_repeat('-', 140) . "\n";
echo sprintf("%-8s | %-18s | %-18s | %-30s | %-30s | %-30s\n", 'ID', 'regNo', 'mlsFNo', 'instrument_type', 'party_1', 'party_2');
echo str_repeat('-', 140) . "\n";

foreach ($rows as $r) {
    echo sprintf("%-8s | %-18s | %-18s | %-30s | %-30s | %-30s\n",
        $r->id,
        $r->regNo,
        $r->mlsFNo ?? 'NULL',
        substr($r->instrument_type ?? 'NULL', 0, 30),
        substr($r->party_1 ?? 'NULL', 0, 30),
        substr($r->party_2 ?? 'NULL', 0, 30)
    );
}
