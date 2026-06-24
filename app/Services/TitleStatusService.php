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
        TitleStatusApplication::TYPE_SURRENDER     => 'surrendered',
        TitleStatusApplication::TYPE_REGRANT       => 'regranted',
        TitleStatusApplication::TYPE_SUBDIVISION   => 'subdivision',
        TitleStatusApplication::TYPE_MERGER        => 'merger',
        TitleStatusApplication::TYPE_PURPOSE       => 'change_of_purpose',
        TitleStatusApplication::TYPE_EXTENSION     => 'extension',
        TitleStatusApplication::TYPE_CHANGE_NAME   => 'change_of_name',
        TitleStatusApplication::TYPE_SEPARATION    => 'separation',
    ];

    private const TYPE_VERB = [
        TitleStatusApplication::TYPE_WITHDRAWAL   => 'Withdrawal',
        TitleStatusApplication::TYPE_CANCELLATION => 'Cancellation',
        TitleStatusApplication::TYPE_REVOKE        => 'Revocation',
        TitleStatusApplication::TYPE_LITIGATION    => 'Litigation',
        TitleStatusApplication::TYPE_AMENDMENT     => 'Amendment/Reconsideration',
        TitleStatusApplication::TYPE_SURRENDER     => 'Surrender',
        TitleStatusApplication::TYPE_REGRANT       => 'Re-grant',
        TitleStatusApplication::TYPE_SUBDIVISION   => 'Subdivision',
        TitleStatusApplication::TYPE_MERGER        => 'Merger',
        TitleStatusApplication::TYPE_PURPOSE       => 'Change of Purpose',
        TitleStatusApplication::TYPE_EXTENSION     => 'Extension',
        TitleStatusApplication::TYPE_CHANGE_NAME   => 'Change of Name',
        TitleStatusApplication::TYPE_SEPARATION    => 'Separation',
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

 

    /**
     * Template: "[Status type] was initiated by [initiator] for File [file_no] on [Time/Date] due to [Reason]"
     * Applicant/Allottee get the holder name with role suffix; Ministry/Court Order render literally.
     */
    public function generateRemark(string $titleType, string $initiatedBy, string $reason, string $applicantName = '', string $fileNo = ''): string
    {
        $statusType = self::TYPE_VERB[$titleType] ?? $titleType;
        $datetime   = now()->format('d/m/Y H:i');
        $reasonText = $reason !== '' ? $reason : '[Reason]';

        if ($initiatedBy === 'Allottee' || $initiatedBy === 'Applicant') {
            $initiator = $applicantName !== ''
                ? "{$applicantName} ({$initiatedBy})"
                : $initiatedBy;
        } elseif ($initiatedBy !== '') {
            $initiator = $initiatedBy;
        } else {
            $initiator = '[Ministry/Allottee]';
        }

        $fileRef = $fileNo !== '' ? " FileNo {$fileNo}" : '';

        return "{$statusType}{$fileRef} was initiated by {$initiator} on {$datetime} due to {$reasonText}";
    }

    /**
     * Flag the file across all source tables and copy to archive tables.
     *
     * @param bool $falseDecommissioning When true the file is NOT actually decommissioned
     *        (e.g. a Title Status update raised from File Indexing). The title-status flags
     *        are still recorded and the row is archived to decommissioned_files with
     *        false_decommissioning = 1, but the real "is_decommissioned" side effects
     *        (flagging the source file + deprecated_records) are skipped.
     */
    /**
     * @param TitleStatusApplication|TitleStatusApplication[] $records One record, or several
     *        when multiple title statuses were selected at once for the same file. The flag
     *        columns (title_status_type / title_status_remark) are then written as a combined
     *        value reflecting every selected status.
     */
    public function flagAndDecommission($records, bool $falseDecommissioning = false): void
    {
        $records = is_array($records) ? array_values($records) : [$records];
        $records = array_filter($records);
        if (empty($records)) {
            return;
        }

        $record   = $records[0];
        $fileNo   = $record->file_no;

        // Combine slugs and remarks across every selected status for the flag columns.
        $slugs    = [];
        $remarks  = [];
        foreach ($records as $r) {
            $slugs[]   = $this->typeSlug($r->title_type);
            $rRemark   = trim((string) ($r->remark ?? ''));
            if ($rRemark !== '') {
                $remarks[] = $rRemark;
            }
        }
        $slug     = implode(', ', array_values(array_unique($slugs)));
        $remark   = implode("\n", $remarks);

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

                // Only mark the source file as decommissioned for a REAL decommissioning.
                if (!$falseDecommissioning && DB::connection('sqlsrv')->getSchemaBuilder()->hasColumn($table, 'is_decommissioned')) {
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

            // NOTE: do not use insertOrIgnore() here — SQL Server does not support it
            // (Laravel throws "This database engine does not support inserting while
            // ignoring errors."). Guard against duplicates manually instead.
            $alreadyArchived = DB::connection('sqlsrv')->table('decommissioned_files')
                ->where('file_no', $fileNo)
                ->where('false_decommissioning', $falseDecommissioning ? 1 : 0)
                ->exists();

            if (!$alreadyArchived) {
                DB::connection('sqlsrv')->table('decommissioned_files')->insert([
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
                    'false_decommissioning' => $falseDecommissioning ? 1 : 0,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("TitleStatus: decommissioned_files insert failed for {$fileNo}: " . $e->getMessage());
        }

        // Archive to deprecated_records — only for a REAL decommissioning. A false
        // decommissioning (Title Status from File Indexing) does not deprecate the record.
        try {
            if (!$falseDecommissioning && ($indexRecord ?? false)) {
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
            'CofO_staging'         => 'mlsFNo',
            default                => 'file_number',
        };
    }
}
