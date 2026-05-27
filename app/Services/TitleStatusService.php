<?php

namespace App\Services;

use App\Models\TitleStatusApplication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TitleStatusService
{
    private const URL_LABELS = [
        'land'      => 'Land',
        'deeds'     => 'Deeds',
        'cadastral' => 'Cadastral',
        'dciv'      => 'DCIV',
        'pp'        => 'Physical Planning',
    ];

    private const TYPE_SLUG = [
        TitleStatusApplication::TYPE_WITHDRAWAL   => 'withdrawn',
        TitleStatusApplication::TYPE_CANCELLATION => 'cancelled',
        TitleStatusApplication::TYPE_REVOKE        => 'revoked',
        TitleStatusApplication::TYPE_LITIGATION    => 'litigation',
        TitleStatusApplication::TYPE_AMENDMENT     => 'amendment',
    ];

    private const SOURCE_TABLES = [
        'file_indexings',
        'fileNumber',
        'pra',
        'CofO_staging',
        'mls_file_no',
        'customers_staging',
        'entities_staging',
        'instrument_capture',
        'deed_registrations',
        'file_history_staging',
    ];

    public function urlLabel(string $url): string
    {
        return self::URL_LABELS[$url] ?? strtoupper($url);
    }

    public function typeSlug(string $titleType): string
    {
        return self::TYPE_SLUG[$titleType] ?? 'flagged';
    }

    public function generateRemark(string $titleType, string $fileNo, string $fileTitle, string $authority, string $reference): string
    {
        $date = now()->format('d/m/Y');
        $ref  = $reference ? " Reference: {$reference}." : '';

        return match ($titleType) {
            TitleStatusApplication::TYPE_WITHDRAWAL   =>
                "File {$fileNo} — {$fileTitle} has been Withdrawn on {$date}. Authority: {$authority}.{$ref}",
            TitleStatusApplication::TYPE_CANCELLATION =>
                "File {$fileNo} — {$fileTitle} RofO has been Cancelled on {$date}. Authority: {$authority}.{$ref}",
            TitleStatusApplication::TYPE_REVOKE        =>
                "File {$fileNo} — {$fileTitle} has been Revoked on {$date}. Authority: {$authority}.{$ref}",
            TitleStatusApplication::TYPE_LITIGATION    =>
                "File {$fileNo} — {$fileTitle} is under Litigation as of {$date}. Authority: {$authority}.{$ref}",
            TitleStatusApplication::TYPE_AMENDMENT     =>
                "File {$fileNo} — {$fileTitle} Application/RofO/CofO has been Amended/Reconsidered on {$date}. Authority: {$authority}.{$ref}",
            default =>
                "File {$fileNo} — {$fileTitle} status updated on {$date}. Authority: {$authority}.{$ref}",
        };
    }

    /**
     * Flag the file across all source tables and copy to archive tables.
     */
    public function flagAndDecommission(TitleStatusApplication $record): void
    {
        $fileNo   = $record->file_no;
        $slug     = $this->typeSlug($record->title_type);
        $remark   = $record->remark;
        $user     = Auth::user();
        $userName = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? $user->name ?? '')) : 'System';

        foreach (self::SOURCE_TABLES as $table) {
            try {
                if (!DB::connection('sqlsrv')->getSchemaBuilder()->hasTable($table)) {
                    continue;
                }

                $fileCol = $this->fileNumberColumn($table);

                $exists = DB::connection('sqlsrv')->table($table)->where($fileCol, $fileNo)->exists();
                if (!$exists) continue;

                $updates = ['title_status' => 1, 'title_status_type' => $slug, 'title_status_remark' => $remark];

                // Also set is_decommissioned if column exists
                if (DB::connection('sqlsrv')->getSchemaBuilder()->hasColumn($table, 'is_decommissioned')) {
                    $updates['is_decommissioned'] = 1;
                }

                DB::connection('sqlsrv')->table($table)->where($fileCol, $fileNo)->update($updates);
            } catch (\Exception $e) {
                Log::warning("TitleStatus: could not update {$table} for {$fileNo}: " . $e->getMessage());
            }
        }

        // Archive to decommissioned_files
        try {
            $fileRecord    = DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $fileNo)->first();
            $indexRecord   = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $fileNo)->first();

            DB::connection('sqlsrv')->table('decommissioned_files')->insertOrIgnore([
                'file_number_id'       => (int) ($fileRecord->id ?? ($indexRecord->id ?? 0)),
                'file_no'              => $fileNo,
                'mls_file_no'          => $fileNo,
                'kangis_file_no'       => $fileRecord->kangisFileNo ?? ($indexRecord->kangis_file_no ?? null),
                'new_kangis_file_no'   => $fileRecord->NewKANGISFileNo ?? ($indexRecord->new_kangis_file_no ?? null),
                'file_name'            => $fileRecord->FileName ?? ($indexRecord->file_title ?? $record->file_title ?? 'N/A'),
                'commissioning_date'   => $fileRecord->commissioning_date ?? null,
                'decommissioning_date' => now(),
                'decommissioning_reason' => "Title Status: {$slug} — " . ($remark ?? ''),
                'decommissioned_by'    => $userName,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning("TitleStatus: decommissioned_files insert failed for {$fileNo}: " . $e->getMessage());
        }

        // Archive to deprecated_records
        try {
            if ($indexRecord ?? false) {
                DB::connection('sqlsrv')->table('deprecated_records')->insert([
                    'file_indexing_id'   => (int) ($indexRecord->id ?? 0),
                    'file_number'        => $indexRecord->file_number ?? $fileNo,
                    'file_title'         => $indexRecord->file_title ?? $record->file_title,
                    'land_use_type'      => $indexRecord->land_use_type ?? $record->land_use,
                    'plot_number'        => $indexRecord->plot_number ?? $record->plot_no,
                    'district'           => $indexRecord->district ?? $record->district,
                    'lga'                => $indexRecord->lga ?? $record->lga,
                    'location'           => $indexRecord->location ?? $record->location,
                    'plot_size'          => $indexRecord->plot_size ?? null,
                    'tp_no'              => $indexRecord->tp_no ?? null,
                    'lpkn_no'            => $indexRecord->lpkn_no ?? null,
                    'tracking_id'        => $indexRecord->tracking_id ?? null,
                    'original_holder'    => $indexRecord->original_holder ?? $record->applicant_name,
                    'current_holder'     => $indexRecord->current_holder ?? $record->applicant_name,
                    'parent_prop_id'     => $indexRecord->parent_prop_id ?? null,
                    'related_fileno'     => $indexRecord->related_fileno ?? null,
                    'has_transaction'    => $indexRecord->has_transaction ?? 0,
                    'workflow_type'      => "title_status_{$slug}",
                    'decommissioned_by'  => $userName,
                    'decommissioned_at'  => now(),
                    'created_by'         => $indexRecord->created_by ?? null,
                    'updated_by'         => $userName,
                    'serial_no'          => $indexRecord->serial_no ?? null,
                    'batch_no'           => $indexRecord->batch_no ?? null,
                    'workflow_status'    => $indexRecord->workflow_status ?? null,
                    'registry'           => $indexRecord->registry ?? null,
                    'prop_id'            => $indexRecord->prop_id ?? null,
                    'phone'              => $indexRecord->phone ?? null,
                    'residence_address'  => $indexRecord->residence_address ?? null,
                    'general_registry'   => $indexRecord->general_registry ?? null,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("TitleStatus: deprecated_records insert failed for {$fileNo}: " . $e->getMessage());
        }
    }

    private function fileNumberColumn(string $table): string
    {
        return match ($table) {
            'fileNumber'           => 'mlsfNo',
            'mls_file_no'          => 'file_no',
            'file_indexings'       => 'file_number',
            'instrument_capture'   => 'file_no',
            'deed_registrations'   => 'file_no',
            default                => 'file_number',
        };
    }
}
