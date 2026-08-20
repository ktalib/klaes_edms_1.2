<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pushes edits made on a file-indexing record out to the other systems that hold the
 * same facts: the file-number registers, PRA, and the OSS application the file came from.
 *
 * Before this, editing a file in File Indexing updated file_indexings and a handful of
 * columns on fileNumber (FileName, location, lga, plot_no, tp_no — see
 * FileIndexingController::updateFileNumberTable) and nothing else. Correcting a plot
 * number or an LGA left PRA, mls_file_no and the originating OSS application showing the
 * old value indefinitely.
 *
 * Two rules govern what this writes:
 *
 * 1. ONLY FIELDS THAT ACTUALLY CHANGED. The payload is a diff of the record before and
 *    after the save, not a snapshot. A blanket push would stomp values in OSS and PRA
 *    that were deliberately different from the indexing record — those tables are not
 *    copies of file_indexings, they are separate records that happen to share some facts.
 *
 * 2. NEVER PARTY / APPLICANT IDENTITY. Property attributes (plot, TP, district, LGA,
 *    location, land use, plot size) propagate everywhere. The file TITLE propagates only
 *    to the file-identity mirrors — fileNumber.FileName, mls_file_no.file_name,
 *    customers_staging.customer_name, entities_staging.entity_name — which is exactly the
 *    set InstrumentRegistrationService::syncPartyNames() already maintains in the other
 *    direction — PLUS the holder name on PRA, under a strict condition described below.
 *
 *    The holder-name case matters because the OSS listings show
 *    COALESCE(pra.Grantee, fileNumber.FileName) as the file title
 *    (OpResettlementApplicationController). Updating only FileName left those screens
 *    showing the previous owner forever, which is the bug this was reported for.
 *
 *    PRA holder names are only rewritten where the stored value STILL EQUALS THE OLD
 *    FILE TITLE — i.e. that row was already mirroring the file title and is simply
 *    stale. A pra row whose Grantee is some other party is a genuine, different party
 *    to a registered instrument and is never touched: rewriting it would falsify the
 *    deed. Grantor / party_1 is never written at all — that is the PREVIOUS owner.
 *
 *    Still deliberately NOT written: mother_applications first_name / surname /
 *    corporate_name — the identity of who submitted an application, not a property
 *    attribute of the file.
 */
class FileIndexingPropagationService
{
    /**
     * Indexing fields whose changes are worth pushing outward, and how they are named
     * on the indexing record.
     */
    private const SYNCABLE_FIELDS = [
        'file_title',
        'location',
        'street_name',
        'district',
        'lga',
        'plot_number',
        'tp_no',
        'plot_size',
        'land_use_type',
    ];

