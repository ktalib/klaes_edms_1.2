<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
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
    }
}