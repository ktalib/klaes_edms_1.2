<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserActivityLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * The in-app half of the attendance notices — the bell entry that goes with the
 * sign-in / sign-out SMS.
 *
 * WHY THIS IS NOT SIMPLY A SECOND CHANNEL ON StaffAttendanceSmsService
 *
 * That class carries three rules that exist only because a text message costs
 * money: one message per person per day, the sign-out held back until the
 * member of staff reaches the end of their own shift, and nothing at all for
 * the ~1,280 of 1,562 staff with no number on file. A notification is a row in
 * a local table. It costs nothing, it reaches everybody, and the request was a
 * notification on EVERY sign-in and sign-out — so this class deliberately has
 * no once-a-day cap, no shift-end gate and no phone-number requirement, and it
 * keeps working when the SMS feature is switched off (STAFF_SMS_ENABLED
 * defaults to false, so on a fresh deployment it usually is).
 *
 * WRITTEN INSIDE THE REQUEST, NOT AFTER IT
 *
 * The SMS goes out through SendStaffAttendanceSms::afterResponse() because it
 * is an outbound HTTPS call with a 40s timeout and must never sit in front of a
 * sign-in. This is a single INSERT on a connection the request already holds,
 * so it runs inline — and it has to: the post-login flash (notification-flash.js)
 * asks for unread notifications as soon as the dashboard paints, and a row
 * written after the response has already lost that race.
 *
 * THE CLOCK
 * Times are rendered in config('attendance.notifications.timezone'),
 * Africa/Lagos. config('app.timezone') is UTC on this deployment, so now()
 * reads 16:00 while the office clock says 17:00 — a body built from the app
 * clock would tell every member of staff they signed out an hour before they
 * did.
 */
class StaffAttendanceNotificationService
{
    public const TYPE_LOGIN = 'attendance.login';
    public const TYPE_LOGOUT = 'attendance.logout';
    public const MODULE = 'attendance';

    /** A sign-out the user asked for by clicking Log Out. */
    public const MODE_MANUAL = 'manual';

    /** A session ActivityLogService closed on its own once the shift ran out. */
    public const MODE_AUTOMATIC = 'automatic';

    /** A session an administrator ended from the user management screens. */
    public const MODE_FORCED = 'forced';

    public function __construct(private UserNotificationService $notifications)
    {
    }

    /**
     * Record the sign-in notification.
     *
     * @param UserActivityLog|null $session The row ActivityLogService::recordLogin
     *        just created. It already holds the resolved IP, browser and
     *        platform, which is what makes this notice worth reading: "signed
     *        in from Chrome on Windows" is how a user spots a sign-in that was
     *        not theirs.
     */
    public function login(User $user, ?UserActivityLog $session = null, ?Carbon $at = null): ?Notification
    {
        if (!$this->enabled('login')) {
            return null;
        }

        $at = $this->localise($at);
        $where = $this->describeOrigin($session);

        $body = "You signed in at {$this->timeOf($at)} on {$this->dateOf($at)}"
            . ($where !== '' ? " from {$where}" : '')
            . '. If this was not you, please contact ICT.';

        return $this->record($user, self::TYPE_LOGIN, 'Sign-in recorded', $body, [
            'event' => 'login',
            'mode' => self::MODE_MANUAL,
            'event_at' => $at->toIso8601String(),
            'ip' => $session->ip_address ?? null,
            'browser' => $session->browser ?? null,
            'platform' => $session->platform ?? null,
        ]);
    }

