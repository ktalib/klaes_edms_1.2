<?php

namespace App\Console;

use App\Console\Commands\CleanupExpiredDigitalAccess;
use App\Console\Commands\CleanupStaleDrafts;
use App\Console\Commands\RebuildPropIds;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        CleanupStaleDrafts::class,
        \App\Console\Commands\CleanupShelfLocationAndBatchUpdate::class,
        \App\Console\Commands\AssignGroupingMetadataCommand::class,
        RebuildPropIds::class,
        \App\Console\Commands\SyncPayrollRatesCommand::class,
        \App\Console\Commands\ProcessDailyAttendance::class,
        \App\Console\Commands\ProcessAutoLogout::class,
        \App\Console\Commands\VerifyCommissioningMirror::class,
        \App\Console\Commands\BackfillPropIdMaster::class,
        CleanupExpiredDigitalAccess::class,
        \App\Console\Commands\SpaTriggerSecondService::class,
        \App\Console\Commands\SpaSmsDoctor::class,
        \App\Console\Commands\PhsLowBalanceReminders::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('drafts:cleanup')->dailyAt('02:00')->withoutOverlapping();
        $schedule->command('attendance:process --queue')->dailyAt('05:30')->withoutOverlapping();
        $schedule->command('activity:auto-logout')->everyTenMinutes()->withoutOverlapping();

        // Release expired file number reservations regularly
        $schedule->call(function () {
            $service = app(\App\Services\FileNumberReservationService::class);
            $released = $service->releaseExpiredReservations();

            if ($released > 0) {
                \Log::info("Released {$released} expired file number reservations");
            }
        })->everyFiveMinutes()->name('cleanup-file-reservations');

        // Keep PropID_Master synced with legacy tables to prevent collisions
        $schedule->command('propid:backfill')->dailyAt('01:00')->withoutOverlapping();

        // Audit file_indexings.prop_id against PropID_Master. Read-only by design: raw-SQL
        // and bulk-import writes bypass the controller's validation, so this drift report is
        // the only thing that catches them. Review the CSV, then repair with --apply.
        $schedule->command('propid:reconcile-indexing')->dailyAt('01:30')->withoutOverlapping();

        // Clean up expired Digital File Request temp copies daily at 03:00
        $schedule->command('dfr:cleanup-expired')->dailyAt('03:00')->withoutOverlapping();

        // Auto-trigger second serve notices for SPAS first-serve records 14+ days old
        $schedule->command('spa:trigger-second-service')->dailyAt('08:00')->withoutOverlapping();

        // Warn PHS organizations whose token balance is running low (daily).
        $schedule->command('phs:low-balance-reminders')->dailyAt('07:00')->withoutOverlapping();
     }



    //  protected function  op(Schedule $schedule)
    //  // op tenmpfileno and prop_id  check and clear 
     
    //  {
    //       $schedule->call;
    //       $opService = app(\App\Services\OpCleanupService::class);$opService->cleanupOpTempFilenoAndPropId();
    //       $

    //  }
     
 



    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
