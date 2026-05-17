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
     */
    public function decommissionFiles(array $fileNumbers, string $reason, ?string $commissionedBy = null): array
    {
        $summary = [
            'archived' => [],
            'deleted' => 0,
            'errors' => []
        ];

        $commissionedBy = $commissionedBy ?: (Auth::user() ? Auth::user()->first_name . ' ' . Auth::user()->last_name : 'System');

        foreach ($fileNumbers as $fileNo) {
            try {
                // 1. Fetch records from all active tables
                $fileRecord = DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $fileNo)->first();
                $indexingRecord = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $fileNo)->first();

                if (!$fileRecord && !$indexingRecord) {
                    $summary['errors'][] = "File $fileNo not found in active records.";
                    continue;
                }

                // 2. Insert into decommissioned_files
                DB::connection('sqlsrv')->table('decommissioned_files')->insert([
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
                ]);

                // 3. Hard Delete from active tables
                DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $fileNo)->delete();
                DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $fileNo)->delete();
                DB::connection('sqlsrv')->table('entities_staging')->where('file_number', $fileNo)->delete();
                DB::connection('sqlsrv')->table('customers_staging')->where('file_number', $fileNo)->delete();

                // Also clean up grouping placeholders to prevent "Tracking ID already in use" errors later
                DB::connection('sqlsrv')->table('kangis_grouping')
                    ->where('kangis_fileno_placeholder', $fileNo)
                    ->delete();

                $summary['archived'][] = $fileNo;
                $summary['deleted']++;

                $this->logPlotsWorkflow('info', "File decommissioned and archived: $fileNo", ['reason' => $reason]);
            } catch (\Exception $e) {
                $this->logPlotsWorkflow('error', "Failed to decommission file: $fileNo", ['error' => $e->getMessage()]);
                $summary['errors'][] = "Error decommissioning $fileNo: " . $e->getMessage();
            }
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
