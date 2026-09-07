<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The phone-number verification gate: send a code to the number on an account,
 * take it back, and record that the number really does reach its owner.
 *
 * WHY THE SYSTEM ASKS AT ALL
 * users.phone_number is the address every SMS in this codebase is aimed at, and
 * nothing has ever proved a single one of those numbers. 1,140 accounts carry
 * no number and 140 more carry something that is not a usable Nigerian mobile —
 * and a wrong number that LOOKS usable is the worst case of the three, because
 * it reads as a way of contacting somebody. A code typed back off the handset
 * is the only proof available.
 *
 * THE GATE IS NOT A LOGIN BLOCK. Signing in is untouched; the account is held
 * afterwards, at a card, exactly like the mandatory passport photo. That is
 * deliberate: a member of staff whose number is wrong must be able to get far
 * enough in to correct it, and correcting it is the whole point of the exercise.
 *
 * THREE THINGS CAN LOCK PEOPLE OUT, AND ALL THREE FAIL OPEN HERE
 *  1. The columns not being deployed yet (code uploaded before the SQL script) —
 *     columnsReady() leaves the gate open, because a gate that cannot record a
 *     success would hold everybody for ever.
 *  2. The gateway. Codes go out on the promotional route, which telcos hold
 *     between 19:45 and 08:00 (status 609) and which never reaches a DND-blocked
 *     handset. Those are reported to the user in plain words rather than as a
 *     failed send, and `phone:verify-status --user=<id> --verify` passes anybody
 *     through by hand.
 *  3. The whole feature: config('phone_verification.enabled').
 *
 * THE MESSAGE DOES NOT GO THROUGH THE SMS CONTROL CENTRE. Everything in that
 * catalogue ships disabled and can be switched off from a web page; a sign-in
 * code that somebody can turn off by accident locks the Ministry out of its own
 * system. This one talks to BulkSmsNgService directly.
 */
class PhoneOtpService
{
    /** Cache key holding the wrong-code count for one user. */
    private const ATTEMPTS_KEY = 'phone_otp.attempts.';

    /** How long the answer to "are the columns deployed?" is remembered. */
    private const SCHEMA_CACHE_KEY = 'phone_otp.columns_ready';
    private const SCHEMA_CACHE_TTL = 300;

    /** Per-request memo of the schema check; see columnsReady(). */
    private static ?bool $columnsReadyMemo = null;

    public function __construct(private BulkSmsNgService $gateway)
    {
    }

    /**
     * Is the gate switched on AND able to record a result?
     */
    public function gateEnabled(): bool
    {
        return (bool) config('phone_verification.enabled', true) && $this->columnsReady();
    }

    /**
     * Should this user be held at the verification card?
     *
     * The passport photo comes first: User::getNeedsPhoneVerificationAttribute()
     * defers to it so a user never faces two blocking cards at once.
     */
    public function needsVerification(User $user): bool
    {
        if (!$this->gateEnabled()) {
            return false;
        }

        return !$this->isVerified($user);
    }

    public function isVerified(User $user): bool
    {
        return (bool) ($user->getAttribute('is_otp_verified') ?? false);
    }

