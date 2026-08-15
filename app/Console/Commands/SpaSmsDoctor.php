<?php

namespace App\Console\Commands;

use App\Models\SpaNotice;
use App\Services\BetaSmsService;
use Illuminate\Console\Command;

/**
 * Diagnoses why notice SMS works on one machine and not another.
 *
 * Written because the failure is environmental, not logical: the same code
 * sends fine locally and silently fails in production, and the UI could only
 * say "SMS could not be sent". Each check below is a thing that differs
 * between the two boxes, ordered cheapest-to-check first.
 */
class SpaSmsDoctor extends Command
{
    protected $signature = 'spa:sms-doctor {--phone= : Also send a live test SMS to this number}';

    protected $description = 'Check why SPAS notice SMS is or is not sending on this machine';

    public function handle(): int
    {
        $this->info('SPAS SMS diagnostics — '.config('app.env').' @ '.gethostname());
        $this->newLine();

        $ok = true;

        // 1. Credentials. .env is not deployed by a file upload (it is gitignored),
        //    so this is the single most likely difference on a fresh production box.
        $username = config('services.betasms.username');
        $password = config('services.betasms.password');
        $sender   = config('services.betasms.sender');

        $this->line('1. Credentials (config/services.php ← .env)');
        $this->line('   BETASMS_USERNAME : '.($username ? $this->mask($username) : '<fg=red>NOT SET</>'));
        $this->line('   BETASMS_PASSWORD : '.($password ? '<fg=green>set ('.strlen($password).' chars)</>' : '<fg=red>NOT SET</>'));
        $this->line('   BETASMS_SENDER   : '.($sender ?: '<fg=red>NOT SET</>'));

        if (!$username || !$password) {
            $this->newLine();
            $this->error('   → Add BETASMS_USERNAME / BETASMS_PASSWORD / BETASMS_SENDER to this server\'s .env.');
            $this->line('     .env is gitignored, so it is NOT copied by a code upload — it must be edited on the server.');
            $ok = false;
        }

        // 2. Stale config cache: values can be correct in .env yet absent from the
        //    cached config the app actually reads.
        $this->newLine();
        $this->line('2. Config cache');
        $cached = file_exists($this->laravel->getCachedConfigPath());
        if ($cached) {
            $this->line('   <fg=yellow>config is cached</> — if you just edited .env, run: php artisan config:clear');
        } else {
            $this->line('   <fg=green>not cached</> — .env is read live');
        }

        // 3. Outbound HTTP. Shared hosting commonly blocks arbitrary outbound
        //    ports; the API is plain HTTP on port 80.
        $this->newLine();
        $this->line('3. Outbound connection to login.betasms.com');
        $ch = curl_init('http://login.betasms.com/api/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_NOBODY         => true,
        ]);
        curl_exec($ch);
        $curlErr  = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErr) {
            $this->error('   → UNREACHABLE: '.$curlErr);
            $this->line('     The server firewall is blocking outbound HTTP. Ask the host to allow it.');
            $ok = false;
        } else {
            $this->line('   <fg=green>reachable</> (HTTP '.$httpCode.')');
        }

        // 4. Whether the gateway accepts these credentials, without sending anything:
        //    a deliberately invalid number is rejected before any message goes out,
        //    but bad credentials are reported first, which is what we are testing.
        if ($username && $password && !$curlErr) {
            $this->newLine();
            $this->line('4. Gateway credential check (no SMS is sent)');
            $probe = $this->rawPost([
                'username' => $username,
                'password' => $password,
                'sender'   => $sender ?: 'KLASE',
                'message'  => 'diagnostic',
                'mobiles'  => '12',   // invalid on purpose: nothing can be delivered
            ]);
            $this->line('   gateway replied: '.$probe);
            if ($probe === '1703') {
                $this->line('   <fg=green>credentials accepted</> (1703 = the deliberately invalid number)');
            } elseif ($probe === '1702') {
                $this->error('   → 1702 = invalid username or password on THIS server.');
                $ok = false;
            } else {
                $this->line('   <fg=yellow>unexpected reply — see the code map in BetaSmsService</>');
            }
        }

        // 5. The wording itself: the gateway refuses some content outright (1713).
        $this->newLine();
        $this->line('5. Notice wording vs the content filter');
        foreach (['first', 'second'] as $type) {
            $body = SpaNotice::smsBody($type);
            $this->line(sprintf('   %-6s serve: %d chars, %d page(s)%s',
                $type,
                strlen($body),
                (int) ceil(mb_strlen($body) / 160),
                stripos($body, 'notice') !== false ? ' <fg=red>contains "notice" — refused with 1713</>' : ''
            ));
        }

        // 6. Optional live send.
        if ($phone = $this->option('phone')) {
            $this->newLine();
            $this->line('6. Live test SMS to '.$phone);
            $sms  = new BetaSmsService();
            $sent = $sms->send($phone, 'KLAES diagnostic message from '.config('app.env').'. The SPAS message route is working.');
            if ($sent) {
                $this->line('   <fg=green>accepted by the gateway</> (code '.$sms->lastStatusCode().') — check the handset');
            } else {
                $this->error('   → NOT sent: '.$sms->lastFailureReason().' (code '.($sms->lastStatusCode() ?: 'none').')');
                $ok = false;
            }
        } else {
            $this->newLine();
            $this->line('6. Live test skipped — re-run with --phone=08012345678 to send one');
        }

        $this->newLine();
        $this->line($ok ? '<fg=green>No blocking problem found on this machine.</>' : '<fg=red>Problems found — see the arrows above.</>');

        return $ok ? 0 : 1;
    }

    private function rawPost(array $fields): string
    {
        $ch = curl_init('http://login.betasms.com/api/');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 25,
        ]);
        $r = curl_exec($ch);
        curl_close($ch);

        return trim((string) $r);
    }

    /** Show enough of the username to recognise it, not enough to reuse it. */
    private function mask(string $value): string
    {
        if (strlen($value) <= 4) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 3).str_repeat('*', max(3, strlen($value) - 6)).substr($value, -3);
    }
}
