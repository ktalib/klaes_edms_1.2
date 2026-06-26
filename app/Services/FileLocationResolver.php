<?php

namespace App\Services;

use App\Models\FileIndexing;
use App\Models\FileTracker;
use Illuminate\Support\Facades\DB;

/**
 * Quick Search & File Location engine.
 *
 * Given a file number, returns a definitive location + status + next action so
 * that no search ever ends without a clear outcome. Shared by the web Quick
 * Search and the SCB Monitor mobile endpoints.
 *
 * Precedence:
 *   1. file_tracker.status = ACTIVE -> logged out -> IN_TRANSIT (ground truth:
 *      a physically logged-out file wins over a stale SCB found/not-found override).
 *   2. Manual override (location_status_manual + tracking_status on the indexing row).
 *   3. file_tracker.status = COMPLETED -> logged in -> IN_ARCHIVE
 *        - file_tracker.status = CANCELLED -> ignore, fall through
 *   4. No active tracker -> parse prefix + year, match config/file_ranges.php
 *        - zone=archive + scanned/indexed -> IN_ARCHIVE
 *        - zone=archive + not scanned      -> FILE_NOT_FOUND (archive-missing)
 *        - zone=pool                        -> IN_POOL_OFFICE
 *   3. No tracker and no range match       -> REFER_TO_ORIGINAL_REGISTRY
 */
class FileLocationResolver
{
    public const STATUS_IN_TRANSIT  = 'IN_TRANSIT';
    public const STATUS_IN_ARCHIVE  = 'IN_ARCHIVE';
    public const STATUS_IN_POOL     = 'IN_POOL_OFFICE';
    public const STATUS_NOT_FOUND   = 'FILE_NOT_FOUND';
    public const STATUS_REFER       = 'REFER_TO_ORIGINAL_REGISTRY';

    // SCB-response outcome statuses (set when an SCB Monitor replies Found / Not Found).
    public const STATUS_IN_ARCHIVE_FOUND     = 'IN_ARCHIVE_FOUND';
    public const STATUS_IN_ARCHIVE_NOT_FOUND = 'IN_ARCHIVE_NOT_FOUND';
    public const STATUS_IN_POOL_FOUND        = 'IN_POOL_OFFICE_FOUND';
    public const STATUS_IN_POOL_NOT_FOUND    = 'IN_POOL_OFFICE_NOT_FOUND';

    // Non-indexed files: cannot be located by normal search → blind request to SCB.
    public const STATUS_PENDING_FILE       = 'PENDING_FILE';
    public const STATUS_BLIND_REQUEST_SENT = 'BLIND_REQUEST_SENT';

    /** Cache of shelf lookups within a single request. */
    protected array $rackShelfCache = [];

    /** Duplicate-registry flag for the file currently being resolved (set per resolve()). */
    protected ?array $currentDuplicateFlag = null;

