<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Moves an indexed file that turned out to be a duplicate into indexing_duplicates
 * and removes it from the live tables.
 *
 * The record is hard-deleted, not soft-deleted: file_indexings has no SoftDeletes
 * trait and IndexedFileTableController::list() does not filter is_deleted, so
 * flagging a row would leave it sitting in the table. indexing_duplicates keeps a
 * full JSON snapshot of everything removed so the move stays auditable/restorable.
 *
 * mls_file_no is deliberately NOT part of the cascade — deleting the commissioning
 * row would de-commission the file and change its Legal Search timeline. When one
 * exists it is recorded as retained instead.
 */
class IndexingDuplicateService
{
    /**
     * References that represent real work: documents, money, or a file in
     * circulation. A duplicate carrying any of these is refused rather than
     * silently destroyed.
     *
     * scan_reassignment_logs is included because its two foreign keys on
     * file_indexings are NO_ACTION — the delete would fail on the constraint anyway,
     * and a clear message beats a rolled-back SQL error.
     */
    private const BLOCKING_REFERENCES = [
        'scannings'              => 'scanned document(s)',
        'blind_scannings'        => 'blind scan(s)',
        'thumbnails'             => 'thumbnail(s)',
        'pagetypings'            => 'page typing record(s)',
        'file_indexing_bills'    => 'bill(s)',
        'spa_applications'       => 'SPA application(s)',
    ];

    /**
     * Tracker rows are deliberately neither blocked on nor deleted: the movement
     * history is the record of where the physical file went, and that stays true
     * whether or not the file turned out to be a duplicate.
     *
     * Keeping them is safe because both tables are self-contained — they carry
     * tracking_id, current_location, current_handler and movement_history of their
     * own, so a row stays readable after its file_indexings row is gone. They are
     * recorded in the snapshot as retained so the archive shows the link.
     */
    private const RETAINED_REFERENCES = [
        'indexed_file_trackers' => 'file tracker record(s)',
        'file_trackings'        => 'file tracking movement(s)',
    ];

    /**
     * Queue / link / derived rows that would be orphaned by the delete. These are
     * regenerable, so they are snapshotted and removed with the record.
     */
    private const CLEANABLE_REFERENCES = [
        'print_label_batch_items',
        'kangis_print_label_batch_items',
        'sltr_print_label_batch_items',
        'st_print_label_batch_items',
        'dciv_print_label_batch_items',
        'file_indexing_links',
        'barcodes',
        'page_typing_tool_logs',
        'deprecated_records',
    ];

    /** fileNumber columns that can hold the indexed file's number. */
    private const FILE_NUMBER_COLUMNS = ['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no'];

