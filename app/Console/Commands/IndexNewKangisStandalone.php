<?php

namespace App\Console\Commands;

use App\Models\FileIndexing;
use App\Services\KangisParentLinkService;
use App\Services\PropertyIdAllocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates standalone file_indexings records for New KANGIS (KN-series) numbers
 * that are REFERENCED (via a file's `new_kangis_file_no` column or `related_fileno`)
 * but were never indexed on their own row.
 *
 * New KANGIS = "KN" + digits with NO separator ("KN123"). "KN 120" / "KN-120"
 * (with a space/dash) is a legacy MLS/land file, NOT a New KANGIS number, and is
 * excluded (see KangisParentLinkService::isNewKangisNumber / isOldMlsKnNumber).
 *
 * For each missing KN the command:
 *   1. Resolves its parent — the Old KANGIS file (legacy MLKN/KNML/KNGP) when one
 *      exists, otherwise the Land file that carries the KN (a legitimate 2-file
 *      case with no legacy KANGIS tier).
 *   2. Ensures that PARENT has a prop_id (allocates one if missing, and writes it
 *      back onto the parent's file_indexings row).
 *   3. Creates the standalone KN record with its OWN distinct prop_id and
 *      parent_prop_id = the parent's prop_id.
 *
 * SAFE BY DEFAULT: runs as a preview (no writes). Pass --commit to actually write.
 */
class IndexNewKangisStandalone extends Command
{
    protected $signature = 'kangis:index-new-kangis
        {--commit : Actually write. Without this flag the command only previews (no DB changes).}
        {--limit=0 : Process at most N candidates (0 = all).}';

    protected $description = 'Create standalone records for referenced-but-unindexed New KANGIS (KN####) files, each with its own prop_id + parent link. Preview by default; --commit to write.';

    private const CREATED_BY = 'New KANGIS Standalone Backfill';

    /** Descriptive columns copied from the source row onto the new KN record. */
    private const COPY_KEYS = [
        'file_title', 'land_use_type', 'plot_number', 'plot_size',
        'latitude', 'longitude', 'district', 'lga', 'street_name',
        'physical_registry', 'tp_no', 'lpkn_no', 'location',
    ];

    public function __construct(
        private KangisParentLinkService $links,
        private PropertyIdAllocationService $allocator
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $limit = max(0, (int) $this->option('limit'));
        $conn = DB::connection('sqlsrv');

        $this->info($commit ? '*** COMMIT MODE — changes WILL be written ***' : 'PREVIEW MODE — no DB changes (pass --commit to write).');

        // 1. KN numbers already indexed as their own standalone row.
        $standalone = [];
        $conn->table('file_indexings')->whereNull('deleted_at')
            ->where('file_number', 'like', 'KN%')
            ->orderBy('id')->pluck('file_number')
            ->each(function ($fn) use (&$standalone) {
                if ($this->links->isNewKangisNumber($fn)) {
                    $standalone[$this->norm($fn)] = true;
                }
            });

        // 2. Source rows that reference a KN (column or related_fileno).
        $cols = ['id', 'file_number', 'general_registry', 'prop_id', 'parent_prop_id',
                 'new_kangis_file_no', 'related_fileno', ...self::COPY_KEYS];
        $sources = $conn->table('file_indexings')->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where(function ($qq) {
                    $qq->whereNotNull('new_kangis_file_no')->where('new_kangis_file_no', '<>', '');
                })->orWhere('related_fileno', 'like', '%KN%');
            })
            ->orderBy('id')->get(array_values(array_unique($cols)));

        // 3. Build candidate map (first reference wins; column source preferred).
        $candidates = [];
        $consider = function ($kn, $srcType, $row) use (&$candidates) {
            $knN = $this->norm($kn);
            if (isset($candidates[$knN])) {
                return;
            }
            $candidates[$knN] = ['kn' => trim((string) $kn), 'src_type' => $srcType, 'row' => $row];
        };
        foreach ($sources as $r) {
            if ($r->new_kangis_file_no && $this->links->isNewKangisNumber($r->new_kangis_file_no)) {
                $consider($r->new_kangis_file_no, 'new_kangis_file_no', $r);
            }
        }
        foreach ($sources as $r) {
            foreach ($this->parseRelated($r->related_fileno) as $tok) {
                if ($this->links->isNewKangisNumber($tok)) {
                    $consider($tok, 'related_fileno', $r);
                }
            }
        }

        // 4. Keep only the ones not yet standalone-indexed.
        $missing = array_filter($candidates, fn ($c) => !isset($standalone[$this->norm($c['kn'])]));
        if ($limit > 0) {
            $missing = array_slice($missing, 0, $limit, true);
        }

        $this->info('New KANGIS to create: ' . count($missing));
        $this->newLine();

        $created = 0; $parentPropAllocated = 0; $skipped = 0;

        foreach ($missing as $c) {
            $kn = $c['kn'];
            $row = $c['row'];

            // Resolve the parent file number.
            [$parentNo, $parentKind] = $this->resolveParent($row);

            if ($parentNo === null) {
                $this->warn("  SKIP {$kn}: could not resolve any parent file.");
                $skipped++;
                continue;
            }

            // Ensure the parent has a prop_id.
            $parentPropId = $this->links->lookupExistingPropId($parentNo);
            $parentAction = $parentPropId !== null ? "prop_id {$parentPropId}" : 'NEEDS prop_id';

            $this->line("  {$kn}  <-  parent {$parentNo} [{$parentKind}] ({$parentAction})");

            if (!$commit) {
                continue;
            }

            try {
                $conn->transaction(function () use ($kn, $row, $parentNo, &$parentPropId, &$parentPropAllocated, &$created) {
                    // 1. Ensure parent prop_id.
                    if ($parentPropId === null) {
                        $parentPropId = $this->allocator->allocateOrRetrievePropId($parentNo);
                        // Persist onto the parent's file_indexings row(s) if empty.
                        DB::connection('sqlsrv')->table('file_indexings')
                            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$parentNo])
                            ->where(function ($q) {
                                $q->whereNull('prop_id')->orWhere('prop_id', 0);
                            })
                            ->update(['prop_id' => $parentPropId, 'updated_at' => now()]);
                        $parentPropAllocated++;
                    }

                    // 2. Allocate a DISTINCT prop_id for the KN record.
                    $ownPropId = $this->allocator->allocateOrRetrievePropId($kn, null, null, $kn, ['skip_lookup' => true]);

                    // 3. Create the standalone KN record.
                    $payload = [];
                    foreach (self::COPY_KEYS as $k) {
                        $payload[$k] = $row->{$k} ?? null;
                    }
                    $payload['file_number'] = $kn;
                    $payload['general_registry'] = 'KANGIS Registry';
                    $payload['indexing_type'] = 'Regular';
                    $payload['workflow_status'] = 'indexed';
                    $payload['prop_id'] = $ownPropId;
                    $payload['parent_prop_id'] = $parentPropId !== null ? (string) $parentPropId : null;
                    $payload['related_fileno'] = json_encode([$parentNo]);
                    $payload['source'] = 'new_kangis_standalone_backfill';
                    $payload['created_by'] = self::CREATED_BY;
                    $payload['updated_by'] = self::CREATED_BY;

                    FileIndexing::on('sqlsrv')->create($payload);

                    // 4. Mirror parent link onto fileNumber + sync prop_id to history.
                    if ($parentPropId !== null) {
                        $this->links->assignFileLevelParent($kn, (int) $parentPropId);
                    }
                    if ($ownPropId !== null) {
                        $this->allocator->syncPropIdToFileHistory($kn, $ownPropId, null);
                    }

                    $created++;
                });
            } catch (\Throwable $e) {
                $this->error("  FAILED {$kn}: " . $e->getMessage());
                Log::warning('kangis:index-new-kangis failed', ['kn' => $kn, 'error' => $e->getMessage()]);
            }
        }

        $this->newLine();
        if ($commit) {
            $this->info("Done. Created {$created} New KANGIS record(s); allocated prop_id to {$parentPropAllocated} parent(s); skipped {$skipped}.");
        } else {
            $this->info('Preview complete. Re-run with --commit to create these records.');
        }

        return self::SUCCESS;
    }

    /**
     * Resolve a KN's parent file number.
     *  - Old KANGIS (legacy MLKN/KNML/KNGP) when the source row IS one, or when the
     *    source row's related_fileno contains one.
     *  - Otherwise the Land file that carries the KN (2-file case, no legacy tier).
     *
     * @return array{0:?string,1:string} [parentFileNumber, kind]
     */
    private function resolveParent(object $row): array
    {
        if ($this->links->isLegacyKangisNumber($row->file_number)) {
            return [trim((string) $row->file_number), 'Old KANGIS'];
        }

        foreach ($this->parseRelated($row->related_fileno) as $rel) {
            if ($this->links->isLegacyKangisNumber($rel)) {
                return [trim((string) $rel), 'Old KANGIS'];
            }
        }

        // No legacy KANGIS anywhere — the land file itself is the parent.
        $fn = trim((string) $row->file_number);
        return $fn !== '' ? [$fn, 'Land file'] : [null, ''];
    }

    /** @return array<int,string> */
    private function parseRelated($value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $decoded), fn ($v) => $v !== ''));
        }
        return [$value];
    }

    private function norm($v): string
    {
        return strtoupper(trim((string) $v));
    }
}
