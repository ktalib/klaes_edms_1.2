<?php

namespace App\Console\Commands;

use App\Models\FileIndexing;
use App\Models\FileTracker;
use App\Services\FileRangeTrackingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the opening "home" file_tracker row for files indexed BEFORE
 * FileRangeTrackingService shipped, so they show up on the File Log Table.
 *
 * Those files were indexed without the range-derived tracking line, so the File
 * Log Table has nothing to list them by — they only exist in file_indexings.
 * This walks a date window of file_indexings and writes the same row the
 * indexing screen now writes automatically.
 *
 * DRY RUN BY DEFAULT. Nothing is written unless --commit is passed, and the CSV
 * export shows the exact attributes that would be inserted: the rows come from
 * FileRangeTrackingService::plan(), which is literally what the committing path
 * feeds to FileTracker::fill(), so the preview cannot drift from the result.
 *
 * Land files only — that falls out of config/file_ranges.php on its own. Its
 * prefixes (RES/COM/IND/AG/SIT/CON-*) are all Land, so a KANGIS, SLTR, ST or
 * DCIV number matches no range and is skipped as `no_range_match`.
 *
 * --csv takes an ABSOLUTE path whose folder already exists (it is not created).
 *
 *   php artisan tracker:backfill-range-home --from=2026-08-03 --to=2026-08-06 --csv="C:/Users/admin/Downloads/range-home-backfill.csv"
 *   php artisan tracker:backfill-range-home --from=2026-08-03 --to=2026-08-06 --commit
 */
class BackfillRangeHomeTrackers extends Command
{
    protected $signature = 'tracker:backfill-range-home
        {--from= : Start date (inclusive), Y-m-d, on file_indexings.created_at}
        {--to= : End date (inclusive), Y-m-d}
        {--csv= : Write the planned rows to this CSV path}
        {--commit : Actually insert the rows (default is a dry run)}';

    protected $description = 'Backfill range-derived home file_tracker rows for files indexed in a date window (dry run unless --commit).';