    /**
     * @return array{status:string, ...} status is 'moved', 'not_found', 'blocked' or 'already_moved'
     */
    public function move(int $fileIndexingId, ?string $reason = null, ?string $duplicateOf = null): array
    {
        $conn = DB::connection('sqlsrv');

        $row = $conn->table('file_indexings')->where('id', $fileIndexingId)->first();
        if (!$row) {
            // Already moved by someone else in a parallel session? Report that
            // instead of a bare "not found" so the operator understands.
            $archived = $conn->table('indexing_duplicates')
                ->where('file_indexing_id', $fileIndexingId)
                ->first();

            if ($archived) {
                return [
                    'status' => 'already_moved',
                    'file_number' => $archived->file_number,
                    'moved_by' => $archived->moved_by,
                    'moved_at' => $archived->created_at,
                ];
            }

            return ['status' => 'not_found'];
        }

        $blocking = $this->countBlockingReferences($fileIndexingId);
        if (!empty($blocking)) {
            return [
                'status' => 'blocked',
                'file_number' => $row->file_number,
                'dependencies' => $blocking,
            ];
        }

        // Every number this record is known by. Matching is exact by design: file
        // numbers here differ by whitespace alone ("KN 1" is a legacy land file,
        // "KN1" is a New KANGIS file), so normalising separators before comparing
        // would delete a different file's customer and entity rows.
        $numbers = $this->collectFileNumbers($row);

        $fileNumberRows = $this->findFileNumberRows($numbers, $row->tracking_id ?? null);

        // fileNumber rows can carry numbers the indexing row does not know about.
        foreach ($fileNumberRows as $fnRow) {
            foreach (self::FILE_NUMBER_COLUMNS as $column) {
                $value = trim((string) ($fnRow->{$column} ?? ''));
                if ($value !== '') {
                    $numbers[$value] = $value;
                }
            }
        }
        $numbers = array_values($numbers);

        $customerRows = $conn->table('customers_staging')->whereIn('file_number', $numbers)->get();
        $entityRows   = $conn->table('entities_staging')->whereIn('file_number', $numbers)->get();
        $cleanable    = $this->collectCleanableRows($fileIndexingId);
        $retained     = $this->countRetainedReferences($fileIndexingId);

        $retainedMls = $conn->table('mls_file_no')
            ->where(function ($q) use ($numbers) {
                $q->whereIn('full_file_number', $numbers);
            })
            ->exists();

        $actor = $this->resolveActorName();
        $counts = [];

        $archiveId = $conn->transaction(function () use (
            $conn, $row, $fileIndexingId, $numbers, $fileNumberRows, $customerRows,
            $entityRows, $cleanable, $retained, $retainedMls, $actor, $reason, $duplicateOf, &$counts
        ) {
            $snapshot = [
                'file_indexing'     => (array) $row,
                'fileNumber'        => array_map(fn ($r) => (array) $r, $fileNumberRows),
                'customers_staging' => $customerRows->map(fn ($r) => (array) $r)->all(),
                'entities_staging'  => $entityRows->map(fn ($r) => (array) $r)->all(),
                'child_rows'        => $cleanable,
                'matched_numbers'   => $numbers,
                'mls_file_no_retained' => $retainedMls,
                // Left in place on purpose — see RETAINED_REFERENCES.
                'retained_references'  => $retained,
            ];

            // Clone the record column-for-column. indexing_duplicates mirrors
            // file_indexings, so the archive lines up with the indexed files table
            // and a restore is a straight column-to-column insert. Anything the
            // archive does not have a column for is dropped (it survives in the
            // snapshot), which keeps this working if the two tables drift.
            $archiveRow = array_intersect_key((array) $row, array_flip($this->archiveColumns()));

            // created_at / updated_at stay as the ORIGINAL indexing timestamps —
            // the move has its own moved_at.
            unset($archiveRow['id']);

            $archiveRow += [
                'file_indexing_id'     => $fileIndexingId,
                'duplicate_of'         => $this->clip($duplicateOf, 100),
                'reason'               => $this->clip($reason, 500),
                'mls_file_no_retained' => $retainedMls ? 1 : 0,
                'snapshot'             => json_encode($snapshot),
                'deleted_counts'       => null,
                'moved_by'             => $this->clip($actor, 150),
                'moved_by_id'          => Auth::id(),
                'moved_at'             => now(),
            ];

            // Archive first: if any delete below fails the whole move rolls back,
            // archive row included.
            $archiveId = $conn->table('indexing_duplicates')->insertGetId($archiveRow);

            // Child queue/link rows first, so nothing is left pointing at a
            // file_indexings row that is about to disappear.
            $childCounts = [];
            foreach (self::CLEANABLE_REFERENCES as $table) {
                if (!Schema::connection('sqlsrv')->hasTable($table)) {
                    continue;
                }
                $deleted = $conn->table($table)->where('file_indexing_id', (string) $fileIndexingId)->delete();
                if ($deleted > 0) {
                    $childCounts[$table] = $deleted;
                }
            }

            $counts = [
                'customers_staging' => empty($numbers) ? 0 : $conn->table('customers_staging')->whereIn('file_number', $numbers)->delete(),
                'entities_staging'  => empty($numbers) ? 0 : $conn->table('entities_staging')->whereIn('file_number', $numbers)->delete(),
                'fileNumber'        => 0,
                'file_indexings'    => 0,
                'child_rows'        => $childCounts,
            ];

            $fileNumberIds = array_values(array_filter(array_map(fn ($r) => $r->id ?? null, $fileNumberRows)));
            if (!empty($fileNumberIds)) {
                $counts['fileNumber'] = $conn->table('fileNumber')->whereIn('id', $fileNumberIds)->delete();
            }

            $counts['file_indexings'] = $conn->table('file_indexings')->where('id', $fileIndexingId)->delete();

            $conn->table('indexing_duplicates')
                ->where('id', $archiveId)
                ->update(['deleted_counts' => json_encode($counts)]);

            return $archiveId;
        });

        // A file number the indexing row claims but fileNumber does not hold is not
        // fatal — but it leaves the registry untouched, so make it visible.
        if ($counts['fileNumber'] === 0) {
            Log::warning('IndexingDuplicateService: no fileNumber row matched; registry left untouched', [
                'file_indexing_id' => $fileIndexingId,
                'file_number' => $row->file_number,
                'numbers_tried' => $numbers,
            ]);
        }

        Log::info('Indexed file moved to indexing_duplicates', [
            'indexing_duplicate_id' => $archiveId,
            'file_indexing_id' => $fileIndexingId,
            'file_number' => $row->file_number,
            'counts' => $counts,
            'moved_by' => $actor,
        ]);

        $this->writeAudit($archiveId, $row, $counts, $reason);

        return [
            'status' => 'moved',
            'indexing_duplicate_id' => $archiveId,
            'file_number' => $row->file_number,
            'counts' => $counts,
            'mls_file_no_retained' => $retainedMls,
            'retained_references' => $retained,
        ];
    }

