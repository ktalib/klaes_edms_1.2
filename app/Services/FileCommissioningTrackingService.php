<?php

namespace App\Services;

use App\Models\FileTracker;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Default In-process In-transit Tracking (DIIT).
 *
 * A file commissioned through KLAES is a NEW file: it is created at the File
 * Commissioning Office and then travels from office to office for processing
 * and documentation. It is not in the registry/archive yet, and until someone
 * logs it out it has no file_tracker row at all — so before DIIT such a file
 * was invisible to the File Log Table and reported "In Archive" by the location
 * resolver.
 *
 * DIIT gives every KLAES-commissioned file a default first tracking line:
 *
 *      File Commissioning Office (FCO) — "File Commissioning" — commissioning date
 *
 * The line is DERIVED from mls_file_no rather than stored, so every file already
 * commissioned is covered the moment this ships and every future commissioning
 * is covered automatically — there is nothing to backfill and nothing to keep in
 * sync. mls_file_no.full_file_number is the authoritative record of a KLAES
 * commissioning (the same authority LegalSearchService::resolveCommissioningInfo()
 * uses for the Legal Search "File Commissioning" row).
 *
 * The DIIT state ends at the file's FIRST REAL tracking record: once a
 * file_tracker row exists, real tracking governs the file's location and the
 * commissioning line becomes the opening entry of its movement history.
 */
class FileCommissioningTrackingService
{
    /** The office a file is commissioned at (offices.office_code = 'FCO'). */
    public const OFFICE_CODE = 'FCO';
    public const OFFICE_NAME = 'File Commissioning Office';
    public const OFFICE_DEPARTMENT = 'Land';

    /** Badge text rendered for the default line (movement entry `status_label`). */
    public const LABEL = 'File Commissioning';

    /**
     * KLAES commissioning went live in 2026; the handful of older mls_file_no
     * rows carry a legacy year and must not sprout a commissioning line.
     */
    public const MIN_YEAR = 2026;

    protected string $connection = 'sqlsrv';

    /**
     * Per-request map of normalized (UPPER+trim) file number => commissioning
     * info array, or false when the file is not KLAES-commissioned. Primed one
     * page at a time so list screens run a single query instead of one per row.
     *
     * @var array<string, array<string,mixed>|false>
     */
    protected array $cache = [];

    /**
     * Base scope over the commissioning register: live rows only, from the year
     * KLAES commissioning started. Callers add their own filters/projection.
     */
    public function baseQuery(): Builder
    {
        return DB::connection($this->connection)
            ->table('mls_file_no')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->where(function ($q) {
                $q->where('year', '>=', self::MIN_YEAR)
                    ->orWhereYear('commissioning_date', '>=', self::MIN_YEAR);
            });
    }

    /**
     * Rows in the commissioning register that have NO file_tracker record yet —
     * the files whose only tracking line is the DIIT one. Once a file is logged
     * out for real it drops out of here and its real tracker takes over.
     */
    public function untrackedQuery(): Builder
    {
        return $this->baseQuery()->whereNotExists(function ($sub) {
            // Plain equality (no UPPER/TRIM): the SQL_Latin1_General_CP1_CI_AS
            // collation already matches case-insensitively and ignores trailing
            // spaces, so this can seek the file_number index.
            $sub->selectRaw('1')
                ->from('file_tracker')
                ->whereColumn('file_tracker.file_number', 'mls_file_no.full_file_number');
        });
    }

