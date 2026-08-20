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
 *    The holder name is rewritten WHATEVER it currently says. An earlier rule only
 *    touched rows whose stored value still equalled the old file title, which defeated
 *    the point: the reason someone edits the title is that the recorded name is wrong,
 *    so it rarely matches. What bounds the write instead is WHICH ROW is touched —
 *    only the row those screens actually read: the newest row per instrument type per
 *    property (see currentHolderRows()). Older rows of the same type name PREVIOUS
 *    owners and are historical fact, as is an Occupancy Permit sitting behind a
 *    Transfer of Title — that permit names the original allottee, not today's holder.
 *    Grantor / party_1 is never written on PRA at all — that is the previous owner.
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
    /**
     * Instrument types whose party_2 / Grantee IS the current owner of the file.
     *
     * Mortgages, tripartite mortgages and surrender/release are deliberately absent:
     * their party_2 is a lender or the State, not the owner, and renaming it from a
     * file-title edit would corrupt the instrument.
     */
    private const OWNERSHIP_INSTRUMENTS = [
        'Transfer of Title (OP)',
        'Occupancy Permit (OP)',
        'Right of Occupancy',
        'Certificate of Occupancy',
        'Deed of Assignment',
        'Deed of Gift',
        'Power of Attorney',
    ];

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
    public function propagate($before, $after, array $explicitNames = []): array
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
            'pra_current_holder' => fn () => $this->renameCurrentHolder($changes, $fileNumbers),
            'instrument_capture_current_holder' => fn () => $this->renameCaptureHolder($changes, $fileNumbers),
            'instrument_capture' => fn () => $this->syncInstrumentCapture($changes, $fileNumbers),
            'customers_staging' => fn () => $this->syncNameMirror('customers_staging', 'customer_name', 'file_number', $changes, $fileNumbers,
                $explicitNames['customer_name'] ?? null),
            'entities_staging' => fn () => $this->syncNameMirror('entities_staging', 'entity_name', 'file_number', $changes, $fileNumbers,
                $explicitNames['entity_name'] ?? null),
            'oss_applications' => fn () => $this->syncOssApplications($changes, $fileNumbers),
            'oss_applicant_name' => fn () => $this->syncNameMirror('oss_applications', 'applicant_name', 'file_no', $changes, $fileNumbers),
            // Land recommendations: the applicant name only. Its other columns are the
            // recommendation's own findings and must not be overwritten from indexing.
            'land_recommendations' => fn () => $this->apply('land_recommendations',
                ['file_title' => 'applicant_name'],
                $changes, $fileNumbers, ['file_number', 'old_file_number']),
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
    private function syncNameMirror(string $table, string $column, string $keyColumn, array $changes, array $fileNumbers, ?string $explicitName = null): int
    {
        // The Entity & Customer section of the same form writes these columns directly,
        // via updateEntityAndCustomerRecords(), INSIDE the transaction — i.e. before this
        // runs. If the operator deliberately set an entity/customer name different from
        // the file title in the same save, mirroring the title here would silently
        // overwrite what they just typed. Their explicit value wins.
        $explicitName = $explicitName !== null ? trim($explicitName) : '';

        if ($explicitName !== ''
            && isset($changes['file_title'])
            && $this->normalize($explicitName) !== $this->normalize($changes['file_title'])) {
            return 0;
        }

        return $this->apply($table, ['file_title' => $column], $changes, $fileNumbers, [$keyColumn]);
    }

    /**
     * Rename the CURRENT owner on the file's latest ownership instrument, whatever name
     * it currently holds.
     *
     * syncHolderName() only touches rows whose stored name still equals the old file
     * title. That covers rows already mirroring the title, but not the common real case:
     * pra.Grantee holds the true owner while file_indexings.file_title had drifted to
     * something else. The OSS listings read COALESCE(pra.Grantee, fileNumber.FileName),
     * so in that state a rename in File Indexing never showed up on those screens.
     *
     * Scope is deliberately one row: the most recent instrument of an OWNERSHIP type
     * (see OWNERSHIP_INSTRUMENTS). Earlier rows name PREVIOUS owners and are historical
     * fact — they are never touched, and neither is Grantor / party_1 on any row.
     *
     * Note this DOES rewrite a party on a registered instrument. That is intended: File
     * Indexing is treated as the master for who currently holds the file.
     */
    private function renameCurrentHolder(array $changes, array $fileNumbers): int
    {
        $newTitle = $changes['file_title'] ?? null;

        if ($newTitle === null || trim((string) $newTitle) === '') {
            return 0;
        }

        if (!Schema::connection('sqlsrv')->hasTable('pra')
            || !Schema::connection('sqlsrv')->hasColumn('pra', 'instrument_type')) {
            return 0;
        }

        $rows = DB::connection('sqlsrv')->table('pra')
            ->where(function ($q) use ($fileNumbers) {
                $q->whereIn('mlsFNo', $fileNumbers)
                    ->orWhereIn('temp_fileno', $fileNumbers)
                    ->orWhereIn('kangisFileNo', $fileNumbers)
                    ->orWhereIn('NewKANGISFileno', $fileNumbers);
            })
            ->whereIn('instrument_type', self::OWNERSHIP_INSTRUMENTS)
            ->orderByDesc('id')
            ->get(['id', 'instrument_type', 'prop_id', 'parent_prop_id', 'Grantee', 'party_2']);

        if ($rows->isEmpty()) {
            return 0;
        }

        $affected = 0;

        foreach ($this->currentHolderRows($rows) as $row) {
            $payload = [];

            foreach (['Grantee', 'party_2'] as $column) {
                if (Schema::connection('sqlsrv')->hasColumn('pra', $column)
                    && $this->normalize($row->$column ?? '') !== $this->normalize($newTitle)) {
                    $payload[$column] = $newTitle;
                }
            }

            if (empty($payload)) {
                continue;
            }

            Log::info('FileIndexingPropagation: renaming current holder', [
                'pra_id' => $row->id,
                'instrument_type' => $row->instrument_type,
                'from' => $row->Grantee ?? null,
                'to' => $newTitle,
            ]);

            $affected += DB::connection('sqlsrv')->table('pra')->where('id', $row->id)->update($payload);
        }

        return $affected;
    }

    /**
     * Of the ownership rows found for a file, the one that states who holds it NOW.
     *
     * Ownership instruments come in two kinds, and only one row is ever "current":
     *
     *   GRANT     (Occupancy Permit, Right of Occupancy, Certificate of Occupancy) —
     *             the State allocating the land. Its grantee is the ORIGINAL ALLOTTEE.
     *   TRANSFER  (Transfer of Title, Deed of Assignment, Deed of Gift, Power of
     *             Attorney) — the holding moving to someone else.
     *
     * So: if the property has any transfer, the newest transfer names the current
     * holder and every grant behind it is allocation history. With no transfer at all,
     * the newest grant IS the current holding. Real data shows why this matters —
     * prop 147221 carries an OP naming "BRUCE HANE" and a ToT naming "SALISU UMAR";
     * renaming the OP from a file-title edit would rewrite who the land was allocated to.
     *
     * Rows are grouped by property (parent_prop_id, else prop_id) and ordered by id, the
     * same way the OSS listings pick the row they display — ids, not transaction_date,
     * because those are user-entered instrument dates that routinely put an OP after the
     * ToT superseding it.
     *
     * @param  \Illuminate\Support\Collection  $rows
     * @return array<int,object>
     */
    private function currentHolderRows($rows): array
    {
        $byGroup = [];

        foreach ($rows as $row) {
            $group = trim((string) ($row->parent_prop_id ?? '')) !== ''
                ? trim((string) $row->parent_prop_id)
                : trim((string) ($row->prop_id ?? ''));

            $kind = $this->isTransferInstrument((string) $row->instrument_type) ? 'transfer' : 'grant';

            $current = $byGroup[$group][$kind] ?? null;
            if ($current === null || (int) $row->id > (int) $current->id) {
                $byGroup[$group][$kind] = $row;
            }
        }

        $selected = [];

        foreach ($byGroup as $kinds) {
            // A transfer always wins: it is the later statement of who holds the land.
            $selected[] = $kinds['transfer'] ?? $kinds['grant'];
        }

        return array_values(array_filter($selected));
    }

    /** Does this instrument move the holding to someone else (rather than grant it)? */
    private function isTransferInstrument(string $instrumentType): bool
    {
        foreach (['Transfer of Title', 'Deed of Assignment', 'Deed of Gift', 'Power of Attorney'] as $needle) {
            if (stripos($instrumentType, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * The same unconditional rename for the capture staging rows.
     *
     * The OSS OP listing unions instrument_capture in for OP records that have no PRA row,
     * and reads `ic.party_1_name as Grantee` there — the permit holder for an OP is
     * party_1, not party_2. Nothing was rewriting that column, so an FC/IC-sourced row
     * kept its old name after a file-title edit no matter what.
     *
     * party_2_name is written too: on the ownership deeds it is the party taking title.
     * Nothing is written on a capture row that PRA has already superseded with a Transfer
     * of Title — behind a transfer the capture row names the original allottee or an
     * earlier owner, which is history, not the current holder.
     */
    private function renameCaptureHolder(array $changes, array $fileNumbers): int
    {
        $newTitle = $changes['file_title'] ?? null;

        if ($newTitle === null || trim((string) $newTitle) === '') {
            return 0;
        }

        if (!Schema::connection('sqlsrv')->hasTable('instrument_capture')
            || !Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'instrument_type')) {
            return 0;
        }

        $keyColumns = array_values(array_filter(
            ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'],
            fn ($column) => Schema::connection('sqlsrv')->hasColumn('instrument_capture', $column)
        ));

        if (empty($keyColumns)) {
            return 0;
        }

        $rows = DB::connection('sqlsrv')->table('instrument_capture')
            ->where(function ($query) use ($keyColumns, $fileNumbers) {
                foreach ($keyColumns as $index => $key) {
                    $index === 0
                        ? $query->whereIn($key, $fileNumbers)
                        : $query->orWhereIn($key, $fileNumbers);
                }
            })
            ->whereIn('instrument_type', self::OWNERSHIP_INSTRUMENTS)
            ->orderByDesc('id')
            ->get(['id', 'instrument_type', 'prop_id', 'party_1_name', 'party_2_name']);

        if ($rows->isEmpty()) {
            return 0;
        }

        // instrument_capture has no parent_prop_id; currentHolderRows() reads it as absent
        // and groups on prop_id, which is what the listing's IC branch partitions by.
        $candidates = $this->currentHolderRows($rows);
        $affected = 0;

        foreach ($candidates as $row) {
            $instrumentType = (string) $row->instrument_type;

            // PRA holds the authoritative transfer chain. If a Transfer of Title exists for
            // this property, any capture row that is not itself that transfer has been
            // superseded and names an earlier party.
            if (stripos($instrumentType, 'Transfer of Title') === false
                && $this->propertyHasTransferOfTitle($row->prop_id ?? null)) {
                continue;
            }

            $payload = [];

            if (Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'party_2_name')
                && $this->normalize($row->party_2_name ?? '') !== $this->normalize($newTitle)) {
                $payload['party_2_name'] = $newTitle;
            }

            // For an Occupancy Permit the holder is party_1 — and party_1_name is the column
            // the OSS listing reads as the Grantee for IC-sourced rows.
            if (stripos($instrumentType, 'Occupancy Permit') !== false
                && Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'party_1_name')
                && $this->normalize($row->party_1_name ?? '') !== $this->normalize($newTitle)) {
                $payload['party_1_name'] = $newTitle;
            }

            if (empty($payload)) {
                continue;
            }

            Log::info('FileIndexingPropagation: renaming capture holder', [
                'instrument_capture_id' => $row->id,
                'instrument_type' => $row->instrument_type,
                'columns' => array_keys($payload),
                'to' => $newTitle,
            ]);

            $affected += DB::connection('sqlsrv')->table('instrument_capture')
                ->where('id', $row->id)
                ->update($payload);
        }

        return $affected;
    }

    /** Does this property already carry a Transfer of Title in PRA? */
    private function propertyHasTransferOfTitle($propId): bool
    {
        $propId = trim((string) ($propId ?? ''));

        if ($propId === '' || $propId === '0') {
            return false;
        }

        if (!Schema::connection('sqlsrv')->hasTable('pra')
            || !Schema::connection('sqlsrv')->hasColumn('pra', 'instrument_type')) {
            return false;
        }

        return DB::connection('sqlsrv')->table('pra')
            ->where(function ($query) use ($propId) {
                $query->where('prop_id', $propId)->orWhere('parent_prop_id', $propId);
            })
            ->where('instrument_type', 'LIKE', '%Transfer of Title%')
            ->exists();
    }

    /**
     * Property attributes on the capture staging rows. The OSS listings read from
     * instrument_capture for FC-sourced records, so leaving it out left those rows
     * showing pre-edit values.
     */
    private function syncInstrumentCapture(array $changes, array $fileNumbers): int
    {
        return $this->apply('instrument_capture', [
            'district' => 'district',
            'lga' => 'lga',
            'tp_no' => 'tp_no',
            'plot_size' => 'plot_size',
            'land_use_type' => 'land_use',
        ], $changes, $fileNumbers, ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno']);
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
            // The owner's name as one string. first_name / surname / corporate_name are
            // still never written: they record who SUBMITTED the application, and
            // splitting a file title back into those parts is guesswork that would
            // corrupt the applicant record.
            'file_title' => 'owner_fullname',
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
