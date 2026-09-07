<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BulkSmsNgService;
use App\Services\PhoneOtpService;
use Illuminate\Console\Command;

/**
 * The phone-verification gate: who it is holding, and the way past it.
 *
 * This command exists because the gate can lock the entire Ministry out of its own
 * system through no fault of any user: the code travels by SMS, and the SMS depends
 * on a wallet with credit, a gateway that is reachable, a route that does not defer
 * everything sent after 19:45 until 08:30, and a handset that accepts bulk traffic.
 * When any of those fails there has to be a way through that does not involve editing
 * the database by hand at eight in the morning.
 *
 *   php artisan phone:verify-status                     what the gate is doing now
 *   php artisan phone:verify-status --user=42           one account, and why
 *   php artisan phone:verify-status --user=42 --verify  let that person through
 *   php artisan phone:verify-status --user=42 --reset   put them back behind it
 *   php artisan phone:verify-status --verify-all        lift it for everybody
 *
 * Turning the gate off entirely is a config change, not a command:
 * PHONE_VERIFICATION_ENABLED=false, or config/phone_verification.php. That leaves
 * every row untouched, so it can be switched back on later without redoing the work.
 */
class PhoneVerificationStatus extends Command
{
    protected $signature = 'phone:verify-status
        {--user= : One user id — show that account, and act on it with --verify / --reset}
        {--verify : Mark the account verified without sending a code (needs --user, or --verify-all)}
        {--reset : Clear the verification and any pending code (needs --user)}
        {--verify-all : Mark EVERY account verified, lifting the gate for the whole staff list}
        {--limit=15 : How many held accounts to list}
        {--force : Do not ask before --verify-all}';

    protected $description = 'Inspect and override the phone-number (OTP) verification gate.';

    public function handle(PhoneOtpService $otp, BulkSmsNgService $gateway): int
    {
        $this->line('');
        $this->info('Phone verification gate — status');
        $this->line(str_repeat('=', 60));

        // ── Switches ─────────────────────────────────────────────────────────
        $configured = (bool) config('phone_verification.enabled', true);
        $ready = $otp->columnsReady();

        $this->line('');
        $this->comment('Switches');
        $this->status('phone_verification.enabled', $configured);
        $this->status('columns deployed (users.is_otp_verified)', $ready);
        $this->line('  code expires after .... ' . $otp->ttlMinutes() . ' minutes');
        $this->line('  resend cooldown ....... ' . $otp->cooldownSeconds() . ' seconds');
        $this->line('  wrong codes allowed ... ' . $otp->maxAttempts());

        if (!$ready) {
            $this->line('');
            $this->warn('  The columns are not on this database, so the gate is holding NOBODY.');
            $this->line('  Run database/sql/2026_09_06_add_phone_otp_verification_to_users.sql');
            $this->line('  against SQL Server (then the .mysql.sql ledger file), or `php artisan migrate`.');

            return self::SUCCESS;
        }

        if (!$configured) {
            $this->line('');
            $this->warn('  The gate is switched OFF: everybody goes straight through.');
            $this->line('  Nothing has been un-verified — turning it back on resumes where it left off.');
        }

        // ── One account ──────────────────────────────────────────────────────
        if ($this->option('user')) {
            return $this->handleOneUser($otp);
        }

        if ($this->option('verify-all')) {
            return $this->handleVerifyAll();
        }

        if ($this->option('verify') || $this->option('reset')) {
            $this->error('--verify and --reset act on one account: add --user=<id> (or use --verify-all).');

            return self::FAILURE;
        }

        // ── The whole staff list ─────────────────────────────────────────────
        $this->reportPopulation($otp);
        $this->reportWallet($gateway);

        return self::SUCCESS;
    }

    /**
     * Show — and optionally change — a single account.
     */
    private function handleOneUser(PhoneOtpService $otp): int
    {
        $user = User::find((int) $this->option('user'));

        if (!$user) {
            $this->error('No user with id ' . $this->option('user') . '.');

            return self::FAILURE;
        }

        if ($this->option('verify') && $this->option('reset')) {
            $this->error('--verify and --reset ask for opposite things. Pick one.');

            return self::FAILURE;
        }

        if ($this->option('verify')) {
            $otp->markVerified($user);
            $this->line('');
            $this->info('  ' . $user->name . ' (id ' . $user->id . ') is now verified — no code was sent.');
        }

        if ($this->option('reset')) {
            $otp->reset($user);
            $this->line('');
            $this->info('  ' . $user->name . ' (id ' . $user->id . ') is back behind the gate.');
        }

        $user->refresh();
        $stored = (string) ($user->phone_number ?? '');
        $usable = BulkSmsNgService::normalizeNumber($stored);

        $this->line('');
        $this->comment('Account');
        $this->line('  id .................... ' . $user->id);
        $this->line('  name .................. ' . $user->name);
        $this->line('  active ................ ' . (((int) ($user->is_active ?? 0) === 1) ? 'yes' : 'no'));
        $this->line('  phone on file ......... ' . ($stored === '' ? '(none)' : $stored));
        $this->line('  usable as .............. ' . ($usable ?? 'NOT a usable Nigerian mobile'));
        $this->status('is_otp_verified', (bool) $user->is_otp_verified);
        $this->line('  code on file .......... ' . ($user->otp ? $user->otp . ($otp->hasExpired($user) ? '  (EXPIRED)' : '  (valid)') : '(none)'));
        $this->line('  code sent at .......... ' . ($user->otp_sent_at ? $user->otp_sent_at->copy()->setTimezone(config('phone_verification.timezone', 'Africa/Lagos'))->format('d/m/Y H:i') . ' (office clock)' : '(never)'));

        $this->line('');
        $this->comment('What this account meets at sign-in');

        if ($user->needs_profile_photo) {
            $this->line('  The passport-photo card — that gate comes first, so the phone card is not shown yet.');
        } elseif (!$otp->gateEnabled()) {
            $this->line('  Nothing: the gate is off or not deployed.');
        } elseif ($user->is_otp_verified) {
            $this->line('  Nothing: this number has been verified.');
        } elseif ($usable === null) {
            $this->line('  The confirmation card, with no number it can send to — they must type a real one in.');
        } else {
            $this->line('  The confirmation card, offering to send a code to ' . $usable . '.');
        }

        return self::SUCCESS;
    }

