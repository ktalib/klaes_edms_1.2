<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Find all rows with mlsFNo of these and any rows touched today
echo "All PRA rows mentioning RES-2026-1975 / 1976 (any column):\n";
foreach (\DB::connection('sqlsrv')->select("
    SELECT id, mlsFNo, fileno, kangisFileNo, instrument_type, party_1, party_2, created_at, updated_at, prop_id, parent_prop_id, system_source
    FROM pra 
    WHERE mlsFNo IN (?, ?) OR fileno IN (?, ?)
    ORDER BY id DESC
", ['RES-2026-1975', 'RES-2026-1976', 'RES-2026-1975', 'RES-2026-1976']) as $r) {
    echo "id={$r->id} mls={$r->mlsFNo} fileno={$r->fileno} inst={$r->instrument_type} p1={$r->party_1} p2={$r->party_2} created={$r->created_at} updated={$r->updated_at} prop_id={$r->prop_id} parent_pid={$r->parent_prop_id} sys={$r->system_source}\n";
}

echo "\nAll PRA rows created in last 24h (system_source=OSSOPCHANGEOFNAME):\n";
foreach (\DB::connection('sqlsrv')->select("
    SELECT TOP 30 id, mlsFNo, instrument_type, party_1, party_2, created_at, prop_id, parent_prop_id
    FROM pra 
    WHERE system_source = 'OSSOPCHANGEOFNAME'
      AND created_at >= DATEADD(day, -1, GETDATE())
    ORDER BY id DESC
") as $r) {
    echo "id={$r->id} mls={$r->mlsFNo} inst={$r->instrument_type} p2={$r->party_2} created={$r->created_at} prop_id={$r->prop_id} parent_pid={$r->parent_prop_id}\n";
}
