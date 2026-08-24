<?php

namespace App\Console\Commands;

use App\Models\DuplexParcelUpdate;
use App\Models\DuplexParcelUpdateFile;
use App\Services\DuplexSummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Undo a duplex commissioning so it can be run again.
 *
 * Testing the duplex means commissioning it repeatedly, and each run scatters rows
 * across a dozen registry tables, burns serials and decommissions the source. Undoing
 * that by hand is slow and easy to get wrong — hence this.
 *
 * It deletes ONLY rows keyed to the file numbers this duplex created, restores the
 * source file, and returns the duplex to `in_land`. It never touches a file number the
 * duplex did not produce.
 *
 *   php artisan duplex:rollback DPX-2026-0007
 *   php artisan duplex:rollback --all --dry-run
 */
class DuplexRollback extends Command
{
    protected $signature = 'duplex:rollback
                            {duplex? : Duplex ID, e.g. DPX-2026-0007}
                            {--all : Roll back every committed duplex}
                            {--dry-run : Report what would be removed, change nothing}';

    protected $description = 'Undo a duplex commissioning so it can be commissioned again';

    /** Table => the column holding a file number, in deletion order. */
    private const TABLES = [
        'pra'               => 'fileno',
        'oss_applications'  => 'file_no',
        'mls_file_no'       => 'full_file_number',
        'file_tracker'      => 'file_number',
        'file_indexings'    => 'file_number',
        'fileNumber'        => 'mlsfNo',
        'customers_staging' => 'file_number',
        'entities_staging'  => 'file_number',
    ];

    public function handle(DuplexSummaryService $summaries): int
    {
        $dry = (bool) $this->option('dry-run');

        $targets = $this->option('all')
            ? DuplexParcelUpdate::visible()->where('status', DuplexParcelUpdate::STATUS_COMMITTED)->get()
            : DuplexParcelUpdate::where('duplex_id', $this->argument('duplex'))->get();

        if ($targets->isEmpty()) {
            $this->warn('Nothing to roll back.');
            return self::SUCCESS;
        }

        foreach ($targets as $duplex) {
            $this->rollback($duplex, $summaries, $dry);
        }

        return self::SUCCESS;
    }

    private function rollback(DuplexParcelUpdate $duplex, DuplexSummaryService $summaries, bool $dry): void
    {
        $conn = DB::connection('sqlsrv');
        $plan = $summaries->build($duplex);

        $created = collect($plan['stages'])
            ->flatMap(fn ($s) => collect($s['files'])->pluck('final'))
            ->filter()->unique()->values()->all();

        $sources = array_values(array_filter($plan['sources']));

        $this->line('');
        $this->info($duplex->duplex_id . '  (' . $duplex->status . ')');

        if (!$created) {
            $this->warn('  nothing was commissioned — skipped');
            return;
        }

        $this->line('  created : ' . implode(', ', $created));
        $this->line('  sources : ' . implode(', ', $sources));

        if ($dry) {
            foreach (self::TABLES as $table => $col) {
                $n = $conn->table($table)->whereIn($col, $created)->count();
                if ($n) $this->line(sprintf('    would delete %-22s %d', $table, $n));
            }
            return;
        }

        $conn->beginTransaction();
        try {
            $deleted = [];

            foreach (self::TABLES as $table => $col) {
                $deleted[$table] = $conn->table($table)->whereIn($col, $created)->delete();
            }

            foreach (['primary_file_number', 'mlsFNo'] as $col) {
                $deleted['PropID_Master'] = ($deleted['PropID_Master'] ?? 0)
                    + $conn->table('PropID_Master')->whereIn($col, $created)->delete();
            }

            // Both the source and any child a later stage replaced were archived.
            $deleted['decommissioned_files'] = $conn->table('decommissioned_files')
                ->whereIn('file_no', array_merge($created, $sources))->delete();

            // The application rows the commit materialised, found by their duplex tag.
            foreach ([
                'plot_subdivision_applications',
                'plot_merger_applications',
                'plot_separation_applications',
                'plot_extension_applications',
                'change_of_purpose_applications',
            ] as $table) {
                $deleted[$table] = $conn->table($table)
                    ->where('remarks', 'LIKE', '%' . $duplex->duplex_id . '%')->delete();
            }

            // Bring the source file back to life.
            $conn->table('fileNumber')->whereIn('mlsfNo', $sources)->update([
                'is_decommissioned'      => 0,
                'decommissioning_date'   => null,
                'decommissioning_reason' => null,
                'successor_file_no'      => null,
                'updated_at'             => now(),
            ]);

            $conn->table('file_indexings')->whereIn('file_number', $sources)->update([
                'is_decommissioned'      => 0,
                'decommissioned_at'      => null,
                'decommissioning_reason' => null,
                'successor_file_no'      => null,
                'updated_at'             => now(),
            ]);

            $conn->commit();

            foreach ($deleted as $table => $n) {
                if ($n) $this->line(sprintf('    deleted %-30s %d', $table, $n));
            }
        } catch (\Throwable $e) {
            $conn->rollBack();
            $this->error('  FAILED, rolled back: ' . $e->getMessage());
            return;
        }

        // The duplex's own rows: clear the real numbers, keep the plan and the
        // holding numbers so it can be commissioned again unchanged.
        DuplexParcelUpdateFile::where('duplex_parcel_update_id', $duplex->id)
            ->where('role', '!=', DuplexParcelUpdateFile::ROLE_SOURCE)
            ->update(['final_file_no' => null]);

        DuplexParcelUpdateFile::where('duplex_parcel_update_id', $duplex->id)
            ->where('role', DuplexParcelUpdateFile::ROLE_RESULT)
            ->update(['role' => DuplexParcelUpdateFile::ROLE_HOLDING]);

        $duplex->update([
            'status'       => DuplexParcelUpdate::STATUS_IN_LAND,
            'committed_at' => null,
            'committed_by' => null,
        ]);

        $this->info('    -> ' . $duplex->duplex_id . ' is in_land again, ready to commission');
    }
}
