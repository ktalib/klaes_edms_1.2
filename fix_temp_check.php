<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Get column list first
$cols = Illuminate\Support\Facades\Schema::connection('sqlsrv')->getColumnListing('instrument_capture');
echo "Columns: " . implode(', ', $cols) . "\n\n";

// Check pra table - latest OP records
$rows = DB::connection('sqlsrv')
    ->select("SELECT TOP 10 id, prop_id, temp_fileno, mlsFNo, transaction_type, instrument_type, Grantor, Grantee, party_1, party_2, op_serial_number FROM [pra] ORDER BY id DESC");

echo "Latest PRA records:\n";
foreach ($rows as $r) {
    echo sprintf(
        "id=%s | prop_id=%s | temp=%s | mls=%s | Grantor=%s | Grantee=%s | p1=%s | p2=%s\n",
        $r->id,
        $r->prop_id ?? 'NULL',
        $r->temp_fileno ?? 'NULL',
        $r->mlsFNo ?? 'NULL',
        $r->Grantor ?? 'NULL',
        $r->Grantee ?? 'NULL',
        $r->party_1 ?? 'NULL',
        $r->party_2 ?? 'NULL'
    );
}

foreach ($rows as $r) {
    echo sprintf(
        "id=%s | prop_id=%s | temp_fileno=%s | op_serial=%s | party_2=%s | created=%s\n",
        $r->id,
        $r->prop_id ?? 'NULL',
        $r->temp_fileno ?? 'NULL',
        $r->op_serial_number ?? '',
        $r->party_2_name ?? '',
        $r->created_at ?? ''
    );
}
