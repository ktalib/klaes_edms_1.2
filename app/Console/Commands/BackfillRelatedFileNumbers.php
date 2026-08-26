<?php

namespace App\Console\Commands;

use App\Services\RelatedFileNumberRegistrar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Register the related file numbers that only ever reached file_indexings.related_fileno.
 *
 * File Indexing never wrote to related_file_number, and MLS commissioning registered only the
 * typed related-files widget, so every plain related number saved since the 2026-05-29 seed
 * migration is invisible to Legal Search and the Related File Numbers page. This replays those
 * rows through RelatedFileNumberRegistrar, which is the same writer the live paths now use.
 *
 * Idempotent: a number already registered for the indexing row is updated in place, never
 * duplicated, and nothing is ever deleted (prune is off).
 *
 *   php artisan related-files:backfill --dry-run
 *   php artisan related-files:backfill --since=2026-05-29
 */
class BackfillRelatedFileNumbers extends Command
{
    protected $signature = 'related-files:backfill
                            {--dry-run : Report what would be written without touching the register}
                            {--since= : Only file_indexings rows created on/after this date (Y-m-d)}
                            {--limit=0 : Stop after this many indexing rows (0 = no limit)}
                            {--chunk=500 : Rows read per batch}';

    protected $description = 'Backfill related_file_number from file_indexings.related_fileno for rows that were never registered';

    public function handle(RelatedFileNumberRegistrar $registrar): int
    {
        $conn = DB::connection('sqlsrv');

        if (!Schema::connection('sqlsrv')->hasTable(RelatedFileNumberRegistrar::TABLE)) {
            $this->error('related_file_number table not found.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit  = (int) $this->option('limit');
        $chunk  = max(50, (int) $this->option('chunk'));

        $query = $conn->table('file_indexings')
            ->select('id', 'file_number', 'file_title', 'prop_id', 'location', 'related_fileno', 'created_at')
            ->whereNotNull('related_fileno')
            // '[]' and '""' are the empty shapes the JSON column takes; LEN() > 2 skips both.
            ->whereRaw('LEN(LTRIM(RTRIM(CAST(related_fileno AS NVARCHAR(500))))) > 2')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from(RelatedFileNumberRegistrar::TABLE . ' AS t')
                    ->whereColumn('t.source_id', 'file_indexings.id')
                    ->where('t.source_table', 'file_indexings');
            })
            ->orderBy('id');

        if ($since = $this->option('since')) {
            $query->whereDate('created_at', '>=', $since);
        }

        $total = (clone $query)->count();
        $this->info(($dryRun ? '[dry-run] ' : '') . "Indexing rows to register: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $processed = 0;
        $links     = 0;
        $skipped   = 0;

        $bar = $this->output->createProgressBar($limit > 0 ? min($limit, $total) : $total);
        $bar->start();

        // Walked with an id cursor, not an offset: registered rows stop matching the
        // whereNotExists, so an offset-based chunk would step over unprocessed rows.
        $lastId = 0;
        while (true) {
            $rows = (clone $query)->where('id', '>', $lastId)->limit($chunk)->get();
            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $lastId = $row->id;

                $decoded = json_decode((string) $row->related_fileno, true);
                if (!is_array($decoded)) {
                    // Legacy bare string (the 2026-07-03 normalisation missed rows added since).
                    $decoded = [(string) $row->related_fileno];
                }

                $wanted = $registrar->normalizeInput($decoded);
                if (empty($wanted)) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                if (!$dryRun) {
                    $registrar->sync(
                        (int) $row->id,
                        $row->file_number,
                        $row->file_title,
                        $row->prop_id ?? null,
                        $decoded,
                        ['location' => $row->location ?? null]
                    );
                }

                $links += count($wanted);
                $processed++;
                $bar->advance();

                if ($limit > 0 && $processed >= $limit) {
                    break 2;
                }
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(($dryRun ? '[dry-run] ' : '') . "Indexing rows registered: {$processed}");
        $this->info(($dryRun ? '[dry-run] ' : '') . "Register rows written: {$links}");

        if ($skipped > 0) {
            $this->warn("Rows skipped (related_fileno held no usable number): {$skipped}");
        }

        return self::SUCCESS;
    }
}
