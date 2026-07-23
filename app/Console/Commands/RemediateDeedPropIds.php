<?php

namespace App\Console\Commands;

use App\Services\PropertyIdAllocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Regenerate the divergent (non-canonical) prop_ids that the instrument/deed capture flow
 * wrote into deed_registrations during the Feb–May 2026 window — 8–10 digit ids that do
 * not exist in PropID_Master. For each affected file we resolve its ONE correct prop_id
 * (an existing canonical id when one exists → re-point; otherwise a freshly minted id via
 * PropertyIdAllocationService → mint) and stamp it on the file's rows.
 *
 * Safe by design (see plan `ticklish-bouncing-glacier`):
 *   - Default is a DRY-RUN (read-only). Writes only with --apply.
 *   - Skips files that participate in an OP/TOT relationship (their shared prop_id is legit).
 *   - Skips files whose divergent prop_id is referenced as parent_prop_id anywhere (lineage).
 *   - Only ever overwrites a divergent (>6 digit) or blank prop_id — never a valid short one.
 */
class RemediateDeedPropIds extends Command
{
    protected $signature = 'propid:remediate-deed
        {--apply : Actually write changes. Omit for a read-only dry-run report.}
        {--limit=0 : Optionally cap the number of files processed (0 = all).}';

    protected $description = 'Regenerate divergent 8-10 digit deed_registrations prop_ids (re-point to canonical or mint fresh), preserving OP/TOT and parent_prop_id.';

    /** Canonical prop_ids are short ints; anything longer is divergent. */
    private const CANONICAL_MAX_LEN = 6;

    private const CANONICAL_TABLES = ['pra', 'CofO_staging', 'file_history_staging', 'pic'];
    private const PARENT_PROP_TABLES = ['pra', 'file_indexings', 'fileNumber', 'deprecated_records'];

