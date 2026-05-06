<?php
/**
 * Permanent Fix: Backfill PropID_Master with all existing prop_ids from
 * instrument_capture and pra, so the ID allocator never reuses a taken ID.
 *
 * - Inserts missing prop_ids in batches of 500
 * - Skips IDs already in PropID_Master
 * - Skips junk/non-numeric IDs in pra (e.g. 'Instrument Type')
 * - Safe to run multiple times (idempotent)
 */
require 'vendor/autoload.php';
require 'bootstrap/app.php';
app()->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$db = DB::connection('sqlsrv');
$now = now()->toDateTimeString();
$inserted = 0;
$skipped  = 0;

// Load all already-registered prop_ids into memory for fast lookup
echo "Loading existing PropID_Master entries...\n";
$existing = $db->table('PropID_Master')
    ->whereNotNull('prop_id')
    ->pluck('prop_id')
    ->flip()  // flip to use as hashmap: [prop_id => index]
    ->all();
echo "  Found: " . count($existing) . " existing entries\n";

// ---- SOURCE 1: instrument_capture ----
echo "\nProcessing instrument_capture...\n";
$db->table('instrument_capture')
    ->whereNotNull('prop_id')
    ->whereRaw("ISNUMERIC(prop_id) = 1")
    ->whereRaw("TRY_CAST(prop_id AS bigint) > 0")
    ->whereRaw("TRY_CAST(prop_id AS bigint) < 2147483647")
    ->orderBy('prop_id')
    ->select(['prop_id', 'mlsFNo', 'temp_fileno', 'id'])
    ->chunk(100, function ($rows) use ($db, &$existing, &$inserted, &$skipped, $now) {
        $toInsert = [];
        foreach ($rows as $row) {
            $pid = (string) $row->prop_id;
            if (isset($existing[$pid])) {
                $skipped++;
                continue;
            }
            $existing[$pid] = 1;
            $toInsert[] = [
                'prop_id'              => (int) $pid,
                'primary_file_number'  => 'BACKFILL-' . $pid,
                'mlsFNo'               => $row->mlsFNo,
                'temp_fileno'          => $row->temp_fileno,
                'source_table'         => 'instrument_capture',
                'source_record_id'     => (int) $row->id,
                'status'               => 'active',
                'notes'                => 'backfill',
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }
        if (!empty($toInsert)) {
            $db->table('PropID_Master')->insert($toInsert);
            $inserted += count($toInsert);
            echo "  Inserted batch: " . count($toInsert) . " | Total inserted: $inserted\n";
        }
    });

// ---- SOURCE 2: pra (numeric prop_ids only) ----
echo "\nProcessing pra...\n";
$db->table('pra')
    ->whereNotNull('prop_id')
    ->whereRaw("ISNUMERIC(prop_id) = 1")
    ->whereRaw("TRY_CAST(prop_id AS bigint) > 0")
    ->whereRaw("TRY_CAST(prop_id AS bigint) < 2147483647")
    ->where('is_deleted', '!=', 1)
    ->orderBy('prop_id')
    ->select(['prop_id', 'mlsFNo', 'temp_fileno', 'id'])
    ->chunk(100, function ($rows) use ($db, &$existing, &$inserted, &$skipped, $now) {
        $toInsert = [];
        foreach ($rows as $row) {
            $pid = (string) $row->prop_id;
            if (isset($existing[$pid])) {
                $skipped++;
                continue;
            }
            $existing[$pid] = 1;
            $toInsert[] = [
                'prop_id'             => (int) $pid,
                'primary_file_number' => 'BACKFILL-' . $pid,
                'mlsFNo'              => $row->mlsFNo,
                'temp_fileno'         => $row->temp_fileno,
                'source_table'        => 'pra',
                'source_record_id'    => (int) $row->id,
                'status'              => 'active',
                'notes'               => 'backfill',
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }
        if (!empty($toInsert)) {
            $db->table('PropID_Master')->insert($toInsert);
            $inserted += count($toInsert);
            echo "  Inserted batch: " . count($toInsert) . " | Total inserted: $inserted\n";
        }
    });

$finalCount = $db->table('PropID_Master')->count();

echo "\n=== BACKFILL COMPLETE ===\n";
echo "Newly inserted: $inserted\n";
echo "Already existed (skipped): $skipped\n";
echo "Total PropID_Master entries now: $finalCount\n";
echo "The allocator will no longer reuse any of these IDs.\n";
