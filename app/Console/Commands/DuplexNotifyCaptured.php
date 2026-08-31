<?php

namespace App\Console\Commands;

use App\Models\DuplexParcelUpdate;
use App\Services\ParcelUpdateNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Send the "duplex captured" notification for duplexes that never got one.
 *
 * The notification has always been raised at the right moment — saveStage() fires it
 * as the last stage is captured — but it was addressed to users selected by the
 * "Generate New FileNo (MLSFileNo)" role inside the comma-separated assign_role
 * column, and NO user in Land carries it. Every one of those sends went to an empty
 * list and logged "0 users notified" as though it had worked.
 *
 * The recipient rule is now MLPP staff in department 3 plus the administrators, and
 * this replays the notification for the duplexes that were captured while the old
 * rule was in force. Nothing else about them is touched: it writes notification rows
 * and reads duplex rows, and that is all.
 *
 * Dry-run by default, like the other repair commands here — it reports who would be
 * told about what, and writes nothing until --apply.
 *
 *   php artisan duplex:notify-captured
 *   php artisan duplex:notify-captured --apply
 *   php artisan duplex:notify-captured --duplex=DPX-2026-0017 --apply
 */
class DuplexNotifyCaptured extends Command
{
    protected $signature = 'duplex:notify-captured
        {--apply : Actually send. Omit for a read-only report.}
        {--duplex= : One duplex ID, e.g. DPX-2026-0017. Default: every eligible duplex.}
        {--include-later : Also duplexes that have moved past capture (pending, approved, in_land, committed).}
        {--force : Send even where a captured-notification already exists for that duplex.}';

    protected $description = 'Replay the "duplex captured" notification for duplexes captured before the recipient rule was fixed';

    /**
     * A duplex is past capture once every stage is done. `captured` is the status it
     * lands on, but an officer who carried straight on to KNUPDA and approval left it
     * further along — those were captured too, and missed the same notification.
     */
    private const LATER_STATUSES = [
        DuplexParcelUpdate::STATUS_PENDING,
        DuplexParcelUpdate::STATUS_APPROVED,
        DuplexParcelUpdate::STATUS_IN_LAND,
        DuplexParcelUpdate::STATUS_COMMITTED,
    ];

    public function handle(ParcelUpdateNotificationService $notifier): int
    {
        $apply = (bool) $this->option('apply');

        $statuses = array_merge(
            [DuplexParcelUpdate::STATUS_CAPTURED],
            $this->option('include-later') ? self::LATER_STATUSES : []
        );

        $query = DuplexParcelUpdate::visible()->whereIn('status', $statuses);

        if ($one = $this->option('duplex')) {
            $query->where('duplex_id', $one);
        }

        $duplexes = $query->orderBy('id')->get();

        if ($duplexes->isEmpty()) {
            $this->warn('No duplex matches. Statuses looked at: ' . implode(', ', $statuses));
            return self::SUCCESS;
        }

        $recipients = $this->recipientCount();

        if ($recipients === 0) {
            $this->error('No recipients: nobody is MLPP in department 3, and there is no admin.');
            return self::FAILURE;
        }

        $this->line(($apply ? '[APPLY] ' : '[DRY-RUN] ')
            . "{$duplexes->count()} duplex(es), {$recipients} recipient(s) each.");
        $this->newLine();

        $sent = 0;
        $skipped = 0;

        foreach ($duplexes as $duplex) {
            $already = $this->alreadyNotified($duplex);

            if ($already && !$this->option('force')) {
                $this->line(sprintf('  %-14s %-10s already notified (%d row(s)) — skipped',
                    $duplex->duplex_id, $duplex->status, $already));
                $skipped++;
                continue;
            }

            $this->line(sprintf('  %-14s %-10s %s', $duplex->duplex_id, $duplex->status,
                $apply ? 'sending…' : 'would notify ' . $recipients));

            if (!$apply) {
                continue;
            }

            // The same call saveStage() makes, so the copy and the payload are
            // identical to a notification raised at the moment of capture.
            $notifier->notifyCreated(
                'duplex',
                $duplex->id,
                $duplex->duplex_id,
                (string) $duplex->file_title,
                (string) $duplex->applicant_name
            );

            $sent++;
        }

        $this->newLine();

        if ($apply) {
            $this->info("Done. {$sent} duplex(es) notified, {$skipped} skipped, "
                . ($sent * $recipients) . ' notification row(s) written.');
        } else {
            $this->info(($duplexes->count() - $skipped) . ' duplex(es) would be notified, '
                . $skipped . ' skipped — about '
                . (($duplexes->count() - $skipped) * $recipients) . ' notification row(s).');
            $this->line('Re-run with --apply to send.');
        }

        return self::SUCCESS;
    }

    /** MLPP in Land, plus the administrators — the rule the service now uses. */
    private function recipientCount(): int
    {
        return DB::connection('sqlsrv')->table('users')
            ->where(function ($q) {
                $q->where(function ($land) {
                    $land->where('staff_type_category', 'MLPP')->where('department_id', 3);
                })->orWhere('is_admin', 1);
            })
            ->count();
    }

    /**
     * Has this duplex's capture already been announced?
     *
     * Matched on the duplex id inside the notification's data payload, which carries
     * file_no. Keeps a re-run from sending a second copy to 164 people.
     */
    private function alreadyNotified(DuplexParcelUpdate $duplex): int
    {
        return DB::connection('sqlsrv')->table('notifications')
            ->where('module', 'parcel_update')
            ->where('data', 'LIKE', '%"file_no":"' . $duplex->duplex_id . '"%')
            ->where('data', 'LIKE', '%"event":"created"%')
            ->count();
    }
}
