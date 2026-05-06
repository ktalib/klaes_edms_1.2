<?php
/**
 * Fix duplicate temp_fileno records in instrument_capture
 * 
 * Strategy:
 *   - For each duplicate pair, soft-delete the NEWER record (is_deleted = 1)
 *   - Also soft-delete the corresponding deed_registrations entry
 *   - Does NOT touch PropID_Master or prop_id in any way
 * 
 * Run with --dry-run first:   php fix_ic_temp_dupes.php --dry-run
 * Then apply:                  php fix_ic_temp_dupes.php
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$dryRun = in_array('--dry-run', $argv);
$db = DB::connection('sqlsrv');

echo ($dryRun ? "[DRY RUN] " : "[LIVE] ") . "Fixing duplicate temp_fileno in instrument_capture" . PHP_EOL . PHP_EOL;

// Find duplicates
$dupes = $db->select("
    SELECT temp_fileno, COUNT(*) as cnt
    FROM instrument_capture
    WHERE temp_fileno IS NOT NULL AND temp_fileno LIKE 'TEMP-%'
    AND (is_deleted = 0 OR is_deleted IS NULL)
    GROUP BY temp_fileno
    HAVING COUNT(*) > 1
    ORDER BY temp_fileno
");

if (empty($dupes)) {
    echo "No duplicates found. Nothing to do." . PHP_EOL;
    exit(0);
}

echo "Found " . count($dupes) . " duplicate temp_fileno groups." . PHP_EOL . PHP_EOL;

$totalIcFixed = 0;
$totalDrFixed = 0;

$db->beginTransaction();

try {
    foreach ($dupes as $d) {
        echo "=== {$d->temp_fileno} ({$d->cnt} records) ===" . PHP_EOL;

        // Get all records for this temp_fileno, ordered by id (first = keeper)
        $rows = $db->table('instrument_capture')
            ->where('temp_fileno', $d->temp_fileno)
            ->where(function($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
            ->orderBy('id')
            ->get();

        $keeper = $rows->first();
        $duplicates = $rows->slice(1); // everything after the first

        echo "  KEEPING ic.id={$keeper->id} (reg={$keeper->registration_number})" . PHP_EOL;

        foreach ($duplicates as $dup) {
            echo "  SOFT-DELETING ic.id={$dup->id} (reg={$dup->registration_number})" . PHP_EOL;

            if (!$dryRun) {
                // Soft-delete the instrument_capture record
                $db->table('instrument_capture')
                    ->where('id', $dup->id)
                    ->update([
                        'is_deleted' => 1,
                        'updated_at' => now(),
                    ]);
            }
            $totalIcFixed++;

            // Soft-delete the matching deed_registration
            if ($dup->registration_number) {
                $dr = $db->table('deed_registrations')
                    ->where('instrument_capture_id', $dup->id)
                    ->where(function($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
                    ->first();

                if ($dr) {
                    echo "  SOFT-DELETING deed_registrations id={$dr->id} (reg={$dr->registration_number})" . PHP_EOL;
                    if (!$dryRun) {
                        $db->table('deed_registrations')
                            ->where('id', $dr->id)
                            ->update([
                                'is_deleted' => 1,
                                'updated_at' => now(),
                            ]);
                    }
                    $totalDrFixed++;
                }
            }
        }
        echo PHP_EOL;
    }

    if ($dryRun) {
        $db->rollBack();
        echo "=== DRY RUN COMPLETE ===" . PHP_EOL;
        echo "Would soft-delete {$totalIcFixed} instrument_capture records" . PHP_EOL;
        echo "Would soft-delete {$totalDrFixed} deed_registration records" . PHP_EOL;
        echo "PropID_Master: UNTOUCHED" . PHP_EOL;
        echo PHP_EOL . "Run without --dry-run to apply." . PHP_EOL;
    } else {
        $db->commit();
        echo "=== FIX APPLIED ===" . PHP_EOL;
        echo "Soft-deleted {$totalIcFixed} instrument_capture records" . PHP_EOL;
        echo "Soft-deleted {$totalDrFixed} deed_registration records" . PHP_EOL;
        echo "PropID_Master: UNTOUCHED" . PHP_EOL;
    }

} catch (\Exception $e) {
    $db->rollBack();
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    echo "All changes rolled back." . PHP_EOL;
}
