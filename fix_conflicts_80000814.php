<?php
/**
 * Fix Prop ID Conflict for 80000814
 * This script separates the conflicting TOTs and OP into unique prop_id values.
 */
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');

$conflicts = [
    ['tot_id' => 139666, 'mlsFNo' => 'COM-2025-757', 'temp_fileno' => 'TEMP-36615'],
    ['tot_id' => 139667, 'mlsFNo' => 'COM-2025-756', 'temp_fileno' => 'TEMP-36619'],
];

$startId = (int) $db->table('PropID_Master')->max('prop_id');
if ($startId < 80000000) $startId = 80000850; // Fallback if max is small
else $startId += 10;

$nextId = $startId;

echo "Starting from PropID: " . $startId . "\n";

foreach ($conflicts as $conflict) {
    $newId = $nextId++;
    echo "Processing {$conflict['mlsFNo']} (ID: {$conflict['tot_id']}) -> New PropID: $newId\n";

    // Update the PRA row
    $db->table('pra')->where('id', $conflict['tot_id'])->update([
        'prop_id' => $newId,
        'updated_at' => now()
    ]);

    // Insert into PropID_Master to "reserve" it and provide a primary lookup
    $timestamp = now();
    $db->table('PropID_Master')->insert([
        'prop_id' => $newId,
        'primary_file_number' => $conflict['mlsFNo'],
        'mlsFNo' => $conflict['mlsFNo'],
        'temp_fileno' => $conflict['temp_fileno'],
        'status' => 'active',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
}

echo "Fix applied successfully.\n";
