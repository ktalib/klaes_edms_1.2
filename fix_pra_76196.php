<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$id = 76196;
$newPropId = '72072';
$newTemp = 'TEMP-11781';

echo "=== BEFORE update ===\n";
$before = DB::connection('sqlsrv')->table('pra')->where('id', $id)->first(['id','prop_id','temp_fileno','party_1','party_2']);
echo "pra.id={$before->id} | prop_id={$before->prop_id} | temp={$before->temp_fileno} | p1={$before->party_1} | p2={$before->party_2}\n";

// Update
$affected = DB::connection('sqlsrv')->table('pra')
    ->where('id', $id)
    ->update([
        'prop_id'     => $newPropId,
        'temp_fileno' => $newTemp,
    ]);

echo "\nRows updated: {$affected}\n";

echo "\n=== AFTER update ===\n";
$after = DB::connection('sqlsrv')->table('pra')->where('id', $id)->first(['id','prop_id','temp_fileno','party_1','party_2']);
echo "pra.id={$after->id} | prop_id={$after->prop_id} | temp={$after->temp_fileno} | p1={$after->party_1} | p2={$after->party_2}\n";

echo "\n=== Verify prop_id 68923 is now clean ===\n";
$check = DB::connection('sqlsrv')->table('pra')->where('prop_id', '68923')->orderBy('id')->get(['id','prop_id','temp_fileno','party_2']);
foreach ($check as $r) {
    echo "pra.id={$r->id} | prop_id={$r->prop_id} | temp={$r->temp_fileno} | p2={$r->party_2}\n";
}