    /**
     * Resolve the location/status for a file number.
     *
     * @return array{
     *   file_number:string, status:string, registry:?string, zone:?string,
     *   current_location:?string, rack_shelf:?string, file_tracker_id:?int,
     *   tracker:?FileTracker, indexing:?FileIndexing,
     *   next_action:string, slip_variant:?string, can_send_fr:bool
     * }
     */
    public function resolve(string $fileNumber): array
    {
        $fileNumber = trim($fileNumber);
        $variants   = $this->variants($fileNumber);

        // Flag if this file number is registered in duplicate_fileno (CofO collected/ready,
        // duplicate, temporary, or withdrawn/cancelled/revoked). Surfaced on every outcome.
        $this->currentDuplicateFlag = $this->duplicateFlagFor($variants);

        $indexing = $this->findIndexing($variants);
        $tracker  = $this->findTracker($variants);

        // ── 1. An ACTIVE tracker means the file is physically logged out -> IN_TRANSIT.
        //        This is the ground truth: once the file has been logged, it wins over
        //        any stale SCB found/not-found override left on the indexing row (which
        //        was set while the file was still in the archive awaiting collection).
        if ($tracker
            && strtoupper((string) $tracker->status) === FileTracker::STATUS_ACTIVE
            && !empty($tracker->movement_log)   // guard against a stuck ACTIVE tracker whose movements were all deleted
        ) {                                     // (a movement still "pending_acceptance" at its destination is still in transit)
            $location = $tracker->current_office_name
                ?: $tracker->receiving_office_name
                ?: $tracker->destination;

            return $this->result($fileNumber, self::STATUS_IN_TRANSIT, [
                'registry'         => $tracker->origin_office_name,
                'current_location' => $location,
                'file_tracker_id'  => $tracker->id,
                'tracker'          => $tracker,
                'indexing'         => $indexing,
                'next_action'      => 'Print Tracking Confirmation Slip',
                'slip_variant'     => 'tracking_confirmation',
            ]);
        }

        // ── 0. Manual override set from the File Quick Search interface ──
        if ($indexing && !empty($indexing->location_status_manual) && !empty($indexing->tracking_status)) {
            $meta = $this->actionMetaFor($indexing->tracking_status);
            return $this->result($fileNumber, $indexing->tracking_status, array_merge([
                'current_location' => $indexing->current_location,
                'file_tracker_id'  => $indexing->file_tracker_id,
                'indexing'         => $indexing,
                'tracker'          => $tracker,
                'manual'           => true,
            ], $meta));
        }

        // ── 2. Tracker logged back in -> Archive ──
        if ($tracker && strtoupper((string) $tracker->status) === FileTracker::STATUS_COMPLETED) {
            // Logged back in / with us -> Archive (must be confirmed by SCB before logging out).
            $rackShelf = $this->getRackShelf($fileNumber, $indexing);
            $range     = $this->matchRange($fileNumber);
            $registry  = $range['registry'] ?? null;

            return $this->result($fileNumber, self::STATUS_IN_ARCHIVE, array_merge([
                'registry'         => $registry,
                'zone'             => 'archive',
                'current_location' => $this->archiveLocation($registry, $rackShelf),
                'rack_shelf'       => $rackShelf,
                'file_tracker_id'  => $tracker->id,
                'tracker'          => $tracker,
                'indexing'         => $indexing,
            ], $this->actionMetaFor(self::STATUS_IN_ARCHIVE)));
        }

        // ── 3. Not indexed at all -> Pending File (blind request) ──
        if ($indexing === null) {
            return $this->result($fileNumber, self::STATUS_PENDING_FILE, array_merge([
                'current_location' => 'Not indexed',
            ], $this->actionMetaFor(self::STATUS_PENDING_FILE)));
        }

        // ── 4. Indexed -> registry range lookup ──
        $range = $this->matchRange($fileNumber);

        if ($range === null) {
            // Indexed but outside any known registry range -> refer.
            return $this->result($fileNumber, self::STATUS_REFER, array_merge([
                'indexing' => $indexing,
            ], $this->actionMetaFor(self::STATUS_REFER)));
        }

        if ($range['zone'] === 'archive') {
            $rackShelf = $this->getRackShelf($fileNumber, $indexing);
            return $this->result($fileNumber, self::STATUS_IN_ARCHIVE, array_merge([
                'registry'         => $range['registry'],
                'zone'             => 'archive',
                'current_location' => $this->archiveLocation($range['registry'], $rackShelf),
                'rack_shelf'       => $rackShelf,
                'indexing'         => $indexing,
            ], $this->actionMetaFor(self::STATUS_IN_ARCHIVE)));
        }

        // zone = pool
        return $this->result($fileNumber, self::STATUS_IN_POOL, array_merge([
            'registry'         => $range['registry'],
            'zone'             => 'pool',
            'current_location' => $range['registry'] . ' — Pool Office',
            'indexing'         => $indexing,
        ], $this->actionMetaFor(self::STATUS_IN_POOL)));
    }

    /**
     * Persist the resolved snapshot onto the matching file_indexings row.
     */
    public function persist(array $result): void
    {
        /** @var FileIndexing|null $indexing */
        $indexing = $result['indexing'] ?? null;
        if (!$indexing) {
            return;
        }

        $indexing->forceFill([
            'tracking_status'  => $result['status'],
            'current_location' => $result['current_location'],
            'file_tracker_id'  => $result['file_tracker_id'],
        ])->save();
    }

    /**
     * Parse a file number into a registry range, longest-prefix first.
     *
     * @return array{zone:string, registry:string}|null
     */
    public function matchRange(string $fileNumber): ?array
    {
        $parsed = $this->parse($fileNumber);
        if ($parsed === null) {
            return null;
        }
        [$prefix, $year] = $parsed;

        $ranges = (array) config('file_ranges.ranges', []);

        // Candidate prefixes from longest to shortest (e.g. CON-RES-RC, CON-RES, CON, ...).
        $segments = explode('-', $prefix);
        for ($take = count($segments); $take >= 1; $take--) {
            $candidate = implode('-', array_slice($segments, 0, $take));
            if (!isset($ranges[$candidate])) {
                continue;
            }
            foreach ($ranges[$candidate] as $range) {
                if ($year >= (int) $range['from'] && $year <= (int) $range['to']) {
                    return ['zone' => $range['zone'], 'registry' => $range['registry']];
                }
            }
        }

        return null;
    }