    public function handle(FileRangeTrackingService $service): int
    {
        $from = trim((string) $this->option('from'));
        $to   = trim((string) $this->option('to'));

        if ($from === '' || $to === '') {
            $this->error('Both --from and --to are required (Y-m-d).');
            return self::FAILURE;
        }

        try {
            // The window is inclusive of the whole --to day, so a file indexed at
            // 16:00 on the last date is not silently dropped.
            $start = Carbon::parse($from)->startOfDay();
            $end   = Carbon::parse($to)->endOfDay();
        } catch (\Throwable $e) {
            $this->error('Could not parse the dates: ' . $e->getMessage());
            return self::FAILURE;
        }

        $commit = (bool) $this->option('commit');

        $this->info(sprintf(
            '%s — files indexed %s .. %s  [db: %s @ %s]',
            $commit ? 'COMMIT' : 'DRY RUN',
            $start->toDateTimeString(),
            $end->toDateTimeString(),
            config('database.connections.sqlsrv.database'),
            config('database.connections.sqlsrv.host')
        ));

        $records = FileIndexing::on('sqlsrv')
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('id')
            ->get();

        $this->line('Indexed files in window: ' . $records->count());

        $planned = [];
        $skipped = [];

        foreach ($records as $record) {
            $plan = $service->plan($record);

            if (!$plan['ok']) {
                $skipped[$plan['reason']] = ($skipped[$plan['reason']] ?? 0) + 1;
                continue;
            }

            $planned[] = ['record' => $record, 'plan' => $plan];
        }

        $this->newLine();
        $this->line('Would create : ' . count($planned));
        foreach ($skipped as $reason => $count) {
            $this->line(str_pad('Skipped (' . $reason . ')', 30) . ': ' . $count);
        }

        // Zone / registry breakdown — the quickest sanity check that the ranges
        // resolved the way the operator expects before anything is written.
        $byZone = [];
        foreach ($planned as $row) {
            $key = $row['plan']['registry'] . ' / ' . $row['plan']['zone'];
            $byZone[$key] = ($byZone[$key] ?? 0) + 1;
        }
        if (!empty($byZone)) {
            $this->newLine();
            $this->line('By registry / zone:');
            ksort($byZone);
            foreach ($byZone as $key => $count) {
                $this->line('  ' . str_pad($key, 32) . $count);
            }
        }

        if ($csvPath = trim((string) $this->option('csv'))) {
            // Reported, never thrown: the survey above is the expensive part and it
            // has already run, so a bad --csv path must not discard it.
            $this->newLine();
            try {
                $this->writeCsv($csvPath, $planned);
                $this->info('CSV written: ' . $csvPath);
            } catch (\Throwable $e) {
                $this->error('Could not write the CSV: ' . $e->getMessage());
                $this->comment('Pass --csv an absolute path whose folder exists, e.g.');
                $this->comment('  --csv="C:/Users/' . (getenv('USERNAME') ?: 'you') . '/Downloads/range-home-backfill.csv"');
            }
        }

        if (!$commit) {
            $this->newLine();
            $this->comment('Dry run — nothing written. Re-run with --commit to insert.');
            return self::SUCCESS;
        }

        if (empty($planned)) {
            $this->info('Nothing to insert.');
            return self::SUCCESS;
        }

        $created = 0;
        $failed  = 0;

        // Rollback manifest: every tracker id this run inserts, written as we go.
        // Undoing the backfill must delete exactly these rows and nothing else — a
        // blanket "file_request_type = SYSTEM" delete would also destroy the rows
        // the indexing screen writes for itself every day.
        $manifestPath = storage_path('logs/range-home-backfill-' . now()->format('Ymd-His') . '.ids.txt');
        $manifest = fopen($manifestPath, 'w');

        $bar = $this->output->createProgressBar(count($planned));
        $bar->start();

        foreach ($planned as $row) {
            try {
                // Re-plan inside the commit so a file that gained a real tracker
                // between the preview and the run is not given a duplicate home row.
                $result = $service->openForIndexing($row['record']);
                if ($result['created']) {
                    $created++;
                    fwrite($manifest, $result['tracker_id'] . "\n");
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->warn('Failed ' . $row['plan']['file_number'] . ': ' . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        fclose($manifest);

        $this->newLine(2);
        $this->info("Created: {$created}   Failed/skipped: {$failed}");
        $this->line('Rollback manifest (tracker ids): ' . $manifestPath);

        return self::SUCCESS;
    }

    /**
     * One CSV line per planned file_tracker row. Columns mirror the table's own
     * columns so the file can be read straight against file_tracker, with the
     * movement_log broken out into the fields the File Log Table actually renders.
     *
     * @param array<int,array{record:FileIndexing, plan:array}> $planned
     */
    private function writeCsv(string $path, array $planned): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            // Deliberately NOT created: a mistyped path (or an unexpanded example
            // like "...\Downloads") would otherwise silently produce a junk folder
            // and hide the CSV somewhere nobody looks.
            throw new \RuntimeException("The folder does not exist: {$dir}");
        }

        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException("Could not open for writing: {$path}");
        }

        // BOM so Excel opens the em-dashes in the office names as UTF-8.
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'file_indexing_id',
            'file_number',
            'file_title',
            'indexed_at',
            'registry',
            'zone',
            'tracking_id',
            'status',
            'assignment_status',
            'file_request_type',
            'department',
            'current_office_code',
            'current_office_name',
            'origin_office_name',
            'destination',
            'module',
            'in_digital_archive',
            'total_offices',
            'completed_offices',
            'date_created',
            'mv_log_id',
            'mv_office_name',
            'mv_status_label',
            'mv_receiving_officer_name',
            'mv_log_in_date',
            'mv_log_out_date',
            'mv_log_out_time',
            'mv_notes',
        ]);

        foreach ($planned as $row) {
            /** @var FileIndexing $record */
            $record = $row['record'];
            $a  = $row['plan']['attributes'];
            $mv = $a['movement_log'][0] ?? [];

            fputcsv($handle, [
                $record->id,
                $a['file_number'] ?? '',
                $a['file_title'] ?? '',
                (string) $record->created_at,
                $row['plan']['registry'],
                $row['plan']['zone'],
                $a['tracking_id'] ?? '',
                $a['status'] ?? '',
                $a['assignment_status'] ?? '',
                $a['file_request_type'] ?? '',
                $a['department'] ?? '',
                $a['current_office_code'] ?? '',
                $a['current_office_name'] ?? '',
                $a['origin_office_name'] ?? '',
                $a['destination'] ?? '',
                $a['module'] ?? '',
                !empty($a['in_digital_archive']) ? 1 : 0,
                $a['total_offices'] ?? '',
                $a['completed_offices'] ?? '',
                $a['date_created'] instanceof Carbon ? $a['date_created']->toDateTimeString() : (string) ($a['date_created'] ?? ''),
                $mv['log_id'] ?? '',
                $mv['office_name'] ?? '',
                $mv['status_label'] ?? '',
                $mv['receiving_officer_name'] ?? '',
                $mv['log_in_date'] ?? '',
                $mv['log_out_date'] ?? '',
                $mv['log_out_time'] ?? '',
                $mv['notes'] ?? '',
            ]);
        }

        fclose($handle);
    }
}
