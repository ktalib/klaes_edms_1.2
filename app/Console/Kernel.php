<?php

namespace App\Console;

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