    /**
     * Split "PREFIX-YYYY-..." into [prefix, year]. Returns null if no 4-digit
     * year segment is present.
     *
     * @return array{0:string,1:int}|null
     */
    public function parse(string $fileNumber): ?array
    {
        $normalized = strtoupper(trim($fileNumber));
        // Strip a trailing temp marker like "(T)".
        $normalized = preg_replace('/\s*\(\s*T\s*\)\s*$/', '', $normalized);

        if (preg_match('/^([A-Z][A-Z\-]*?)-(\d{4})(?:\-|$)/', $normalized, $m)) {
            return [$m[1], (int) $m[2]];
        }

        return null;
    }

    /**
     * Public rack/shelf lookup for a file number — its assigned home shelf,
     * independent of whether the file is currently in transit. Resolves the
     * indexing row itself for the fallback path.
     */
    public function rackShelfFor(string $fileNumber): ?string
    {
        $indexing = $this->findIndexing($this->variants($fileNumber));
        return $this->getRackShelf($fileNumber, $indexing);
    }

    // ───────────────────────────── helpers ─────────────────────────────

    protected function result(string $fileNumber, string $status, array $extra): array
    {
        return array_merge([
            'file_number'      => $fileNumber,
            'status'           => $status,
            'registry'         => null,
            'zone'             => null,
            'current_location' => null,
            'rack_shelf'       => null,
            'file_tracker_id'  => null,
            'tracker'          => null,
            'indexing'         => null,
            'next_action'      => '',
            'slip_variant'     => null,
            'can_send_fr'      => false,
            'can_log'          => false,
            'is_blind'         => false,
            'manual'           => false,
            'duplicate_flag'   => $this->currentDuplicateFlag,
        ], $extra);
    }

    /**
     * Look up the file in duplicate_fileno and, if present, return a normalized
     * badge payload (category + display label + colour). Surfaced on every Quick
     * Search / mobile File Search result so the front desk can see at a glance
     * that the file is flagged as CofO-collected/ready, a duplicate, a temporary
     * file, or withdrawn/cancelled/revoked.
     *
     * @return array{category:string,label:string,color:string,comment:?string,registry:?string}|null
     */
    protected function duplicateFlagFor(array $variants): ?array
    {
        if (empty($variants)) {
            return null;
        }

        $row = DB::connection('sqlsrv')
            ->table('duplicate_fileno')
            ->whereIn('file_number', $variants)
            ->orderByDesc('id')
            ->first(['category', 'comment', 'registry']);

        if (!$row || empty($row->category)) {
            return null;
        }

        // The comment chip is only useful when it adds information beyond the category
        // itself (the import often copies the category into the comment column verbatim).
        $comment = trim((string) ($row->comment ?? ''));
        if ($comment === '' || strtoupper($comment) === strtoupper(trim((string) $row->category))) {
            $comment = null;
        }

        return array_merge(
            $this->duplicateCategoryMeta($row->category),
            ['comment' => $comment, 'registry' => $row->registry ?: null]
        );
    }

    /**
     * Map a raw duplicate_fileno.category value to a display label + badge colour.
     */
    protected function duplicateCategoryMeta(string $category): array
    {
        $key = strtoupper(trim($category));

        return match ($key) {
            '[COFO_COLLECTED]' => ['category' => $category, 'label' => 'CofO Collected', 'color' => '#16a34a'],
            '[COFO_READY]'     => ['category' => $category, 'label' => 'CofO Ready',     'color' => '#2563eb'],
            'DUPLICATE FILES'  => ['category' => $category, 'label' => 'Duplicate File', 'color' => '#d97706'],
            'TEMP FILES'       => ['category' => $category, 'label' => 'Temporary File', 'color' => '#7c3aed'],
            'W/C/R FILES'      => ['category' => $category, 'label' => 'Withdrawn / Cancelled / Revoked', 'color' => '#dc2626'],
            default            => ['category' => $category, 'label' => trim($category, '[]'), 'color' => '#475569'],
        };
    }

