<?php
/**
 * Migrate Occupancy Permit (OP) records from file_history_staging to pra table.
 *
 * Usage:
 *   php migrate_ops_fh_to_pra.php              -- dry run (preview only)
 *   php migrate_ops_fh_to_pra.php --execute    -- actually migrate
 */

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$execute = in_array('--execute', $argv ?? [], true);

echo "=== Migrate OP records from file_history_staging → pra ===\n";
echo "Mode: " . ($execute ? "EXECUTE (will write to DB)" : "DRY RUN (preview only)") . "\n\n";

// ── Step 1: Find OP records in file_history_staging ─────────────────────────

$opRecords = DB::connection('sqlsrv')
    ->table('file_history_staging')
    ->where(function ($q) {
        $q->where('transaction_type', 'like', '%Occupancy Permit%')
          ->orWhere('instrument_type', 'like', '%Occupancy Permit%');
    })
    ->orderBy('id')
    ->get();

echo "Found {$opRecords->count()} OP record(s) in file_history_staging.\n\n";

if ($opRecords->isEmpty()) {
    echo "Nothing to migrate. Done.\n";
    exit(0);
}

// Show summary
echo str_pad('ID', 8) . str_pad('mlsFNo', 20) . str_pad('fileno', 20) . str_pad('transaction_type', 35) . str_pad('party_2', 30) . str_pad('prop_id', 12) . "\n";
echo str_repeat('-', 125) . "\n";
foreach ($opRecords as $r) {
    echo str_pad($r->id, 8)
       . str_pad($r->mlsFNo ?? '—', 20)
       . str_pad($r->fileno ?? '—', 20)
       . str_pad($r->transaction_type ?? '—', 35)
       . str_pad($r->party_2 ?? '—', 30)
       . str_pad($r->prop_id ?? '—', 12)
       . "\n";
}
echo "\n";

if (!$execute) {
    echo "Run with --execute to perform the migration.\n";
    exit(0);
}

// ── Step 2: Get PRA table columns ──────────────────────────────────────────

$praColumns = array_flip(Schema::connection('sqlsrv')->getColumnListing('pra'));

// Column mapping: file_history_staging → pra
// Most columns share the same name. Map the ones that differ.
$columnMap = [
    'id'                   => null,  // skip - pra auto-generates
    'kangisFileNo'         => null,  // pra doesn't have this
    'NewKANGISFileno'      => null,  // pra doesn't have this
    'date_migrated'        => null,  // skip
    'migrated_by'          => null,  // skip
    'is_caveated'          => null,  // skip for pra
    'caveated_comment'     => null,  // skip
    'caveat_id'            => null,  // skip
    'is_deleted'           => null,  // skip
    'deleted_by'           => null,  // skip
    'deleted_at'           => null,  // skip
    'approved_plan_no'     => null,  // skip
    'schedule'             => null,  // skip
    'date_recommended'     => null,  // skip
    'date_approved'        => null,  // skip
    // These map directly (same name in both tables):
    // mlsFNo, fileno, transaction_type, instrument_type, transaction_date,
    // serialNo, pageNo, volumeNo, regNo, reg_date, reg_time,
    // period, period_unit, property_description, districtName, plot_no,
    // lgsaOrCity, layout, land_use, location, tp_no, lpkn_no,
    // party_1, party_2, party_3, party_4, party_5,
    // prop_id, test_control, source, created_by, updated_by, created_at, updated_at
];

$migrated = 0;
$skipped = 0;
$errors = [];

DB::connection('sqlsrv')->beginTransaction();

