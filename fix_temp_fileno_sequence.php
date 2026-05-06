<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$db = DB::connection('sqlsrv');
// $db = db::connection'O
echo "=== FIX 1: Clear TEMP- values from mlsFNo in pra ===\n";
$affected = $db->table('pra')
    ->where('mlsFNo', 'like', 'TEMP-%')
    ->update(['mlsFNo' => null]);
echo "Cleared TEMP- from pra.mlsFNo: $affected rows\n";

// Also check pic and instrument_capture mlsFNo
$affected2 = $db->table('pic')
    ->where('mlsFNo', 'like', 'TEMP-%')
    ->update(['mlsFNo' => null]);
echo "Cleared TEMP- from pic.mlsFNo: $affected2 rows\n";

if (\Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'mlsFNo')) {
    $affected3 = $db->table('instrument_capture')
        ->where('mlsFNo', 'like', 'TEMP-%')
        ->update(['mlsFNo' => null]);
    echo "Cleared TEMP- from instrument_capture.mlsFNo: $affected3 rows\n";
}

echo "\n=== FIX 2: Reseed temp_fileno_sequence identity ===\n";
// Find max TEMP number currently in use across all tables (temp_fileno column only)
$maxNum = 0;
foreach (['pra', 'pic', 'instrument_capture'] as $table) {
    if (!\Schema::connection('sqlsrv')->hasColumn($table, 'temp_fileno')) continue;
    $max = $db->table($table)
        ->where('temp_fileno', 'like', 'TEMP-%')
        ->selectRaw("MAX(TRY_CONVERT(INT, REPLACE(CAST(temp_fileno AS VARCHAR), 'TEMP-', ''))) as max_num")
        ->first();
    if ($max && $max->max_num) {
        echo "  Max TEMP in {$table}.temp_fileno: {$max->max_num}\n";
        $maxNum = max($maxNum, (int)$max->max_num);
    }
}
echo "Overall max TEMP number in use: $maxNum\n";

// Reseed identity to max number so next insert gets maxNum+1
$db->statement("DBCC CHECKIDENT ('temp_fileno_sequence', RESEED, $maxNum)");
echo "Reseeded temp_fileno_sequence identity to $maxNum\n";
echo "Next generated TEMP will be: TEMP-" . str_pad($maxNum + 1, 5, '0', STR_PAD_LEFT) . "\n";

// Verify
$ident = $db->select("SELECT IDENT_CURRENT('temp_fileno_sequence') as current_identity");
echo "\nVerification - current identity: " . $ident[0]->current_identity . "\n";