    /** @return array<string,string> table => human count, only for tables with hits */
    private function countBlockingReferences(int $fileIndexingId): array
    {
        $conn = DB::connection('sqlsrv');
        $found = [];

        foreach (self::BLOCKING_REFERENCES as $table => $label) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }

            $count = $conn->table($table)->where('file_indexing_id', (string) $fileIndexingId)->count();
            if ($count > 0) {
                $found[$table] = $count . ' ' . $label;
            }
        }

        // Two NO_ACTION foreign keys, on two different columns.
        if (Schema::connection('sqlsrv')->hasTable('scan_reassignment_logs')) {
            $count = $conn->table('scan_reassignment_logs')
                ->where('from_file_indexing_id', $fileIndexingId)
                ->orWhere('to_file_indexing_id', $fileIndexingId)
                ->count();
            if ($count > 0) {
                $found['scan_reassignment_logs'] = $count . ' scan reassignment log(s)';
            }
        }

        return $found;
    }

    /**
     * Count the tracker rows left in place, so the archive records that the file's
     * movement history survives the move.
     *
     * @return array<string,int>
     */
    private function countRetainedReferences(int $fileIndexingId): array
    {
        $conn = DB::connection('sqlsrv');
        $found = [];

        foreach (array_keys(self::RETAINED_REFERENCES) as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }

            $count = $conn->table($table)->where('file_indexing_id', (string) $fileIndexingId)->count();
            if ($count > 0) {
                $found[$table] = $count;
            }
        }

        return $found;
    }

    /** @return array<string,string> keyed by value so duplicates collapse */
    private function collectFileNumbers(object $row): array
    {
        $numbers = [];

        foreach (['file_number', 'kangis_file_no', 'new_kangis_file_no', 'mls_file_no'] as $column) {
            $value = trim((string) ($row->{$column} ?? ''));
            if ($value !== '' && $value !== '-') {
                $numbers[$value] = $value;
            }
        }

        return $numbers;
    }

    /** @return array<int,object> */
    private function findFileNumberRows(array $numbers, ?string $trackingId): array
    {
        $conn = DB::connection('sqlsrv');
        $values = array_values($numbers);
        $rows = [];

        if (!empty($values)) {
            $rows = $conn->table('fileNumber')
                ->where(function ($query) use ($values) {
                    foreach (self::FILE_NUMBER_COLUMNS as $column) {
                        $query->orWhereIn($column, $values);
                    }
                })
                ->get()
                ->all();
        }

        // Fall back to the tracking id, which survives a file-number correction.
        $trackingId = trim((string) $trackingId);
        if (empty($rows) && $trackingId !== '') {
            $rows = $conn->table('fileNumber')->where('tracking_id', $trackingId)->get()->all();
        }

        return $rows;
    }

    /** @return array<string,array<int,array>> */
    private function collectCleanableRows(int $fileIndexingId): array
    {
        $conn = DB::connection('sqlsrv');
        $out = [];

        foreach (self::CLEANABLE_REFERENCES as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }

            $rows = $conn->table($table)->where('file_indexing_id', (string) $fileIndexingId)->get();
            if ($rows->isNotEmpty()) {
                $out[$table] = $rows->map(fn ($r) => (array) $r)->all();
            }
        }

        return $out;
    }

    /**
     * Column list of indexing_duplicates, used to decide which of the source row's
     * columns the archive can actually hold.
     *
     * @return array<int,string>
     */
    private function archiveColumns(): array
    {
        static $columns = null;

        if ($columns === null) {
            $columns = Schema::connection('sqlsrv')->getColumnListing('indexing_duplicates');
        }

        return $columns;
    }

    private function resolveActorName(): string
    {
        $user = Auth::user();
        if (!$user) {
            return 'system';
        }

        foreach (['name', 'full_name', 'username', 'email'] as $attribute) {
            $value = trim((string) ($user->{$attribute} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'user #' . $user->id;
    }

    private function clip(?string $value, int $length): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $length);
    }

    private function writeAudit(int $archiveId, object $row, array $counts, ?string $reason): void
    {
        try {
            app(AuditService::class)->logAction(
                'DELETED',
                'indexing_duplicate',
                $archiveId,
                ['file_indexing_id' => $row->id, 'file_number' => $row->file_number],
                ['counts' => $counts, 'reason' => $reason],
                sprintf('Moved indexed file %s to indexing duplicates', $row->file_number)
            );
        } catch (\Throwable $e) {
            // Audit must never block the move.
            Log::warning('IndexingDuplicateService: audit write failed', ['error' => $e->getMessage()]);
        }
    }
}