    /**
     * Map a status to its next-action label, slip variant, and the available
     * actions (send FSR, log file, blind request). Used for manual/SCB-driven
     * statuses and by callers.
     *
     * @return array{next_action:string, slip_variant:?string, can_send_fr:bool, can_log:bool, is_blind:bool}
     */
    public function actionMetaFor(string $status): array
    {
        $base = ['next_action' => '', 'slip_variant' => null, 'can_send_fr' => false, 'can_log' => false, 'is_blind' => false];

        return array_merge($base, match ($status) {
            self::STATUS_IN_TRANSIT =>
                ['next_action' => 'Print Tracking Confirmation Slip', 'slip_variant' => 'tracking_confirmation'],

            // Archive / Pool both require an SCB physical confirmation before logging.
            self::STATUS_IN_ARCHIVE =>
                ['next_action' => 'Send File Search Request to SCB Monitor', 'can_send_fr' => true],
            self::STATUS_IN_POOL =>
                ['next_action' => 'Send File Search Request to SCB Monitor', 'can_send_fr' => true],

            // SCB replied FOUND -> log the file (no slip yet; not logged out).
            self::STATUS_IN_ARCHIVE_FOUND, self::STATUS_IN_POOL_FOUND =>
                ['next_action' => 'Log the file', 'can_log' => true],

            // SCB replied NOT FOUND -> refer to original registry.
            self::STATUS_IN_ARCHIVE_NOT_FOUND, self::STATUS_IN_POOL_NOT_FOUND =>
                ['next_action' => 'Refer to Original Registry', 'slip_variant' => 'refer_registry'],

            // Non-indexed file -> blind request to SCB.
            self::STATUS_PENDING_FILE =>
                ['next_action' => 'Send Blind Request to SCB Monitor', 'can_send_fr' => true, 'is_blind' => true],
            self::STATUS_BLIND_REQUEST_SENT =>
                ['next_action' => 'Awaiting SCB feedback', 'is_blind' => true],

            self::STATUS_NOT_FOUND =>
                ['next_action' => 'Print Missing File Confirmation Slip', 'slip_variant' => 'missing'],

            // Indexed but outside any known registry range — send it to the SCB to
            // physically search first. Only if the SCB reports Not Found does the
            // outcome (IN_ARCHIVE_NOT_FOUND) offer the Refer-to-Original-Registry slip.
            self::STATUS_REFER =>
                ['next_action' => 'Send File Search Request to SCB Monitor', 'can_send_fr' => true],

            default => [],
        });
    }

    /**
     * Combine a base location status (IN_ARCHIVE / IN_POOL_OFFICE / PENDING_FILE)
     * with an SCB found/not-found result into the outcome status.
     */
    public function combineScbOutcome(?string $resolvedStatus, bool $found): string
    {
        $isPool = $resolvedStatus === self::STATUS_IN_POOL;
        if ($found) {
            return $isPool ? self::STATUS_IN_POOL_FOUND : self::STATUS_IN_ARCHIVE_FOUND;
        }
        return $isPool ? self::STATUS_IN_POOL_NOT_FOUND : self::STATUS_IN_ARCHIVE_NOT_FOUND;
    }

    protected function archiveLocation(?string $registry, ?string $rackShelf): string
    {
        $parts = array_filter([$registry, $rackShelf ? 'Rack/Shelf ' . $rackShelf : null]);
        return $parts ? implode(' — ', $parts) : ($registry ?? 'Digital Archive');
    }

    /**
     * Build file-number match variants (handles temp "(T)" suffix + casing).
     */
    protected function variants(string $fileNumber): array
    {
        $stripped   = preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $fileNumber);
        $candidates = [$fileNumber];
        if ($stripped !== null && $stripped !== '' && $stripped !== $fileNumber) {
            $candidates[] = $stripped;
        }

        $variants = [];
        foreach ($candidates as $candidate) {
            $variants[] = $candidate;
            $variants[] = strtoupper($candidate);
            $variants[] = strtolower($candidate);
        }

        return array_values(array_unique(array_filter($variants)));
    }

    protected function findIndexing(array $variants): ?FileIndexing
    {
        if (empty($variants)) {
            return null;
        }

        return FileIndexing::on('sqlsrv')
            ->where(function ($query) use ($variants) {
                $query->whereIn('file_number', $variants)
                    ->orWhereIn('new_kangis_file_no', $variants)
                    ->orWhereIn('kangis_file_no', $variants)
                    ->orWhereIn('mls_file_no', $variants)
                    ->orWhereIn('st_fillno', $variants);
            })
            ->orderByDesc('id')
            ->first();
    }

    protected function findTracker(array $variants): ?FileTracker
    {
        if (empty($variants)) {
            return null;
        }

        return FileTracker::whereIn('file_number', $variants)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Physical rack/shelf for the file. Prefers print_label_batch_items, then
     * falls back to file_indexings.shelf_location.
     */
    protected function getRackShelf(string $fileNumber, ?FileIndexing $indexing): ?string
    {
        $key = strtoupper(trim($fileNumber));
        if ($key === '') {
            return null;
        }
        if (array_key_exists($key, $this->rackShelfCache)) {
            return $this->rackShelfCache[$key];
        }

        $shelf = DB::connection('sqlsrv')
            ->table('print_label_batch_items')
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$key])
            ->orderByDesc('id')
            ->value('shelf_location');

        if (!$shelf && $indexing) {
            $shelf = $indexing->shelf_location;
        }

        return $this->rackShelfCache[$key] = ($shelf ?: null);
    }
}