    /**
     * Are users.otp / otp_sent_at / is_otp_verified actually on this database?
     *
     * Cached: this is asked on effectively every request through the middleware,
     * and hasColumn() is a catalogue query. Five minutes is short enough that a
     * deployment does not need a cache flush to take effect.
     */
    public function columnsReady(): bool
    {
        // Asked several times in one page render — the middleware, the header banner
        // and the sidebar each consult the gate — so the answer is memoised for the
        // request as well as cached across them. A table's shape cannot change
        // mid-request, so this is only ever saving reads.
        if (self::$columnsReadyMemo !== null) {
            return self::$columnsReadyMemo;
        }

        $cached = Cache::get(self::SCHEMA_CACHE_KEY);

        if ($cached !== null) {
            return self::$columnsReadyMemo = (bool) $cached;
        }

        try {
            $schema = Schema::connection('sqlsrv');
            $ready = $schema->hasColumn('users', 'is_otp_verified')
                && $schema->hasColumn('users', 'otp')
                && $schema->hasColumn('users', 'otp_sent_at');
        } catch (\Throwable $e) {
            // The database could not be asked. Hold nobody on the strength of a
            // question that was never answered.
            Log::warning('PhoneOtpService: could not inspect the users table', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }

        Cache::put(self::SCHEMA_CACHE_KEY, $ready, self::SCHEMA_CACHE_TTL);

        return self::$columnsReadyMemo = $ready;
    }

    /**
     * Send a fresh code to a number, storing it and the number on the account.
     *
     * @param string|null $phone The number the user confirmed or corrected on the
     *                           card. Null means "use what is on file".
     *
     * @return array{sent:bool, message:string, phone:string|null, masked:string|null,
     *               retry_after:int, expires_in:int, reason:string|null}
     */
    public function sendCode(User $user, ?string $phone = null): array
    {
        if (!$this->columnsReady()) {
            return $this->result(false, __('Phone verification is not available on this server yet. Please contact ICT.'));
        }

        $raw = trim((string) ($phone ?? $user->phone_number ?? ''));

        if ($raw === '') {
            return $this->result(false, __('Enter the mobile number that should receive the code.'));
        }

        $normalised = BulkSmsNgService::normalizeNumber($raw);

        if ($normalised === null) {
            return $this->result(false, __('That is not a usable Nigerian mobile number. Enter it as 08012345678.'));
        }

        // A code already sent to THIS number, recently enough that a second one
        // would only cost credit. A different number is always allowed straight
        // away: the user has just told us the first one was wrong.
        $sameNumber = BulkSmsNgService::normalizeNumber((string) ($user->phone_number ?? '')) === $normalised;
        $wait = $this->cooldownRemaining($user);

        if ($sameNumber && $wait > 0) {
            return $this->result(
                false,
                trans_choice(
                    '{1}Your code was just sent. Wait a second before asking for another.|[2,*]Your code was just sent. Wait :seconds seconds before asking for another.',
                    $wait,
                    ['seconds' => $wait]
                ),
                $normalised,
                $wait
            );
        }

        $code = $this->generateCode();

        // Written BEFORE the gateway call, and kept even if the send fails: ICT
        // reads the code back to staff whose handset is out of coverage or whose
        // network is holding promotional traffic until 08:30, and that support
        // route is the only one those people have.
        $user->forceFill([
            'phone_number' => $normalised,
            'otp' => $code,
            'otp_sent_at' => now(),
            // Changing the number voids any earlier proof. Belt and braces: the
            // model event does this too, and this path must not depend on it.
            'is_otp_verified' => false,
        ])->save();

        $this->clearAttempts($user);

        // A sign-in code goes to a member of staff, so it carries the staff
        // sender ID (KLAES). Null if the catalogue cannot be read, which leaves
        // the server default in place rather than failing the send.
        $sender = null;

        try {
            $sender = \App\Models\SmsSetting::senderFor(\App\Models\SmsSetting::KEY_PHONE_OTP);
        } catch (\Throwable $e) {
            // Fall through on the server default.
        }

        $accepted = $this->gateway->sendFirstAccepted($normalised, $this->messagesFor($code), $sender);

        if ($accepted !== null) {
            $this->logDispatch($user, $normalised, true, $this->gateway->lastStatusCode(), null);

            return $this->result(
                true,
                __('A verification code has been sent to :phone. It expires in :minutes minutes.', [
                    'phone' => $this->mask($normalised),
                    'minutes' => $this->ttlMinutes(),
                ]),
                $normalised,
                $this->cooldownSeconds()
            );
        }

        $reason = $this->gateway->lastFailureReason();
        $codeOut = $this->gateway->lastStatusCode();

        Log::warning('PhoneOtpService: verification code not delivered', [
            'user_id' => $user->id,
            'phone' => $normalised,
            'gateway_code' => $codeOut,
            'reason' => $reason,
        ]);

        $this->logDispatch($user, $normalised, false, $codeOut, $reason);

        return $this->result(
            false,
            $this->explainFailure($codeOut, $reason),
            $normalised,
            $this->cooldownSeconds(),
            $reason
        );
    }

    /**
     * Check a code the user typed back.
     *
     * @return array{verified:bool, message:string, attempts_left:int|null}
     */
    public function verifyCode(User $user, string $code): array
    {
        if (!$this->columnsReady()) {
            return ['verified' => false, 'message' => __('Phone verification is not available on this server yet. Please contact ICT.'), 'attempts_left' => null];
        }

        $expected = trim((string) ($user->getAttribute('otp') ?? ''));
        $given = preg_replace('/\D+/', '', $code) ?? '';

        if ($expected === '') {
            return ['verified' => false, 'message' => __('No code has been sent yet. Ask for one first.'), 'attempts_left' => null];
        }

        if ($this->hasExpired($user)) {
            return ['verified' => false, 'message' => __('That code has expired. Ask for a new one.'), 'attempts_left' => null];
        }

        if (!hash_equals($expected, $given)) {
            $left = $this->countWrongAttempt($user);

            if ($left <= 0) {
                // Burn the code rather than the account: the user asks for
                // another one and carries on. Nothing here locks a person out —
                // a lockout would need an administrator to undo, and the number
                // being wrong is the very thing they are trying to fix.
                $user->forceFill(['otp' => null])->save();
                $this->clearAttempts($user);

                return ['verified' => false, 'message' => __('Too many wrong codes. Ask for a new one.'), 'attempts_left' => 0];
            }

            return [
                'verified' => false,
                'message' => trans_choice(
                    '{1}That code is not correct. One more attempt before you must request a new code.|[2,*]That code is not correct. :count attempts left.',
                    $left,
                    ['count' => $left]
                ),
                'attempts_left' => $left,
            ];
        }

        $this->markVerified($user);

        return [
            'verified' => true,
            'message' => __('Your phone number has been verified. You can now use the system.'),
            'attempts_left' => null,
        ];
    }

    /**
     * Record the account as verified and drop the code.
     *
     * Also used by `phone:verify-status --verify`, which is how somebody whose
     * handset cannot receive the SMS at all is let through.
     */
    public function markVerified(User $user): void
    {
        $user->forceFill([
            'is_otp_verified' => true,
            'otp' => null,
        ])->save();

        $this->clearAttempts($user);
    }

    /**
     * Put an account back behind the gate.
     */
    public function reset(User $user): void
    {
        $user->forceFill([
            'is_otp_verified' => false,
            'otp' => null,
            'otp_sent_at' => null,
        ])->save();

        $this->clearAttempts($user);
    }

    /**
     * Seconds until this user may ask for another code on the same number.
     */
    public function cooldownRemaining(User $user): int
    {
        $sentAt = $user->getAttribute('otp_sent_at');

        if (!$sentAt) {
            return 0;
        }

        $sentAt = $sentAt instanceof Carbon ? $sentAt : Carbon::parse($sentAt);
        $elapsed = $sentAt->diffInSeconds(now(), false);

        // A negative elapsed time means the row carries a moment in the future —
        // a clock change, or a value written by another process. Treat it as
        // "just sent" rather than letting it hand out an unlimited cooldown.
        if ($elapsed < 0) {
            return $this->cooldownSeconds();
        }

        return (int) max(0, $this->cooldownSeconds() - $elapsed);
    }

    /**
     * Has the code on file passed its expiry?
     */
    public function hasExpired(User $user): bool
    {
        $sentAt = $user->getAttribute('otp_sent_at');

        if (!$sentAt) {
            return true;
        }

        $sentAt = $sentAt instanceof Carbon ? $sentAt : Carbon::parse($sentAt);

        return $sentAt->addMinutes($this->ttlMinutes())->isPast();
    }

    /**
     * "0803****678" — enough for the user to recognise the number without
     * printing it in full on a screen somebody else may be looking at.
     */
    public function mask(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        // Show it the way staff read it back: local 0-leading form.
        if (str_starts_with($digits, '234') && strlen($digits) === 13) {
            $digits = '0' . substr($digits, 3);
        }

        if (strlen($digits) <= 5) {
            return $digits;
        }

        return substr($digits, 0, 4) . str_repeat('*', max(0, strlen($digits) - 7)) . substr($digits, -3);
    }

    /**
     * The number as the card should show it for editing: local 0-leading form.
     */
    public function localFormat(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (str_starts_with($digits, '234') && strlen($digits) === 13) {
            return '0' . substr($digits, 3);
        }

        return $digits;
    }

    public function ttlMinutes(): int
    {
        return max(1, (int) config('phone_verification.ttl_minutes', 15));
    }

    public function cooldownSeconds(): int
    {
        return max(0, (int) config('phone_verification.resend_cooldown_seconds', 60));
    }

    public function maxAttempts(): int
    {
        return max(1, (int) config('phone_verification.max_attempts', 5));
    }

    /**
     * Best wording first, then a plainer one.
     *
     * Both fit one 160-character page. The fallback exists because these
     * gateways run keyword filters that refuse a wording outright while
     * answering HTTP 200 — BetaSMS does it on "code" and "approved" — and
     * BulkSmsNgService::sendFirstAccepted retries on the one status that could
     * plausibly be about the text (602).
     *
     * @return array<int,string>
     */
    private function messagesFor(string $code): array
    {
        $tokens = ['Code' => $code, 'Minutes' => (string) $this->ttlMinutes()];

        /*
         | The wording is editable in the SMS Control Centre, like every other
         | message -- what this one does NOT get there is an off switch; see
         | SmsSetting::KEY_PHONE_OTP. An override saved on the page wins; with
         | none saved, the catalogue default applies; and if the catalogue cannot
         | be read at all we fall back to config/phone_verification.php, which is
         | where this wording lived before.
         */
        try {
            $dispatcher = app(\App\Services\Sms\KlaesSmsDispatcher::class);
            [$rich, $plain] = $dispatcher->wordings(\App\Models\SmsSetting::KEY_PHONE_OTP, $tokens);

            if ($rich !== null) {
                return array_values(array_filter([$rich, $plain]));
            }
        } catch (\Throwable $e) {
            Log::warning('PhoneOtpService: falling back to the built-in wording', [
                'message' => $e->getMessage(),
            ]);
        }

        // {code} is the legacy placeholder in config/phone_verification.php.
        $primary = (string) config('phone_verification.message');
        $fallback = (string) config('phone_verification.message_fallback');

        return [
            str_replace('{code}', $code, $primary),
            str_replace('{code}', $code, $fallback),
        ];
    }

    /**
     * Record a verification code in sms_dispatch_logs, so it shows up on the
     * SMS Control Centre next to every other message.
     *
     * THE CODE ITSELF IS NEVER WRITTEN THERE. That log is readable by anyone who
     * can open the control page; storing a live sign-in code in it would hand
     * them somebody else's account. Only the outcome is kept.
     *
     * No dedupe key: a resend is a deliberate second send, not a duplicate.
     */
    private function logDispatch(User $user, string $phone, bool $sent, ?string $gatewayCode, ?string $reason): void
    {
        try {
            \App\Models\SmsDispatchLog::create([
                'message_key' => \App\Models\SmsSetting::KEY_PHONE_OTP,
                'phone' => $phone,
                'message' => '[verification code withheld]',
                'status' => $sent
                    ? \App\Models\SmsDispatchLog::STATUS_SENT
                    : \App\Models\SmsDispatchLog::STATUS_FAILED,
                'gateway_code' => $gatewayCode,
                'failure_reason' => $reason,
                'attempts' => 1,
                'subject_type' => 'users',
                'subject_id' => (string) $user->id,
                'dedupe_key' => null,
                'event_at' => now(config('klaes_sms.timezone', 'Africa/Lagos'))->toDateTimeString(),
                'created_by' => $user->name ?? null,
            ]);
        } catch (\Throwable $e) {
            // The log table may not be deployed. Never let bookkeeping stop
            // somebody receiving the code they need to sign in.
            Log::debug('PhoneOtpService: could not write the dispatch log', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Turn a gateway status into something the person at the card can act on.
     *
     * "Message not sent" tells a member of staff nothing they can do. Every one
     * of these has a different next step, and 609 in particular is not a fault
     * at all — the message was accepted and is simply being held by the network.
     */
    private function explainFailure(?string $status, ?string $reason): string
    {
        return match ($status) {
            '609' => __('Nigerian networks do not deliver these messages between 7:45pm and 8:00am, so your code will not arrive until about 8:30am. Contact ICT if you need access before then.'),
            '604' => __('Codes cannot be sent at the moment because the SMS account has run out of credit. Please contact ICT.'),
            '601' => __('The SMS service is not configured on this server. Please contact ICT.'),
            default => $reason
                ? __('Your code could not be sent: :reason. If it keeps failing, contact ICT.', ['reason' => $reason])
                : __('Your code could not be sent. Check the number and try again, or contact ICT.'),
        };
    }

    private function generateCode(): string
    {
        $length = max(4, min(10, (int) config('phone_verification.code_length', 6)));

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Record a wrong code and say how many tries are left.
     */
    private function countWrongAttempt(User $user): int
    {
        $key = self::ATTEMPTS_KEY . $user->id;

        try {
            $used = (int) Cache::get($key, 0) + 1;
            Cache::put($key, $used, now()->addMinutes($this->ttlMinutes()));
        } catch (\Throwable $e) {
            // No cache, no counter. The route throttle still applies, and a
            // wrong code is still a wrong code.
            return $this->maxAttempts();
        }

        return max(0, $this->maxAttempts() - $used);
    }

    private function clearAttempts(User $user): void
    {
        try {
            Cache::forget(self::ATTEMPTS_KEY . $user->id);
        } catch (\Throwable $e) {
            // Nothing to do: a stale counter costs the user one extra resend.
        }
    }

    /**
     * @return array{sent:bool, message:string, phone:string|null, masked:string|null,
     *               retry_after:int, expires_in:int, reason:string|null}
     */
    private function result(bool $sent, string $message, ?string $phone = null, int $retryAfter = 0, ?string $reason = null): array
    {
        return [
            'sent' => $sent,
            'message' => $message,
            'phone' => $phone,
            'masked' => $this->mask($phone),
            'retry_after' => $retryAfter,
            'expires_in' => $sent ? $this->ttlMinutes() * 60 : 0,
            'reason' => $reason,
        ];
    }
}
