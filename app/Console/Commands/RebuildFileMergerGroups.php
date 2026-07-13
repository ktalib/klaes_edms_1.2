<?php

namespace App\Console\Commands;

use App\Services\FileMergerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill the file_merger registry from existing lineage (decommissioned_files +
 * related_fileno / parent_prop_id). Idempotent — MergerIDs are stable, so re-running
 * simply refreshes each group. Safe to run repeatedly.
 */
class RebuildFileMergerGroups extends Command
{
    protected $signature = 'file-merger:rebuild
                            {--file= : Rebuild only the group containing this file number}
                            {--fresh : Truncate the file_merger table before a full rebuild}';

    protected $description = 'Materialise parcel-update / merger groups into the file_merger table from existing lineage';

    public function handle(FileMergerService $service): int
    {
        if (!Schema::connection('sqlsrv')->hasTable('decommissioned_files')) {
            $this->error('decommissioned_files table not found on the sqlsrv connection.');
            return self::FAILURE;
        }

        // Single-file mode.
        if ($file = trim((string) $this->option('file'))) {
            $mergerId = $service->rebuildForFile($file);
            if ($mergerId) {
                $group = $service->resolveGroup($file, false);
                $this->info("Group {$mergerId} rebuilt with " . count($group) . ' file(s):');
                foreach ($group as $m) {
                    $this->line("  [{$m['role']}] {$m['file_number']}");
                }
                return self::SUCCESS;
            }
            $this->warn("No parcel-update group found for {$file}.");
            return self::SUCCESS;
        }

        if ($this->option('fresh') && Schema::connection('sqlsrv')->hasTable('file_merger')) {
            DB::connection('sqlsrv')->table('file_merger')->truncate();
            $this->warn('Truncated file_merger before rebuild.');
        }

        // Every seed file number recorded in decommissioned_files (parents + successors).
        $cols = ['file_no', 'mls_file_no', 'kangis_file_no'];
        if (Schema::connection('sqlsrv')->hasColumn('decommissioned_files', 'successor_file_no')) {
            $cols[] = 'successor_file_no';
        }

        $seeds = [];
        DB::connection('sqlsrv')->table('decommissioned_files')
            ->select($cols)
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$seeds, $cols) {
                foreach ($rows as $row) {
                    foreach ($cols as $col) {
                        $v = trim((string) ($row->{$col} ?? ''));
                        if ($v !== '') {
                            $seeds[mb_strtoupper($v)] = $v;
                        }
                    }
                }
            });

        if (!$seeds) {
            $this->info('No decommissioned files to reconcile.');
            return self::SUCCESS;
        }

        $this->info('Rebuilding groups from ' . count($seeds) . ' seed file number(s)...');
        $bar = $this->output->createProgressBar(count($seeds));
        $bar->start();

        $groups = [];
        foreach ($seeds as $seed) {
            $mergerId = $service->rebuildForFile($seed);
            if ($mergerId) {
                $groups[$mergerId] = true;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Done. ' . count($groups) . ' distinct merger group(s) materialised.');

        return self::SUCCESS;
    }
}
