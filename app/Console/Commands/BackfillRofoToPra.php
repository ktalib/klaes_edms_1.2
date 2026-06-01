<?php

namespace App\Console\Commands;

use App\Models\LandRecommendation;
use App\Models\SltrRecommendation;
use App\Services\Pra\RofoPraSyncer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillRofoToPra extends Command
{
    protected $signature = 'rofo:backfill-pra
        {--source=all : land | sltr | st | all}
        {--chunk=200 : Rows per chunk}
        {--dry-run : List what would be synced without inserting}';

    protected $description = 'Backfill already-generated ROFOs (Land/OSS, SLTR, ST) into the pra table. Idempotent.';

    public function handle(RofoPraSyncer $syncer): int
    {
        $source = strtolower((string) $this->option('source'));
        $chunk = max(50, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $totals = ['land' => 0, 'sltr' => 0, 'st' => 0];

        if (in_array($source, ['all', 'land'], true)) {
            $totals['land'] = $this->backfillLand($syncer, $chunk, $dryRun);
        }

        if (in_array($source, ['all', 'sltr'], true)) {
            $totals['sltr'] = $this->backfillSltr($syncer, $chunk, $dryRun);
        }

        if (in_array($source, ['all', 'st'], true)) {
            $totals['st'] = $this->backfillSt($syncer, $chunk, $dryRun);
        }

        $this->info(sprintf(
            '%s — Land: %d, SLTR: %d, ST: %d (total %d)',
            $dryRun ? 'Would sync' : 'Synced',
            $totals['land'],
            $totals['sltr'],
            $totals['st'],
            array_sum($totals)
        ));

        return self::SUCCESS;
    }

    private function backfillLand(RofoPraSyncer $syncer, int $chunk, bool $dryRun): int
    {
        $this->info('Backfilling Land / OSS ROFOs...');
        $synced = 0;

        $query = LandRecommendation::query()
            ->where(function ($q) {
                $q->where('rofo_status', LandRecommendation::ROFO_GENERATED)
                  ->orWhereRaw("UPPER(ISNULL(type, '')) = 'OSS'");
            })
            ->whereNotNull('file_number')
            ->where('file_number', '<>', '');

        $query->orderBy('id')->chunkById($chunk, function ($recs) use ($syncer, $dryRun, &$synced) {
            foreach ($recs as $rec) {
                if ($dryRun) {
                    if (!$this->praExists($rec->file_number)) {
                        $this->line("  [Land] would sync file_number={$rec->file_number}");
                        $synced++;
                    }
                    continue;
                }

                if ($syncer->syncLand($rec)) {
                    $synced++;
                }
            }
        });

        return $synced;
    }

    private function backfillSltr(RofoPraSyncer $syncer, int $chunk, bool $dryRun): int
    {
        $this->info('Backfilling SLTR ROFOs...');
        $synced = 0;

        $query = SltrRecommendation::query()
            ->where('rofo_status', SltrRecommendation::ROFO_GENERATED)
            ->whereNotNull('sltr_number')
            ->where('sltr_number', '<>', '');

        $query->orderBy('id')->chunkById($chunk, function ($recs) use ($syncer, $dryRun, &$synced) {
            foreach ($recs as $rec) {
                if ($dryRun) {
                    if (!$this->praExists($rec->sltr_number)) {
                        $this->line("  [SLTR] would sync sltr_number={$rec->sltr_number}");
                        $synced++;
                    }
                    continue;
                }

                if ($syncer->syncSltr($rec)) {
                    $synced++;
                }
            }
        });

        return $synced;
    }

    private function backfillSt(RofoPraSyncer $syncer, int $chunk, bool $dryRun): int
    {
        $this->info('Backfilling ST (Sectional Titling) ROFOs...');
        $synced = 0;

        DB::connection('sqlsrv')->table('rofo')
            ->select('sub_application_id')
            ->whereNotNull('sub_application_id')
            ->orderBy('id')
            ->chunk($chunk, function ($rows) use ($syncer, $dryRun, &$synced) {
                $ids = $rows->pluck('sub_application_id')
                    ->map(fn ($v) => (int) $v)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (empty($ids)) {
                    return;
                }

                if ($dryRun) {
                    // Use the same join logic as syncer to count would-sync rows,
                    // but skip the actual insert.
                    $rows = DB::connection('sqlsrv')->table('subapplications')
                        ->leftJoin('mother_applications', 'subapplications.main_application_id', '=', 'mother_applications.id')
                        ->whereIn('subapplications.id', $ids)
                        ->select('subapplications.id', 'subapplications.fileno as sub_fileno', 'mother_applications.fileno as mother_fileno')
                        ->get();

                    foreach ($rows as $row) {
                        $fileNumber = trim((string) ($row->sub_fileno ?? $row->mother_fileno ?? ''));
                        if ($fileNumber !== '' && !$this->praExists($fileNumber)) {
                            $this->line("  [ST] would sync sub_application_id={$row->id} file_number={$fileNumber}");
                            $synced++;
                        }
                    }
                    return;
                }

                $synced += $syncer->syncStUnits($ids);
            });

        return $synced;
    }

    private function praExists(string $rofoNumber): bool
    {
        return DB::connection('sqlsrv')->table('pra')
            ->where('rofo_number', $rofoNumber)
            ->exists();
    }
}