    /**
     * Lift the gate for everybody at once.
     */
    private function handleVerifyAll(): int
    {
        $held = User::query()->where('is_otp_verified', false)->count();

        $this->line('');
        $this->warn('  This marks ' . number_format($held) . ' accounts verified WITHOUT any code being sent.');
        $this->line('  Their numbers stay exactly as they are — including the wrong ones. This is the');
        $this->line('  emergency lever, not a rollout step; to pause the gate instead, set');
        $this->line('  PHONE_VERIFICATION_ENABLED=false, which can be undone without losing anything.');

        if (!$this->option('force') && !$this->confirm('Verify all ' . number_format($held) . ' accounts?', false)) {
            $this->line('  Nothing changed.');

            return self::SUCCESS;
        }

        $changed = User::query()
            ->where('is_otp_verified', false)
            ->update(['is_otp_verified' => true, 'otp' => null]);

        $this->info('  ' . number_format($changed) . ' accounts marked verified.');

        return self::SUCCESS;
    }

    /**
     * Who the gate is holding, and how many of them it can actually reach.
     */
    private function reportPopulation(PhoneOtpService $otp): void
    {
        $this->line('');
        $this->comment('Who is held');

        $total = User::query()->count();
        $active = User::query()->where('is_active', 1)->count();
        $verified = User::query()->where('is_otp_verified', true)->count();

        $this->line('  users ................. ' . number_format($total) . ' (' . number_format($active) . ' active)');
        $this->line('  verified .............. ' . number_format($verified));
        $this->line('  still held ............ ' . number_format($total - $verified));

        // How many of the held accounts could be sent a code right now, without the
        // user having to type a new number in. This is the number that decides
        // whether enabling the gate is a morning of support calls or a fortnight.
        $reachable = 0;
        $unreachable = [];

        User::query()
            ->where('is_active', 1)
            ->where('is_otp_verified', false)
            ->select(['id', 'first_name', 'last_name', 'phone_number'])
            ->chunkById(500, function ($chunk) use (&$reachable, &$unreachable) {
                foreach ($chunk as $user) {
                    if (BulkSmsNgService::normalizeNumber((string) ($user->phone_number ?? '')) !== null) {
                        $reachable++;
                    } elseif (count($unreachable) < 5000) {
                        $unreachable[] = $user;
                    }
                }
            });

        $this->line('  held, reachable now ... ' . number_format($reachable) . '  (a code can be sent to the number on file)');
        $this->line('  held, unreachable ..... ' . number_format(count($unreachable)) . '  (must type a real number into the card first)');

        $limit = max(0, (int) $this->option('limit'));

        if ($limit > 0 && $unreachable) {
            $this->line('');
            $this->line('  First ' . min($limit, count($unreachable)) . ' with no usable number:');
            foreach (array_slice($unreachable, 0, $limit) as $user) {
                $this->line(sprintf(
                    '    %-6s %-32s %s',
                    $user->id,
                    mb_substr($user->name, 0, 32),
                    ($user->phone_number ?: '(none)')
                ));
            }
        }
    }

    /**
     * The wallet, because an empty one is the single most likely reason a code
     * never arrives — and it fails silently from the user's side.
     */
    private function reportWallet(BulkSmsNgService $gateway): void
    {
        $this->line('');
        $this->comment('Gateway');

        $email = config('services.bulk_sms_ng.email');

        if (!$email) {
            $this->line('  BULK_SMS_NG_EMAIL / PASSWORD are not set on this server, so no code can be');
            $this->line('  sent at all. .env does not travel with a code upload — that is the usual cause.');

            return;
        }

        $balance = $gateway->balance();

        $this->line('  account ............... ' . $email);
        $this->line('  sender ID ............. ' . config('services.bulk_sms_ng.sender', 'SPAS'));
        $this->line('  balance ............... ' . ($balance === null ? 'could not be read' : '₦' . $balance));
        $this->line('  route ................. promotional (transactional is not enabled on this account)');
        $this->line('  NOTE: promotional traffic is held by the telcos between 19:45 and 08:00, and');
        $this->line('        never reaches a DND-blocked handset. Both look like "no code arrived".');
    }

    private function status(string $label, $value): void
    {
        $on = (bool) $value;
        $this->line(sprintf('  %s %s %s', str_pad($label, 40, '.'), $on ? '[ on ]' : '[ off ]', ''));
    }
}