    /**
     * Record the sign-out notification.
     *
     * Unlike the SMS this fires whatever the hour: a lunchtime sign-out sends
     * no text (it would spend the day's message) but still leaves a bell entry.
     *
     * @param string      $mode   One of the MODE_* constants — a session the user
     *                            ended, one the shift clock ended, or one an
     *                            administrator ended. The user cannot tell those
     *                            apart from a bare "you signed out".
     * @param string|null $reason Free text from the caller (the auto-logout
     *                            description, or the administrator's reason).
     */
    public function logout(
        User $user,
        ?Carbon $at = null,
        string $mode = self::MODE_MANUAL,
        ?string $reason = null
    ): ?Notification {
        if (!$this->enabled('logout')) {
            return null;
        }

        $at = $this->localise($at);
        $when = "{$this->timeOf($at)} on {$this->dateOf($at)}";

        [$title, $body] = match ($mode) {
            self::MODE_AUTOMATIC => [
                'Session closed automatically',
                "Your KLAES session was closed automatically at {$when} because your shift had ended.",
            ],
            self::MODE_FORCED => [
                'Session ended by an administrator',
                "Your KLAES session was ended by an administrator at {$when}.",
            ],
            default => [
                'Sign-out recorded',
                "You signed out at {$when}. Thank you for today.",
            ],
        };

        return $this->record($user, self::TYPE_LOGOUT, $title, $body, [
            'event' => 'logout',
            'mode' => $mode,
            'event_at' => $at->toIso8601String(),
            'reason' => $reason,
        ]);
    }

    /**
     * Write the row, swallowing anything that goes wrong.
     *
     * A notification must never take a sign-in or a sign-out down with it — the
     * user still has to get in, and on the logout side the session still has to
     * close. The log line is the record when the insert fails.
     */
    private function record(User $user, string $type, string $title, string $body, array $data): ?Notification
    {
        if ($this->alreadyNotified($user, $type)) {
            return null;
        }

        try {
            return $this->notifications->create(
                (int) $user->id,
                $type,
                $title,
                $body,
                array_filter($data, fn ($value) => $value !== null && $value !== ''),
                ['module' => self::MODULE, 'name' => $title]
            );
        } catch (\Throwable $e) {
            Log::error('StaffAttendanceNotificationService: could not record an attendance notification', [
                'user_id' => $user->id,
                'type' => $type,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Has the same notice just gone out?
     *
     * Not a once-a-day rule — a short window, and it exists for one specific
     * shape of duplicate: ActivityLogService closes sessions ROW BY ROW, so a
     * user carrying three stale session rows is finalised three times in the
     * same loop and would collect three identical "session closed" cards.
     * Genuine sign-ins minutes apart are still each announced.
     */
    private function alreadyNotified(User $user, string $type): bool
    {
        $window = (int) config('attendance.notifications.duplicate_window_seconds', 60);

        if ($window <= 0) {
            return false;
        }

        try {
            // created_at is written on the app clock (UTC), so the cutoff is
            // built on it too — this comparison must not be localised.
            return Notification::query()
                ->forUser((int) $user->id)
                ->where('type', $type)
                ->where('created_at', '>=', now()->subSeconds($window))
                ->exists();
        } catch (\Throwable $e) {
            // A failed lookup must not cost the user the notification itself.
            return false;
        }
    }

    /**
     * "Chrome on Windows (105.112.4.9)", from whatever the session row has.
     */
    private function describeOrigin(?UserActivityLog $session): string
    {
        if (!$session) {
            return '';
        }

        $browser = trim((string) ($session->browser ?? ''));
        $platform = trim((string) ($session->platform ?? ''));
        $ip = trim((string) ($session->ip_address ?? ''));

        $agent = trim($browser . ($browser !== '' && $platform !== '' ? ' on ' : '') . $platform);

        if ($agent !== '' && $ip !== '') {
            return "{$agent} ({$ip})";
        }

        return $agent !== '' ? $agent : $ip;
    }

    private function enabled(string $event): bool
    {
        return (bool) config('attendance.notifications.enabled', true)
            && (bool) config('attendance.notifications.' . $event, true);
    }

    private function timeOf(Carbon $at): string
    {
        return $at->format('h:i A');
    }

    private function dateOf(Carbon $at): string
    {
        return $at->format('d/m/Y');
    }

    /**
     * Move a moment onto the office clock, defaulting to now.
     */
    private function localise(?Carbon $at): Carbon
    {
        $timezone = config('attendance.notifications.timezone', 'Africa/Lagos');

        return $at
            ? $at->copy()->setTimezone($timezone)
            : Carbon::now($timezone);
    }
}
