<?php

namespace App\Console\Commands;

use App\Services\FileIndexingCoordinateBackfillService;
use Illuminate\Console\Command;

class BackfillFileIndexingCoordinates extends Command
{
    protected $signature = 'fileindexing:backfill-coordinates
                            {--limit=100 : Max rows to process this run (keep small locally to avoid API cost)}
                            {--batch-size=100 : Rows fetched per internal batch}
                            {--dry-run : Resolve and report without writing}
                            {--force : Also re-geocode rows that already have coordinates}';

    protected $description = 'Backfill file_indexings.latitude/longitude via Google Geocoding, using the same address format as the web form\'s "Apply & Pin on Map" control.';

    public function handle(FileIndexingCoordinateBackfillService $service): int
    {
        $limit     = max(1, (int) $this->option('limit'));
        $batchSize = max(1, (int) $this->option('batch-size'));
        $dryRun    = (bool) $this->option('dry-run');
        $force     = (bool) $this->option('force');

        $this->info("Geocoding up to {$limit} row(s)" . ($dryRun ? ' [dry-run]' : '') . ($force ? ' [force]' : '') . '…');

        $totalCounts = [];
        $totalWritten = 0;
        $totalProcessed = 0;
        $afterId = null;
        $remaining = null;

        // Loop in sub-batches, advancing the id cursor each time, so a run of
        // unaddressable rows (SKIPPED_NO_ADDRESS — never written to) doesn't
        // keep getting re-selected as "the first N missing coordinates" forever.
        while ($totalProcessed < $limit) {
            $result = $service->runBatch(min($batchSize, $limit - $totalProcessed), $dryRun, $force, $afterId);

            if (!empty($result['key_missing'])) {
                $this->error('services.google_maps.geocoding_key (GOOGLE_GEOCODING_API_KEY) is not configured.');
                return self::FAILURE;
            }

            foreach ($result['counts'] as $status => $n) {
                $totalCounts[$status] = ($totalCounts[$status] ?? 0) + $n;
            }
            $totalWritten += $result['written'];
            $totalProcessed += $result['processed'];
            $afterId = $result['last_id'];
            $remaining = $result['remaining'];

            $summary = collect($result['counts'])->map(fn ($n, $s) => "{$s}={$n}")->implode(', ') ?: 'no rows';
            $this->line("  batch: processed {$result['processed']} ({$summary}), remaining {$remaining}");

            if ($result['processed'] === 0) {
                break; // no more rows left to consider
            }
        }

        $this->info("Processed {$totalProcessed} row(s) total.");
        $this->info('Outcome breakdown:');
        foreach ($totalCounts as $status => $n) {
            $this->line(sprintf('  %-24s %d', $status, $n));
        }
        $this->info($dryRun ? 'Dry-run complete (no writes).' : "Backfill complete — {$totalWritten} row(s) updated.");
        $this->info("Remaining without coordinates: {$remaining}");

        return self::SUCCESS;
    }
}