    public function handle(PropertyIdAllocationService $propIds): int
    {
        $apply = (bool) $this->option('apply');
        $limit = (int) $this->option('limit');
        $conn = DB::connection('sqlsrv');

        $this->info(($apply ? '[APPLY] ' : '[DRY-RUN] ') . 'Remediating divergent deed_registrations prop_ids…');

        // Distinct files that have at least one divergent (>6 digit) prop_id in deed_registrations.
        $files = $conn->table('deed_registrations')
            ->selectRaw('UPPER(LTRIM(RTRIM(CAST(fileno AS NVARCHAR(100))))) AS fno')
            ->whereNotNull('fileno')
            ->whereRaw("LTRIM(RTRIM(CAST(fileno AS NVARCHAR(100)))) <> ''")
            ->whereRaw('LEN(LTRIM(RTRIM(CAST(prop_id AS NVARCHAR(50))))) > ?', [self::CANONICAL_MAX_LEN])
            ->distinct()
            ->pluck('fno')
            ->all();

        if ($limit > 0) {
            $files = array_slice($files, 0, $limit);
        }

        $this->line('Files with divergent deed prop_ids: ' . count($files));

        $log = [];               // change-log rows: [table, fileno, old_prop_id, new_prop_id]
        $counts = ['repoint' => 0, 'mint' => 0, 'skip_optot' => 0, 'skip_parentref' => 0, 'error' => 0];

        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        foreach ($files as $fno) {
            $bar->advance();
            try {
                // --- Skip guard 1: OP/TOT participation (shared prop_id is legitimate) ---
                if ($this->participatesInOpTot($conn, $fno)) {
                    $counts['skip_optot']++;
                    $log[] = ['(skip)', $fno, '', 'SKIP-optot'];
                    continue;
                }

                // The file's current divergent prop_id(s) in deed_registrations.
                $oldPids = $conn->table('deed_registrations')
                    ->whereRaw('UPPER(LTRIM(RTRIM(CAST(fileno AS NVARCHAR(100))))) = ?', [$fno])
                    ->whereRaw('LEN(LTRIM(RTRIM(CAST(prop_id AS NVARCHAR(50))))) > ?', [self::CANONICAL_MAX_LEN])
                    ->distinct()
                    ->pluck('prop_id')
                    ->map(fn ($v) => trim((string) $v))
                    ->filter()
                    ->all();

                // --- Skip guard 2: divergent prop_id referenced as parent_prop_id anywhere ---
                if ($this->referencedAsParent($conn, $oldPids)) {
                    $counts['skip_parentref']++;
                    $log[] = ['(skip)', $fno, implode('|', $oldPids), 'SKIP-parentref'];
                    continue;
                }

                // --- Resolve the correct prop_id ---
                $canonical = $this->resolveExistingCanonical($conn, $fno); // read-only lookup
                if ($canonical !== null) {
                    $action = 'repoint';
                    $newPid = $canonical;
                } else {
                    $action = 'mint';
                    // In dry-run we do NOT mint (that writes a PropID_Master row); we only preview.
                    $newPid = $apply ? $this->mint($propIds, $fno) : null;
                }

                if ($action === 'mint') {
                    $counts['mint']++;
                } else {
                    $counts['repoint']++;
                }

                if (!$apply) {
                    $log[] = ['deed_registrations', $fno, implode('|', $oldPids), $action === 'repoint' ? (string) $newPid : '(new id on apply)'];
                    continue;
                }

                if ($newPid === null || $newPid <= 0) {
                    $counts['error']++;
                    $counts[$action]--;
                    $this->newLine();
                    $this->warn("Could not resolve a prop_id for {$fno} — skipped.");
                    continue;
                }

                // --- Apply: stamp the resolved id on the file's divergent/blank rows ---
                $conn->transaction(function () use ($conn, $fno, $newPid, &$log) {
                    // deed_registrations: only the divergent rows.
                    $conn->table('deed_registrations')
                        ->whereRaw('UPPER(LTRIM(RTRIM(CAST(fileno AS NVARCHAR(100))))) = ?', [$fno])
                        ->whereRaw('LEN(LTRIM(RTRIM(CAST(prop_id AS NVARCHAR(50))))) > ?', [self::CANONICAL_MAX_LEN])
                        ->update(['prop_id' => $newPid]);
                    $log[] = ['deed_registrations', $fno, '(divergent)', (string) $newPid];

                    // Other source tables: only rows that are divergent or blank (never a valid short id).
                    foreach (self::CANONICAL_TABLES as $t) {
                        $conn->table($t)
                            ->whereRaw('UPPER(LTRIM(RTRIM(CAST(fileno AS NVARCHAR(100))))) = ?', [$fno])
                            ->where(function ($q) {
                                $q->whereRaw('LEN(LTRIM(RTRIM(CAST(prop_id AS NVARCHAR(50))))) > ?', [self::CANONICAL_MAX_LEN])
                                    ->orWhereNull('prop_id')
                                    ->orWhereRaw("LTRIM(RTRIM(CAST(prop_id AS NVARCHAR(50)))) = ''");
                            })
                            ->update(['prop_id' => $newPid]);
                    }
                });
            } catch (Throwable $e) {
                $counts['error']++;
                $this->newLine();
                $this->warn("Error on {$fno}: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Write the report / change-log CSV.
        $dir = storage_path('app/propid-remediation');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $csv = $dir . '/deed-remediation-' . ($apply ? 'applied' : 'dryrun') . '-' . now()->format('Ymd-His') . '.csv';
        $fh = fopen($csv, 'w');
        fputcsv($fh, ['table', 'fileno', 'old_prop_id', 'new_prop_id']);
        foreach ($log as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);

        $this->table(
            ['re-point', 'mint', 'skip OP/TOT', 'skip parent-ref', 'errors'],
            [[$counts['repoint'], $counts['mint'], $counts['skip_optot'], $counts['skip_parentref'], $counts['error']]]
        );
        $this->info(($apply ? 'Applied. ' : 'Dry-run only (no changes written). ') . 'Report: ' . $csv);

        if (!$apply) {
            $this->comment('Review the report, then re-run with --apply to write changes.');
        }

        return self::SUCCESS;
    }

    /**
     * Does this file participate in an OP/TOT relationship? (Its pra rows carry an
     * Occupancy Permit / Transfer of Title type — such prop_id sharing is legitimate.)
     */
    private function participatesInOpTot($conn, string $fno): bool
    {
        return $conn->table('pra')
            ->whereRaw('UPPER(LTRIM(RTRIM(CAST(fileno AS NVARCHAR(100))))) = ?', [$fno])
            ->where(function ($q) {
                $q->where('transaction_type', 'like', '%Occupancy Permit%')
                    ->orWhere('transaction_type', 'like', '%Transfer of Title%')
                    ->orWhere('transaction_type', 'like', '%(OP)%');
            })
            ->exists();
    }

    /** Is any of the given (divergent) prop_ids referenced as a parent_prop_id anywhere? */
    private function referencedAsParent($conn, array $oldPids): bool
    {
        $oldPids = array_values(array_filter(array_map('strval', $oldPids), fn ($v) => $v !== ''));
        if (empty($oldPids)) {
            return false;
        }
        foreach (self::PARENT_PROP_TABLES as $t) {
            try {
                $hit = $conn->table($t)
                    ->whereIn(DB::raw('LTRIM(RTRIM(CAST(parent_prop_id AS NVARCHAR(50))))'), $oldPids)
                    ->exists();
                if ($hit) {
                    return true;
                }
            } catch (Throwable $e) {
                // Table/column absent — ignore.
            }
        }
        return false;
    }

    /**
     * Read-only: the file's existing canonical (short) prop_id, if one exists — first from
     * PropID_Master (by any registered identifier), then from the canonical source tables.
     * Returns null when the file has no canonical id yet (→ needs a fresh mint).
     */
    private function resolveExistingCanonical($conn, string $fno): ?int
    {
        $master = $conn->table('PropID_Master')
            ->where(function ($q) use ($fno) {
                foreach (['primary_file_number_norm', 'mlsFNo_norm', 'kangisFileNo_norm', 'NewKANGISFileno_norm', 'temp_fileno_norm'] as $col) {
                    $q->orWhere($col, $fno);
                }
            })
            ->value('prop_id');
        if ($master !== null && (int) $master > 0) {
            return (int) $master;
        }

        foreach (self::CANONICAL_TABLES as $t) {
            $pid = $conn->table($t)
                ->whereRaw('UPPER(LTRIM(RTRIM(CAST(fileno AS NVARCHAR(100))))) = ?', [$fno])
                ->whereRaw('LEN(LTRIM(RTRIM(CAST(prop_id AS NVARCHAR(50))))) <= ?', [self::CANONICAL_MAX_LEN])
                ->whereRaw("LTRIM(RTRIM(CAST(prop_id AS NVARCHAR(50)))) <> ''")
                ->orderByDesc('id')
                ->value('prop_id');
            if ($pid !== null && (int) $pid > 0) {
                return (int) $pid;
            }
        }

        return null;
    }

    /**
     * Mint a fresh canonical prop_id for the file via the authoritative service (this also
     * registers a PropID_Master row). The KANGIS-format file numbers are passed on the
     * matching identifier so master records them correctly.
     */
    private function mint(PropertyIdAllocationService $propIds, string $fno): int
    {
        $isOldKangis = (bool) preg_match('/^(KNML|MLKN|KNGP)\s*\d/i', $fno);
        $isNewKangis = !$isOldKangis && (bool) preg_match('/^KN\s*\d/i', $fno);

        return $propIds->allocateOrRetrievePropId(
            $fno,                                   // primary
            (!$isOldKangis && !$isNewKangis) ? $fno : null, // mlsFNo
            $isOldKangis ? $fno : null,             // kangisFileNo
            $isNewKangis ? $fno : null              // NewKANGISFileno
        );
    }
}