    /**
     * Bulk-load the commissioning info for a whole page of file numbers in one
     * query. Mirrors the priming pattern the File Log Table already uses for
     * titles/locations — without it, infoFor() costs one query per row.
     */
    public function prime(iterable $fileNumbers): void
    {
        $wanted = [];
        foreach ($fileNumbers as $number) {
            $key = $this->key($number);
            if ($key !== '' && !array_key_exists($key, $this->cache)) {
                $wanted[$key] = trim((string) $number);
            }
        }

        if (empty($wanted)) {
            return;
        }

        try {
            // SQL Server caps a statement at 2,100 parameters.
            foreach (array_chunk($wanted, 1000, true) as $chunk) {
                $rows = $this->baseQuery()
                    ->whereIn('full_file_number', array_values($chunk))
                    ->orderBy('id')
                    ->get(['full_file_number', 'commissioning_date', 'commissioning_time', 'created_by', 'created_at', 'tracking_id', 'source', 'file_name']);

                $found = [];
                foreach ($rows as $row) {
                    $found[$this->key($row->full_file_number)] = $row;
                }

                foreach (array_keys($chunk) as $key) {
                    $this->cache[$key] = isset($found[$key]) ? $this->hydrate($found[$key]) : false;
                }
            }
        } catch (\Throwable $e) {
            // A transient failure must not break the screen — leave the cache
            // alone so infoFor() falls back to per-row lookups.
            Log::warning('Unable to prime commissioning (DIIT) cache', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Commissioning info for a single file, or null when the file was not
     * commissioned through KLAES.
     *
     * @return array{file_number:string, commissioned_at:Carbon, commissioned_by:?string, tracking_id:?string, source:?string}|null
     */
    public function infoFor(?string $fileNumber): ?array
    {
        $key = $this->key($fileNumber);
        if ($key === '') {
            return null;
        }

        if (!array_key_exists($key, $this->cache)) {
            try {
                $row = $this->baseQuery()
                    ->where('full_file_number', trim((string) $fileNumber))
                    ->orderBy('id')
                    ->first(['full_file_number', 'commissioning_date', 'commissioning_time', 'created_by', 'created_at', 'tracking_id', 'source', 'file_name']);

                $this->cache[$key] = $row ? $this->hydrate($row) : false;
            } catch (\Throwable $e) {
                return null;
            }
        }

        return $this->cache[$key] ?: null;
    }

    public function isCommissioned(?string $fileNumber): bool
    {
        return $this->infoFor($fileNumber) !== null;
    }

    /**
     * Turn a commissioning-register row into the info array used everywhere else.
     *
     * @param object $row
     * @return array<string,mixed>
     */
    public function hydrate($row): array
    {
        return [
            'file_number'     => trim((string) $row->full_file_number),
            'file_title'      => isset($row->file_name) ? trim((string) $row->file_name) : null,
            'commissioned_at' => $this->commissionedAt($row),
            'commissioned_by' => trim((string) ($row->created_by ?? '')) ?: null,
            'tracking_id'     => trim((string) ($row->tracking_id ?? '')) ?: null,
            'source'          => trim((string) ($row->source ?? '')) ?: null,
        ];
    }

    /**
     * The moment the file was commissioned: the hand-entered commissioning date
     * (plus time when recorded), falling back to the register row's created_at.
     */
    protected function commissionedAt($row): Carbon
    {
        $date = trim((string) ($row->commissioning_date ?? ''));
        $time = trim((string) ($row->commissioning_time ?? ''));

        if ($date !== '') {
            $stamp = substr($date, 0, 10) . ($time !== '' ? ' ' . substr($time, 0, 8) : ' 00:00:00');
            $parsed = rescue(fn () => Carbon::parse($stamp), null, false);
            if ($parsed) {
                return $parsed;
            }
        }

        return rescue(fn () => Carbon::parse($row->created_at), Carbon::now(), false);
    }

    /**
     * The default line itself, in the movement_log shape the tracking sheet and
     * the File Log Table already render (see convertMovementLogEntry() in
     * create_file_tracker_page/partials/js.blade.php). `status_label` drives the
     * badge text, so the row reads "File Commissioning" rather than "Log-out".
     *
     * @param array<string,mixed> $info
     * @param Carbon|null $closedAt When the file left the commissioning office —
     *        the first real movement's log-in. Left open (still in transit at
     *        FCO) when the file has no real tracking yet.
     * @return array<string,mixed>
     */
    public function movementEntry(array $info, ?Carbon $closedAt = null): array
    {
        /** @var Carbon $at */
        $at = $info['commissioned_at'];
        $holder = $info['commissioned_by'] ?: 'File Commissioning Office';

        // A legacy file recommissioned into KLAES can carry movements older than its
        // commissioning date; the line still closes (the file did leave the office),
        // just never before it opened.
        if ($closedAt !== null && $closedAt->lessThan($at)) {
            $closedAt = $at->copy();
        }

        return [
            'log_id'                 => 'DIIT-' . strtoupper(substr(sha1('diit|' . $info['file_number']), 0, 10)),
            'office_code'            => self::OFFICE_CODE,
            'office_name'            => self::OFFICE_NAME,
            'log_in_date'            => $at->format('Y-m-d'),
            'log_in_time'            => $at->format('H:i'),
            'log_out_date'           => $closedAt ? $closedAt->format('Y-m-d') : null,
            'log_out_time'           => $closedAt ? $closedAt->format('H:i') : null,
            'status'                 => $closedAt ? 'completed' : 'active',
            'status_label'           => self::LABEL,
            'notes'                  => 'File commissioned in KLAES — in process at the File Commissioning Office.',
            'timestamp'              => $at->toIso8601String(),
            'user_name'              => $info['commissioned_by'] ?: 'System',
            'receiving_office_code'  => self::OFFICE_CODE,
            'receiving_office_name'  => self::OFFICE_NAME,
            'receiving_officer_id'   => null,
            'receiving_officer_name' => $holder,
            'origin_office_code'     => self::OFFICE_CODE,
            'origin_office_name'     => self::OFFICE_NAME,
            'origin_office_department' => self::OFFICE_DEPARTMENT,
            // Marks the row as the derived commissioning line rather than a real
            // logged movement, so the UI can render it read-only.
            '_diit'                  => true,
        ];
    }

    /**
     * An UNSAVED FileTracker carrying nothing but the commissioning line, so a
     * commissioned-but-never-logged-out file can be listed by the File Log Table
     * through exactly the same decoration/rendering path as a real tracker.
     *
     * @param array<string,mixed> $info
     */
    public function syntheticTracker(array $info): FileTracker
    {
        /** @var Carbon $at */
        $at = $info['commissioned_at'];

        $tracker = new FileTracker();
        $tracker->forceFill([
            'id'                    => null,
            'tracking_id'           => $info['tracking_id'] ?: ('DIIT-' . $info['file_number']),
            'file_number'           => $info['file_number'],
            'file_title'            => $info['file_title'],
            'file_type'             => 'File',
            'priority'              => FileTracker::PRIORITY_MEDIUM,
            'status'                => FileTracker::STATUS_ACTIVE,
            'department'            => self::OFFICE_DEPARTMENT,
            'created_by_name'       => $info['commissioned_by'],
            'description'           => 'Default In-process In-transit Tracking — file commissioned in KLAES.',
            'movement_log'          => [$this->movementEntry($info)],
            'current_office_code'   => self::OFFICE_CODE,
            'current_office_name'   => self::OFFICE_NAME,
            'origin_office_code'    => self::OFFICE_CODE,
            'origin_office_name'    => self::OFFICE_NAME,
            'origin_office_department' => self::OFFICE_DEPARTMENT,
            'receiving_office_code' => self::OFFICE_CODE,
            'receiving_office_name' => self::OFFICE_NAME,
            'receiving_officer_name' => $info['commissioned_by'],
            'total_offices'         => 1,
            'completed_offices'     => 1,
            'date_created'          => $at,
            'created_at'            => $at,
            'updated_at'            => $at,
        ]);

        // Not a database row: never let a stray save() write it, and let the API
        // payload/front end tell it apart from a real tracker.
        $tracker->exists = false;
        $tracker->setAttribute('is_diit', true);

        return $tracker;
    }

    /** Normalized cache key for a file number. */
    protected function key(?string $fileNumber): string
    {
        return mb_strtoupper(trim((string) $fileNumber));
    }
}
