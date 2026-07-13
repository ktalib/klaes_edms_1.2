<?php

namespace App\Services;

use App\Models\DecommissionedFiles;
use App\Models\FileIndexing;
use App\Models\FileNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class PlotWorkflowService
{
    /**
     * Decommission a set of files and move them to archives.
     *
     * @param string|null $successorFileNo the file number that replaces the decommissioned file(s)
     *                                      (the merged/subdivided/separated/extended result), stored
     *                                      as an old -> successor lineage pointer.
     */
    public function decommissionFiles(array $fileNumbers, string $reason, ?string $commissionedBy = null, ?string $successorFileNo = null): array
    {
        $summary = [
            'archived' => [],
            'deleted' => 0,
            'errors' => []
        ];

        $commissionedBy = $commissionedBy ?: (Auth::user() ? Auth::user()->first_name . ' ' . Auth::user()->last_name : 'System');

        foreach ($fileNumbers as $fileNo) {
            try {
                // 1. Fetch records from all active tables. Match the file's OWN identifiers —
                //    its MLS number (mlsfNo / file_number) OR its KANGIS number (kangisFileNo /
                //    kangis_file_no). A KANGIS-only file (MLS = N/A, e.g. "MLKN 2455") stores its
                //    number in the KANGIS column, so an mlsfNo-only match would miss it and leave
                //    the live row behind after archiving. (Do NOT match new_kangis_file_no — that
                //    is a recert pointer to a SUCCESSOR file, not this file's own identity.)
                $fileRecord = DB::connection('sqlsrv')->table('fileNumber')
                    ->where(function ($q) use ($fileNo) {
                        $q->where('mlsfNo', $fileNo)->orWhere('kangisFileNo', $fileNo);
                    })->first();
                $indexingRecord = DB::connection('sqlsrv')->table('file_indexings')
                    ->where(function ($q) use ($fileNo) {
                        $q->where('file_number', $fileNo)->orWhere('kangis_file_no', $fileNo);
                    })->first();

                if (!$fileRecord && !$indexingRecord) {
                    $summary['errors'][] = "File $fileNo not found in active records.";
                    continue;
                }

                // 2. Insert into decommissioned_files
                $decommissionRow = [
                    'file_number_id' => (int) ($fileRecord->id ?? ($indexingRecord->id ?? 0)),
                    'file_no' => $fileNo,
                    'mls_file_no' => $fileNo,
                    'kangis_file_no' => $fileRecord->kangisFileNo ?? ($indexingRecord->kangis_file_no ?? null),
                    'new_kangis_file_no' => $fileRecord->NewKANGISFileNo ?? ($indexingRecord->new_kangis_file_no ?? null),
                    'file_name' => $fileRecord->FileName ?? ($indexingRecord->file_title ?? 'N/A'),
                    'commissioning_date' => $fileRecord->commissioning_date ?? null,
                    'decommissioning_date' => now(),
                    'decommissioning_reason' => $reason,
                    'decommissioned_by' => $commissionedBy,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                // Store the old -> successor pointer when the column exists (added 2026_07_03).
                if ($successorFileNo && Schema::connection('sqlsrv')->hasColumn('decommissioned_files', 'successor_file_no')) {
                    $decommissionRow['successor_file_no'] = $successorFileNo;
                }
                DB::connection('sqlsrv')->table('decommissioned_files')->insert($decommissionRow);

                // 2.5 Archive detailed indexing record to deprecated_records before deletion
                if ($indexingRecord) {
                    DB::connection('sqlsrv')->table('deprecated_records')->insert([
                        'file_indexing_id' => (int) ($indexingRecord->id ?? 0),
                        'file_number' => $indexingRecord->file_number ?? $fileNo,
                        'file_title' => $indexingRecord->file_title ?? null,
                        'land_use_type' => $indexingRecord->land_use_type ?? null,
                        'plot_number' => $indexingRecord->plot_number ?? null,
                        'district' => $indexingRecord->district ?? null,
                        'lga' => $indexingRecord->lga ?? null,
                        'location' => $indexingRecord->location ?? null,
                        'plot_size' => $indexingRecord->plot_size ?? null,
                        'tp_no' => $indexingRecord->tp_no ?? null,
                        'lpkn_no' => $indexingRecord->lpkn_no ?? null,
                        'tracking_id' => $indexingRecord->tracking_id ?? null,
                        'original_holder' => $indexingRecord->original_holder ?? null,
                        'current_holder' => $indexingRecord->current_holder ?? null,
                        'parent_prop_id' => $indexingRecord->parent_prop_id ?? null,
                        'related_fileno' => $indexingRecord->related_fileno ?? null,
                        'has_transaction' => $indexingRecord->has_transaction ?? 0,
                        'workflow_type' => $reason,
                        'decommissioned_by' => $commissionedBy,
                        'decommissioned_at' => now(),
                        'created_by' => $indexingRecord->created_by ?? null,
                        'updated_by' => $indexingRecord->updated_by ?? null,
                        'serial_no' => $indexingRecord->serial_no ?? null,
                        'batch_no' => $indexingRecord->batch_no ?? null,
                        'workflow_status' => $indexingRecord->workflow_status ?? null,
                        'registry' => $indexingRecord->registry ?? null,
                        'prop_id' => $indexingRecord->prop_id ?? null,
                        'phone' => $indexingRecord->phone ?? null,
                        'residence_address' => $indexingRecord->residence_address ?? null,
                        'general_registry' => $indexingRecord->general_registry ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // 3. Hard Delete from active tables. Mirror the lookup above so a KANGIS-only file
                //    is removed by its KANGIS number too — otherwise the archive row is written but
                //    the live fileNumber/file_indexings row survives and keeps surfacing in search.
                DB::connection('sqlsrv')->table('fileNumber')
                    ->where(function ($q) use ($fileNo) {
                        $q->where('mlsfNo', $fileNo)->orWhere('kangisFileNo', $fileNo);
                    })->delete();
                DB::connection('sqlsrv')->table('file_indexings')
                    ->where(function ($q) use ($fileNo) {
                        $q->where('file_number', $fileNo)->orWhere('kangis_file_no', $fileNo);
                    })->delete();
                DB::connection('sqlsrv')->table('entities_staging')->where('file_number', $fileNo)->delete();
                DB::connection('sqlsrv')->table('customers_staging')->where('file_number', $fileNo)->delete();

                // Also clean up grouping placeholders to prevent "Tracking ID already in use" errors later
                DB::connection('sqlsrv')->table('kangis_grouping')
                    ->where('kangis_fileno_placeholder', $fileNo)
                    ->delete();

                // NOTE: We deliberately DO NOT delete the file's rows from the Legal Search staging
                // tables (file_history_staging, CofO_staging, pra, deed_registrations). Legal Search
                // relies on those rows to display the successor file's lineage/history via prop_id and
                // parent_prop_id expansion. Suppression of a decommissioned file when it is searched
                // directly is handled at query time in LegalSearchService (see getDecommissionedFileNumbers).

                $summary['archived'][] = $fileNo;
                $summary['deleted']++;

                $this->logPlotsWorkflow('info', "File decommissioned and archived: $fileNo", ['reason' => $reason]);
            } catch (\Exception $e) {
                $this->logPlotsWorkflow('error', "Failed to decommission file: $fileNo", ['error' => $e->getMessage()]);
                $summary['errors'][] = "Error decommissioning $fileNo: " . $e->getMessage();
            }
        }

        // Materialise / refresh the parcel-update (merger) group so the File Tracking Sheet and
        // File Movement History can stitch every related file's movement log into one lineage.
        // Best-effort only — this must never fail the decommission itself.
        try {
            $mergerService = app(\App\Services\FileMergerService::class);
            $seed = $successorFileNo ?: ($fileNumbers[0] ?? null);
            if ($seed) {
                $mergerService->rebuildForFile($seed);
            }
            // Also seed from each decommissioned parent so a group is still built when the
            // successor is not yet linked (e.g. the caller passed no successor file number).
            foreach ($fileNumbers as $parentFileNo) {
                $mergerService->rebuildForFile($parentFileNo);
            }
        } catch (\Throwable $mergerError) {
            $this->logPlotsWorkflow('warning', 'File merger group rebuild skipped', [
                'error' => $mergerError->getMessage(),
            ]);
        }

        return $summary;
    }

    /**
     * Update historical prop_id across various tables.
     */
    public function updateHistoricalPropId(array $oldPropIds, int $newPropId): int
    {
        if (empty($oldPropIds)) {
            return 0;
        }

        $tables = [
            'pra' => 'Prop_id',
            'deeds_registrations' => 'prop_id', // Assuming these exist
            'c_of_o' => 'prop_id',
            'billings' => 'prop_id',
            'deeds_bill_balances_metadata' => 'prop_id',
            'file_history_staging' => 'prop_id',
            'PropID_Master' => 'prop_id', // Handle lineage in master? Or just leave it?
        ];

        $totalUpdated = 0;

        foreach ($tables as $table => $column) {
            try {
                if (Schema::connection('sqlsrv')->hasTable($table) && Schema::connection('sqlsrv')->hasColumn($table, $column)) {
                    $updated = DB::connection('sqlsrv')->table($table)
                        ->whereIn($column, $oldPropIds)
                        ->update([$column => $newPropId, 'updated_at' => now()]);
                    $totalUpdated += $updated;
                }
            } catch (\Exception $e) {
                $this->logPlotsWorkflow('warning', "Could not update historical records in table $table", ['error' => $e->getMessage()]);
            }
        }

        return $totalUpdated;
    }

    /**
     * Specialized logger for Plot Workflow actions.
     */
    private function logPlotsWorkflow(string $level, string $message, array $context = []): void
    {
        try {
            $logger = Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/plots-workflow.log'),
                'level' => 'debug',
            ]);
            $logger->{$level}($message, $context);
        } catch (\Throwable $e) {
            Log::warning('Failed to write Plots workflow audit log from service', [
                'message' => $message,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
