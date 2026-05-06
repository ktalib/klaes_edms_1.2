<?php
/**
 * Reassign new unique temp file numbers to the duplicate records 
 * instead of soft-deleting them. Also restores is_deleted to 0.
 * 
 * Run with --dry-run first:   php fix_ic_reassign_temp.php --dry-run
 * Then apply:                  php fix_ic_reassign_temp.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$dryRun = in_array('--dry-run', $argv);
$db = DB::connection('sqlsrv');

echo ($dryRun ? "[DRY RUN] " : "[LIVE] ") . "Reassigning new TEMP numbers to soft-deleted duplicates" . PHP_EOL . PHP_EOL;

// Find the 10 records we soft-deleted (is_deleted = 1 and part of a dup pair)
$softDeleted = $db->select("
    SELECT ic.id, ic.temp_fileno, ic.registration_number, ic.instrument_type, 
           ic.prop_id, ic.party_2_name, ic.op_serial_number
    FROM instrument_capture ic
    WHERE ic.is_deleted = 1
      AND ic.temp_fileno IS NOT NULL
      AND ic.temp_fileno LIKE 'TEMP-%'
      AND EXISTS (
          SELECT 1 FROM instrument_capture ic2
          WHERE ic2.temp_fileno = ic.temp_fileno
            AND ic2.id <> ic.id
            AND (ic2.is_deleted = 0 OR ic2.is_deleted IS NULL)
      )
    ORDER BY ic.id
");

if (empty($softDeleted)) {
    echo "No soft-deleted duplicates found to reassign." . PHP_EOL;
    exit(0);
}

echo "Found " . count($softDeleted) . " records to reassign." . PHP_EOL . PHP_EOL;

$db->beginTransaction();

try {
    foreach ($softDeleted as $rec) {
        // Generate a new TEMP number from the sequence
        if (!$dryRun) {
            $newSeqId = $db->table('temp_fileno_sequence')->insertGetId([
                'is_used' => 1,
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $newTemp = 'TEMP-' . str_pad($newSeqId, 5, '0', STR_PAD_LEFT);
        } else {
            $newTemp = 'TEMP-XXXXX'; // placeholder for dry run
        }

        echo "  ic.id={$rec->id}: {$rec->temp_fileno} -> {$newTemp} | reg={$rec->registration_number} | prop_id={$rec->prop_id} | p2={$rec->party_2_name}" . PHP_EOL;

        if (!$dryRun) {
            // 1. Update instrument_capture: new temp_fileno + restore is_deleted
            $db->table('instrument_capture')
                ->where('id', $rec->id)
                ->update([
                    'temp_fileno' => $newTemp,
                    'is_deleted' => 0,
                    'updated_at' => now(),
                ]);

            // 2. Restore and update deed_registrations linked to this ic record
            $dr = $db->table('deed_registrations')
                ->where('instrument_capture_id', $rec->id)
                ->first();

            if ($dr) {
                $drUpdate = [
                    'is_deleted' => 0,
                    'updated_at' => now(),
                ];
                // Update fileno in deed_registrations if it was the old temp
                if ($dr->fileno === $rec->temp_fileno) {
                    $drUpdate['fileno'] = $newTemp;
                }
                $db->table('deed_registrations')
                    ->where('id', $dr->id)
                    ->update($drUpdate);

                echo "    -> deed_registrations id={$dr->id}: restored, fileno=" . ($drUpdate['fileno'] ?? $dr->fileno) . PHP_EOL;
            }

            // 3. Allocate new PropID for the new temp number (so it gets its own entry)
            // Actually, we do NOT change prop_id — the record keeps its existing prop_id.
            // PropID_Master already has the entry. No change needed.
        }
    }

    if ($dryRun) {
        $db->rollBack();
        echo PHP_EOL . "=== DRY RUN COMPLETE ===" . PHP_EOL;
        echo "Would reassign " . count($softDeleted) . " records with new TEMP numbers" . PHP_EOL;
        echo "PropID_Master: UNTOUCHED" . PHP_EOL;
        echo PHP_EOL . "Run without --dry-run to apply." . PHP_EOL;
    } else {
        $db->commit();
        echo PHP_EOL . "=== REASSIGNMENT COMPLETE ===" . PHP_EOL;
        echo "Reassigned " . count($softDeleted) . " records with new unique TEMP file numbers" . PHP_EOL;
        echo "All records restored (is_deleted = 0)" . PHP_EOL;
        echo "PropID_Master: UNTOUCHED" . PHP_EOL;
    }

} catch (\Exception $e) {
    $db->rollBack();
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    echo "All changes rolled back." . PHP_EOL;
}