    /**
     * Compare the record before and after the save and push whatever changed.
     *
     * @param  object  $before  the file_indexings row as it was
     * @param  object  $after   the file_indexings row as it now is
     * @return array<string,mixed> per-target row counts, for logging and the API response
     */
    public function propagate($before, $after): array
    {
        [$changes, $previous] = $this->diff($before, $after);

        if (empty($changes)) {
            return ['changed' => [], 'targets' => []];
        }

        $fileNumbers = $this->fileNumberVariants($after);

        if (empty($fileNumbers)) {
            Log::info('FileIndexingPropagation: nothing to key on', [
                'file_indexing_id' => $after->id ?? null,
                'changed' => array_keys($changes),
            ]);

            return ['changed' => array_keys($changes), 'targets' => []];
        }

        $targets = [];

        foreach ([
            'fileNumber' => fn () => $this->syncFileNumber($changes, $fileNumbers),
            'mls_file_no' => fn () => $this->syncMlsFileNo($changes, $fileNumbers),
            'pra' => fn () => $this->syncPra($changes, $fileNumbers),
            'pra_holder_name' => fn () => $this->syncHolderName('pra', ['Grantee', 'party_2'],
                ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno', 'resolved_fileno'],
                $changes, $previous, $fileNumbers),
            'instrument_capture_holder_name' => fn () => $this->syncHolderName('instrument_capture', ['party_2_name'],
                ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'],
                $changes, $previous, $fileNumbers),
            'customers_staging' => fn () => $this->syncNameMirror('customers_staging', 'customer_name', 'file_number', $changes, $fileNumbers),
            'entities_staging' => fn () => $this->syncNameMirror('entities_staging', 'entity_name', 'file_number', $changes, $fileNumbers),
            'oss_applications' => fn () => $this->syncOssApplications($changes, $fileNumbers),
            'mother_applications' => fn () => $this->syncMotherApplication($changes, $after),
            'subapplications' => fn () => $this->syncSubApplication($changes, $after),
        ] as $label => $sync) {
            try {
                $rows = $sync();
                if ($rows > 0) {
                    $targets[$label] = $rows;
                }
            } catch (Throwable $e) {
                // One unreachable target must not fail the save — file_indexings is already
                // committed and is the authoritative record. Report it loudly instead.
                Log::error('FileIndexingPropagation: target failed', [
                    'target' => $label,
                    'file_indexing_id' => $after->id ?? null,
                    'error' => $e->getMessage(),
                ]);
                $targets[$label] = 'failed: ' . $e->getMessage();
            }
        }

        Log::info('FileIndexingPropagation: done', [
            'file_indexing_id' => $after->id ?? null,
            'file_numbers' => $fileNumbers,
            'changed' => array_keys($changes),
            'targets' => $targets,
        ]);

        return ['changed' => array_keys($changes), 'targets' => $targets];
    }

    /**
     * @return array{0:array<string,mixed>,1:array<string,mixed>} [changed => new, changed => old]
     */
    private function diff($before, $after): array
    {
        $changes = [];
        $previous = [];

        foreach (self::SYNCABLE_FIELDS as $field) {
            $old = $this->scalarize($before->$field ?? null);
            $new = $this->scalarize($after->$field ?? null);

            // Only a real, non-blank new value propagates. Clearing a field in indexing
            // does not mean the other systems should lose their copy — those are usually
            // populated from sources indexing does not see.
            if ($new === '' || $new === null) {
                continue;
            }

            if ($this->normalize($old) !== $this->normalize($new)) {
                $changes[$field] = $new;
                $previous[$field] = $old;
            }
        }

        return [$changes, $previous];
    }

    /**
     * Rename the current holder on instrument rows that were already mirroring the file
     * title, and only those.
     *
     * The guard is the whole point: `WHERE column = old_title`. A row holding any other
     * name belongs to a genuinely different party on a registered instrument and must
     * survive untouched. Grantor / party_1 is never a target — that is the previous owner.
     *
     * @param  array<int,string>  $nameColumns
     * @param  array<int,string>  $keyColumns
     */
    private function syncHolderName(string $table, array $nameColumns, array $keyColumns, array $changes, array $previous, array $fileNumbers): int
    {
        $newTitle = $changes['file_title'] ?? null;
        $oldTitle = $previous['file_title'] ?? null;

        if ($newTitle === null || $oldTitle === null || trim((string) $oldTitle) === '') {
            return 0;
        }

        if (!Schema::connection('sqlsrv')->hasTable($table)) {
            return 0;
        }

        $nameColumns = array_values(array_filter(
            $nameColumns,
            fn ($column) => Schema::connection('sqlsrv')->hasColumn($table, $column)
        ));

        $keyColumns = array_values(array_filter(
            $keyColumns,
            fn ($column) => Schema::connection('sqlsrv')->hasColumn($table, $column)
        ));

        if (empty($nameColumns) || empty($keyColumns)) {
            return 0;
        }

        $affected = 0;

        // One statement per column: a row may carry the stale title in Grantee but a
        // different, correct value in party_2, and each must be judged on its own.
        foreach ($nameColumns as $column) {
            $affected += DB::connection('sqlsrv')
                ->table($table)
                ->where(function ($query) use ($keyColumns, $fileNumbers) {
                    foreach ($keyColumns as $index => $key) {
                        $index === 0
                            ? $query->whereIn($key, $fileNumbers)
                            : $query->orWhereIn($key, $fileNumbers);
                    }
                })
                ->whereRaw('UPPER(LTRIM(RTRIM(' . $column . '))) = UPPER(?)', [trim((string) $oldTitle)])
                ->update([$column => $newTitle]);
        }

        return $affected;
    }

