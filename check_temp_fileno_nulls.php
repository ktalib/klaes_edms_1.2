<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$conn = DB::connection('sqlsrv');

// Find OP records with NULL temp_fileno
$rows = $conn->select("
    SELECT id, temp_fileno, mlsFNo, kangisFileNo, NewKANGISFileno, 
           op_serial_number, op_type, party_2_name, prop_id, created_at
    FROM instrument_capture
    WHERE instrument_type = 'Occupancy Permit (OP)'
      AND (temp_fileno IS NULL OR LTRIM(RTRIM(temp_fileno)) = '')
    ORDER BY id ASC
");

echo "Found " . count($rows) . " OP records with NULL temp_fileno\n\n";

if (count($rows) === 0) {
    echo "Nothing to backfill.\n";
    exit(0);
}

// Get PropertyIdAllocationService
$propIdService = app(\App\Services\PropertyIdAllocationService::class);

foreach ($rows as $r) {
    echo "Processing ic.id={$r->id}  party2={$r->party_2_name}  opSerial={$r->op_serial_number}\n";

    // Step 1: Generate next temp_fileno from sequence table
    $seqId = $conn->table('temp_fileno_sequence')->insertGetId([
        'created_by' => null,
        'is_used' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $tempFileno = 'TEMP-' . str_pad((string) $seqId, 5, '0', STR_PAD_LEFT);
    echo "  Generated temp_fileno: {$tempFileno}\n";

    // Step 2: Allocate prop_id using temp_fileno
    $propId = null;
    try {
        $propId = $propIdService->allocateOrRetrievePropId(
            $tempFileno,   // primary
            null,          // mlsFNo
            null,          // kangisFileNo
            null,          // newKangisFileNo
            [
                'temp_fileno' => $tempFileno,
                'allow_temp_only' => true,
            ]
        );
        echo "  Allocated prop_id: {$propId}\n";
    } catch (\Exception $e) {
        echo "  WARNING: prop_id allocation failed: {$e->getMessage()}\n";
    }

    // Step 3: Update the instrument_capture record
    $updateData = ['temp_fileno' => $tempFileno];
    if ($propId) {
        $updateData['prop_id'] = $propId;
    }

    $conn->table('instrument_capture')
        ->where('id', $r->id)
        ->update($updateData);

    echo "  Updated ic.id={$r->id} with temp_fileno={$tempFileno}" 
        . ($propId ? " prop_id={$propId}" : '') . "\n\n";
}

echo "Backfill complete.\n";

// Verify
echo "\n--- Verification ---\n";
$check = $conn->selectOne("
    SELECT COUNT(*) as remaining
    FROM instrument_capture
    WHERE instrument_type = 'Occupancy Permit (OP)'
      AND (temp_fileno IS NULL OR LTRIM(RTRIM(temp_fileno)) = '')
");
echo "Remaining OP records with NULL temp_fileno: {$check->remaining}\n";

// deed_registrations - check available columns first
$drCols = $conn->select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'deed_registrations' AND COLUMN_NAME LIKE '%file%' ORDER BY COLUMN_NAME");
echo "deed_registrations file-related columns: ";
foreach ($drCols as $c) echo $c->COLUMN_NAME . ", ";
echo "\n";

$drAllCols = $conn->select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'deed_registrations' AND COLUMN_NAME LIKE '%temp%' ORDER BY COLUMN_NAME");
echo "deed_registrations temp-related columns: ";
foreach ($drAllCols as $c) echo $c->COLUMN_NAME . ", ";
echo "\n";

// Sample instrument_capture rows with NULL temp_fileno
echo "\n--- instrument_capture columns ---\n";
$icCols = $conn->select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'instrument_capture' AND (COLUMN_NAME LIKE '%file%' OR COLUMN_NAME LIKE '%temp%' OR COLUMN_NAME LIKE '%mls%' OR COLUMN_NAME LIKE '%kangis%') ORDER BY COLUMN_NAME");
foreach ($icCols as $c) echo $c->COLUMN_NAME . ", ";
echo "\n";

echo "\n--- instrument_capture samples with NULL temp_fileno ---\n";
$samples = $conn->select("
    SELECT TOP 10 id, temp_fileno, 
           instrument_type, party_2_name, created_at
    FROM instrument_capture
    WHERE temp_fileno IS NULL OR LTRIM(RTRIM(temp_fileno)) = ''
    ORDER BY id DESC
");
foreach ($samples as $r) {
    echo "id={$r->id}"
        . "  type=" . ($r->instrument_type ?? 'NULL')
        . "  party2=" . ($r->party_2_name ?? 'NULL')
        . "  created=" . ($r->created_at ?? 'NULL') . "\n";
}

// Sample deed_registrations rows - check what columns exist
echo "\n--- deed_registrations: all columns ---\n";
$drAllCols2 = $conn->select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'deed_registrations' ORDER BY ORDINAL_POSITION");
foreach ($drAllCols2 as $c) echo $c->COLUMN_NAME . ", ";
echo "\n";

$drSamples = $conn->select("
    SELECT TOP 5 id, fileno, instrument_type, grantor, grantee, created_at
    FROM deed_registrations
    ORDER BY id DESC
");
foreach ($drSamples as $r) {
    echo "id={$r->id}  fileno=" . ($r->fileno ?? 'NULL')
        . "  type=" . ($r->instrument_type ?? 'NULL')
        . "  grantor=" . ($r->grantor ?? 'NULL')
        . "  grantee=" . ($r->grantee ?? 'NULL')
        . "  created=" . ($r->created_at ?? 'NULL') . "\n";
}
