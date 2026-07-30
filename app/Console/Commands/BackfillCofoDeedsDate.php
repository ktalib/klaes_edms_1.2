<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfills CofO_staging.deeds_date / deeds_time from transaction_date /
 * transaction_time.
 *
 * For Certificate of Occupancy records the registration date/time were captured
 * into transaction_date / transaction_time, while deeds_date / deeds_time (the
 * canonical "Reg Date" / "Reg Time" columns the report, timeline and edit form
 * prefer — same convention as the `pra` table) were left NULL. As a result the
 * REG DATE column rendered "-" and the edit modal's Reg Date field loaded empty.
 *
 * This copies the value across only where the deeds_* column is still empty and
 * the transaction_* column has a value, so it is idempotent and forward-only:
 * a deeds_date already set by hand is never overwritten. All five columns are
 * nvarchar, so the copy is a plain string move (no date parsing).
 */
class BackfillCofoDeedsDate extends Command
{
    protected $signature = 'cofo:backfill-deeds-date
        {--dry-run : Report how many rows would be filled without writing}';

    protected $description = 'Backfill CofO_staging deeds_date/deeds_time from transaction_date/transaction_time (idempotent, only fills empties).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $conn = DB::connection('sqlsrv');

        // Rows whose canonical column is still empty but the transaction_* source
        // carries a value. Each dimension (date/time) is handled independently so
        // a row with a time but no date (or vice versa) is still filled.
        $needsDate = fn () => $conn->table('CofO_staging')
            ->where(fn ($q) => $q->whereNull('deeds_date')->orWhereRaw("LTRIM(RTRIM(deeds_date)) = ''"))
            ->whereNotNull('transaction_date')
            ->whereRaw("LTRIM(RTRIM(transaction_date)) <> ''");

        $needsTime = fn () => $conn->table('CofO_staging')
            ->where(fn ($q) => $q->whereNull('deeds_time')->orWhereRaw("LTRIM(RTRIM(deeds_time)) = ''"))
            ->whereNotNull('transaction_time')
            ->whereRaw("LTRIM(RTRIM(transaction_time)) <> ''");

        if ($dryRun) {
            $this->info("Dry run — no rows written.");
            $this->line("  deeds_date  would fill: {$needsDate()->count()}");
            $this->line("  deeds_time  would fill: {$needsTime()->count()}");
            return self::SUCCESS;
        }

        $dateFilled = $needsDate()->update([
            'deeds_date' => DB::raw('transaction_date'),
            'updated_at' => now(),
        ]);

        $timeFilled = $needsTime()->update([
            'deeds_time' => DB::raw('transaction_time'),
            'updated_at' => now(),
        ]);

        $this->info("Done. Filled deeds_date on {$dateFilled} row(s); deeds_time on {$timeFilled} row(s).");

        return self::SUCCESS;
    }
}
