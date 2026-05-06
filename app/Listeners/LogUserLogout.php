<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use App\Models\UserActivityLog;
use App\Services\ActivityLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogUserLogout
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
     * @param  \Illuminate\Auth\Events\Logout  $event
     * @return void
     */
    public function handle(Logout $event)
    {
        try {
            $user = $event->user;
            $userId = null;

            if (is_object($user)) {
                if (method_exists($user, 'getAuthIdentifier')) {
                    $userId = $user->getAuthIdentifier();
                } elseif (method_exists($user, '__get') || property_exists($user, 'id')) {
                    $userId = data_get($user, 'id');
                }
            }

            if ($userId) {
                // Mark current session as logged out
                $updated = UserActivityLog::where('user_id', $userId)
                    ->where('is_online', true)
                    ->update([
                        'is_online' => false,
                        'logout_time' => now(),
                        'status' => 'Offline',
                    ]);

                if ($updated > 0) {
                    ActivityLogService::flushOnlineCaches();
                }

                // Log the logout activity
                UserActivityLog::logActivity($userId, 'logout', [
                    'activity_description' => 'User logged out successfully'
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to log user logout: ' . $e->getMessage());
        }
    }
}