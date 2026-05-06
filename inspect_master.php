<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');

// First, check the PropID_Master table structure
$cols = $db->select("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'PropID_Master'");
echo "PropID_Master columns:\n";
foreach ($cols as $col) {
    echo "  {$col->COLUMN_NAME} ({$col->DATA_TYPE})\n";
}

// Count existing entries
$existingCount = $db->table('PropID_Master')->count();
echo "\nExisting PropID_Master entries: $existingCount\n";

// Count distinct prop_ids in instrument_capture
$icCount = $db->table('instrument_capture')
    ->whereNotNull('prop_id')
    ->where('prop_id', '>', 0)
    ->where('prop_id', '<', 2147483647)
    ->distinct()
    ->count('prop_id');
echo "Distinct prop_ids in instrument_capture: $icCount\n";

// Count distinct prop_ids in pra
$praCount = $db->table('pra')
    ->whereNotNull('prop_id')
    ->where('prop_id', '>', 0)
    ->where('prop_id', '<', 2147483647)
    ->distinct()
    ->count('prop_id');
echo "Distinct prop_ids in pra: $praCount\n";
