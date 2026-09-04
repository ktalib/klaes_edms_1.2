<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use App\Jobs\SendStaffAttendanceSms;
use App\Models\StaffSmsLog;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Log;

class LogUserLogin
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \Illuminate\Auth\Events\Login  $event
     * @return void
     */
    public function handle(Login $event)
    {
        // Staff sign-ins only. The portal guards (phs, online_ls, laas) fire this
        // same event, and their users are not App\Models\User — user_activity_logs
        // has a foreign key to users.id, so logging a portal account here either
        // violates that key or, worse, attributes the activity to whichever STAFF
        // user happens to hold the same id.
        if (!$event->user instanceof User) {
            return;
        }

        try {
            $result = ActivityLogService::recordLogin($event->user);

            if (!$result) {
                Log::warning('Login event fired but activity log entry was not created', [
                    'user_id' => $event->user->id,
                    'reason' => 'recordLogin returned null',
                ]);
            }
        } catch (\Throwable $exception) {
            Log::error('Failed to record login activity', [
                'user_id' => $event->user->id ?? null,
                'message' => $exception->getMessage(),
            ]);
        }

        // The day's sign-in SMS. Queued to run after the response so a slow
        // gateway can never hold up the sign-in itself; the once-a-day check
        // lives in StaffAttendanceSmsService, not here.
        try {
            SendStaffAttendanceSms::queueFor($event->user->id, StaffSmsLog::TYPE_LOGIN);
        } catch (\Throwable $exception) {
            Log::error('Failed to queue login SMS', [
                'user_id' => $event->user->id ?? null,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}