try {
    foreach ($opRecords as $record) {
        $row = (array) $record;
        $fhId = $row['id'];

        // Check if this record already exists in PRA (duplicate prevention)
        $duplicateQuery = DB::connection('sqlsrv')->table('pra');
        $hasDuplicateCheck = false;

        if (!empty($row['prop_id'])) {
            $duplicateQuery->where('prop_id', $row['prop_id']);
            $hasDuplicateCheck = true;
        }

        if (!empty($row['mlsFNo'])) {
            if ($hasDuplicateCheck) {
                $duplicateQuery->where('mlsFNo', $row['mlsFNo']);
            } else {
                $duplicateQuery->where('mlsFNo', $row['mlsFNo']);
                $hasDuplicateCheck = true;
            }
        }

        if (!empty($row['fileno'])) {
            if (!$hasDuplicateCheck) {
                $duplicateQuery->where('fileno', $row['fileno']);
                $hasDuplicateCheck = true;
            }
        }

        if ($hasDuplicateCheck) {
            $duplicateQuery->where(function ($q) {
                $q->where('transaction_type', 'like', '%Occupancy Permit%')
                  ->orWhere('instrument_type', 'like', '%Occupancy Permit%');
            });

            if (!empty($row['serialNo'])) {
                $duplicateQuery->where('serialNo', $row['serialNo']);
            }

            $existing = $duplicateQuery->first();
            if ($existing) {
                echo "  SKIP FH#{$fhId} — already exists in PRA as #{$existing->id}\n";
                $skipped++;
                continue;
            }
        }

        // Build PRA payload - only include columns that exist in PRA
        $praPayload = [];
        foreach ($row as $col => $val) {
            // Skip explicitly excluded columns
            if (array_key_exists($col, $columnMap) && $columnMap[$col] === null) {
                continue;
            }

            $targetCol = $columnMap[$col] ?? $col;

            if (isset($praColumns[$targetCol])) {
                $praPayload[$targetCol] = $val;
            }
        }

        // Ensure source is marked for traceability
        $praPayload['source'] = 'migrated_from_fh';
        $praPayload['updated_at'] = now();

        // Map party fields to Grantor/Grantee if pra has them
        if (isset($praColumns['Grantor']) && !empty($row['party_1'])) {
            $praPayload['Grantor'] = $row['party_1'];
        }
        if (isset($praColumns['Grantee']) && !empty($row['party_2'])) {
            $praPayload['Grantee'] = $row['party_2'];
        }

        try {
            $newPraId = DB::connection('sqlsrv')->table('pra')->insertGetId($praPayload);
            echo "  MIGRATED FH#{$fhId} → PRA#{$newPraId} ({$row['transaction_type']})\n";
            $migrated++;
        } catch (\Throwable $e) {
            echo "  ERROR FH#{$fhId}: {$e->getMessage()}\n";
            $errors[] = "FH#{$fhId}: {$e->getMessage()}";
        }
    }

    if (empty($errors)) {
        // Delete migrated OP records from file_history_staging
        $deleteIds = $opRecords->pluck('id')->toArray();
        // Only delete the ones we successfully migrated (not skipped/errored)
        $migratedIds = [];
        foreach ($opRecords as $record) {
            $row = (array) $record;
            // Re-check: was it migrated (not skipped)?
            // Simple approach: delete all OP records from FH since they don't belong there
            $migratedIds[] = $row['id'];
        }

        if (!empty($migratedIds)) {
            $deleted = DB::connection('sqlsrv')
                ->table('file_history_staging')
                ->whereIn('id', $migratedIds)
                ->where(function ($q) {
                    $q->where('transaction_type', 'like', '%Occupancy Permit%')
                      ->orWhere('instrument_type', 'like', '%Occupancy Permit%');
                })
                ->delete();
            echo "\n  Deleted {$deleted} OP record(s) from file_history_staging.\n";
        }

        DB::connection('sqlsrv')->commit();
        echo "\n✓ Migration complete: {$migrated} migrated, {$skipped} skipped (duplicates).\n";
    } else {
        DB::connection('sqlsrv')->rollBack();
        echo "\n✗ Rolled back due to " . count($errors) . " error(s). Fix and retry.\n";
    }
} catch (\Throwable $e) {
    DB::connection('sqlsrv')->rollBack();
    echo "\n✗ Fatal error: {$e->getMessage()}\n";
    exit(1);
}