    /**
     * Block-indexed records store arrays/JSON in these columns; the outward tables hold a
     * single value, so take the first entry.
     */
    private function scalarize($value)
    {
        if (is_array($value)) {
            return $value === [] ? null : (is_scalar($value[0] ?? null) ? $value[0] : null);
        }

        if (is_string($value) && $value !== '' && ($value[0] === '[' || $value[0] === '{')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $this->scalarize($decoded);
            }
        }

        return is_scalar($value) ? trim((string) $value) : null;
    }

    private function normalize($value): string
    {
        return strtoupper(trim((string) $value));
    }

    /**
     * Every number this physical file is known by. A file's records are split across the
     * main number, the KANGIS aliases and a temporary "(T)" number, and matching only the
     * literal file_number silently updates nothing for temp-registered files.
     *
     * @return array<int,string>
     */
    private function fileNumberVariants($record): array
    {
        $candidates = [
            $record->file_number ?? null,
            $record->temp_file_no ?? null,
            $record->mls_file_no ?? null,
            $record->new_kangis_file_no ?? null,
            $record->kangis_fileno_resolved ?? null,
        ];

        $variants = [];

        foreach ($candidates as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value === '') {
                continue;
            }

            $variants[strtoupper($value)] = $value;

            $base = trim((string) preg_replace('/\(\s*T\s*\)\s*$/i', '', $value));
            if ($base !== '' && $base !== $value) {
                $variants[strtoupper($base)] = $base;
            } elseif ($base !== '') {
                $withTemp = $base . '(T)';
                $variants[strtoupper($withTemp)] = $withTemp;
            }
        }

        return array_values($variants);
    }

    /**
     * Apply a change map to a table, keyed by any of the given file-number columns.
     *
     * @param  array<string,string>  $map  indexing field => column on the target table
     * @param  array<int,string>     $keyColumns
     */
    private function apply(string $table, array $map, array $changes, array $fileNumbers, array $keyColumns): int
    {
        if (!Schema::connection('sqlsrv')->hasTable($table)) {
            return 0;
        }

        $payload = [];

        foreach ($map as $field => $column) {
            if (array_key_exists($field, $changes) && Schema::connection('sqlsrv')->hasColumn($table, $column)) {
                $payload[$column] = $changes[$field];
            }
        }

        if (empty($payload)) {
            return 0;
        }

        $keyColumns = array_values(array_filter(
            $keyColumns,
            fn ($column) => Schema::connection('sqlsrv')->hasColumn($table, $column)
        ));

        if (empty($keyColumns)) {
            return 0;
        }

        if (Schema::connection('sqlsrv')->hasColumn($table, 'updated_at')) {
            $payload['updated_at'] = now();
        }

        return DB::connection('sqlsrv')
            ->table($table)
            ->where(function ($query) use ($keyColumns, $fileNumbers) {
                foreach ($keyColumns as $index => $column) {
                    $index === 0
                        ? $query->whereIn($column, $fileNumbers)
                        : $query->orWhereIn($column, $fileNumbers);
                }
            })
            ->update($payload);
    }

    /**
     * updateFileNumberTable() already writes FileName, location, lga, plot_no and tp_no on
     * every save. District and street were never carried, so they are added here; the
     * overlap is harmless because both write the same value.
     */
    private function syncFileNumber(array $changes, array $fileNumbers): int
    {
        return $this->apply('fileNumber', [
            'file_title' => 'FileName',
            'location' => 'location',
            'district' => 'district',
            'lga' => 'lga',
            'plot_number' => 'plot_no',
            'tp_no' => 'tp_no',
        ], $changes, $fileNumbers, ['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'temp_fileno']);
    }

    private function syncMlsFileNo(array $changes, array $fileNumbers): int
    {
        return $this->apply('mls_file_no', [
            'file_title' => 'file_name',
            'location' => 'location',
            'district' => 'district',
            'lga' => 'lga',
            'plot_number' => 'plot_no',
            'tp_no' => 'tp_no',
            'land_use_type' => 'land_use',
        ], $changes, $fileNumbers, ['full_file_number', 'old_fileno']);
    }

    /**
     * Property attributes only — see the class docblock on why party_1 / party_2 are not
     * touched.
     */
    private function syncPra(array $changes, array $fileNumbers): int
    {
        return $this->apply('pra', [
            'location' => 'location',
            'street_name' => 'streetName',
            'district' => 'districtName',
            'lga' => 'lgsaOrCity',
            'plot_number' => 'plot_no',
            'tp_no' => 'tp_no',
            'plot_size' => 'plot_size',
            'land_use_type' => 'land_use',
        ], $changes, $fileNumbers, ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno', 'resolved_fileno']);
    }

    /**
     * The file-identity name mirrors, matching what syncPartyNames() maintains inbound.
     */
    private function syncNameMirror(string $table, string $column, string $keyColumn, array $changes, array $fileNumbers): int
    {
        return $this->apply($table, ['file_title' => $column], $changes, $fileNumbers, [$keyColumn]);
    }

    private function syncOssApplications(array $changes, array $fileNumbers): int
    {
        return $this->apply('oss_applications', [
            'location' => 'location',
            'district' => 'district',
            'lga' => 'lga',
            'plot_number' => 'plot_no',
            'land_use_type' => 'land_use',
        ], $changes, $fileNumbers, ['file_no']);
    }

    /**
     * The OSS mother application this file was indexed from. Keyed by the stored link
     * (file_indexings.main_application_id), never by a file-number guess — mother_applications
     * holds several file-number-ish columns and matching loosely could hit another applicant's
     * record.
     */
    private function syncMotherApplication(array $changes, $record): int
    {
        $applicationId = (int) ($record->main_application_id ?? 0);

        if ($applicationId <= 0 || !Schema::connection('sqlsrv')->hasTable('mother_applications')) {
            return 0;
        }

        $map = [
            'district' => 'property_district',
            'lga' => 'property_lga',
            'plot_number' => 'property_plot_no',
            'street_name' => 'property_street_name',
            'land_use_type' => 'land_use',
            'plot_size' => 'plot_size',
        ];

        $payload = [];
        foreach ($map as $field => $column) {
            if (array_key_exists($field, $changes) && Schema::connection('sqlsrv')->hasColumn('mother_applications', $column)) {
                $payload[$column] = $changes[$field];
            }
        }

        if (empty($payload)) {
            return 0;
        }

        $payload['updated_at'] = now();

        return DB::connection('sqlsrv')
            ->table('mother_applications')
            ->where('id', $applicationId)
            ->update($payload);
    }

    private function syncSubApplication(array $changes, $record): int
    {
        $subApplicationId = (int) ($record->subapplication_id ?? 0);

        if ($subApplicationId <= 0 || !Schema::connection('sqlsrv')->hasTable('subapplications')) {
            return 0;
        }

        $map = [
            'location' => 'property_location',
            'district' => 'unit_district',
            'lga' => 'unit_lga',
            'street_name' => 'address_street_name',
            'land_use_type' => 'land_use',
            'plot_size' => 'plot_size',
        ];

        $payload = [];
        foreach ($map as $field => $column) {
            if (array_key_exists($field, $changes) && Schema::connection('sqlsrv')->hasColumn('subapplications', $column)) {
                $payload[$column] = $changes[$field];
            }
        }

        if (empty($payload)) {
            return 0;
        }

        $payload['updated_at'] = now();

        return DB::connection('sqlsrv')
            ->table('subapplications')
            ->where('id', $subApplicationId)
            ->update($payload);
    }
}
