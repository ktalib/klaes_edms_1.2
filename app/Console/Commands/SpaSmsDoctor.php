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

        // DNS first, separately from the connection: "site can't be reached" in a
        // browser covers both a name that will not resolve and a port that is
        // firewalled, and the fixes are completely different (hosts entry vs a
        // firewall rule vs a different port).
        $ip = gethostbyname('login.betasms.com');
        $dnsOk = $ip !== 'login.betasms.com' && filter_var($ip, FILTER_VALIDATE_IP);
        $this->line('   DNS  : '.($dnsOk
            ? '<fg=green>resolves to '.$ip.'</>'
            : '<fg=red>does NOT resolve on this server</>'));

        // Then a ladder of routes. Each is a real workaround, so whichever one
        // answers is the value to put in this server's .env — no guessing.
        $configured = config('services.betasms.endpoint');
        $proxy      = config('services.betasms.proxy');

        $routes = [
            'http  :80 (default)' => ['url' => 'http://login.betasms.com/api/', 'proxy' => null, 'ip' => null],
            'https :443'          => ['url' => 'https://login.betasms.com/api/', 'proxy' => null, 'ip' => null],
        ];
        if ($dnsOk) {
            // Same URL, DNS bypassed — separates "name will not resolve" from
            // "network will not carry the packets".
            $routes['http  :80 IP-pinned'] = ['url' => 'http://login.betasms.com/api/', 'proxy' => null, 'ip' => $ip];
        }
        if ($proxy) {
            $routes['via BETASMS_PROXY'] = ['url' => $configured, 'proxy' => $proxy, 'ip' => null];
        }

        $working = null;
        foreach ($routes as $label => $route) {
            [$usable, $detail] = $this->probe($route);
            $this->line(sprintf('   %-20s %s', $label, $usable
                ? '<fg=green>WORKS</> — gateway answered '.$detail
                : '<fg=red>no</> — '.$detail));

            if ($usable && !$working) {
                $working = $route;
            }
        }

        $curlErr = '';
        if (!$working) {
            $curlErr = 'no route reachable';
            $this->newLine();
            $this->error('   → This server cannot reach the SMS gateway by any route.');
            $this->line('     Nothing in the app can fix that. Either ask the host to allow outbound');
            $this->line('     traffic to login.betasms.com, set BETASMS_PROXY to a proxy this box can');
            $this->line('     use, or send from a machine that has internet access.');
            if (!$dnsOk) {
                $this->line('     DNS also fails here — a hosts-file entry for login.betasms.com may be');
                $this->line('     enough if the block turns out to be name resolution only.');
            }
            $ok = false;
        } elseif ($working['url'] !== $configured
               || ($working['proxy'] ?? null) !== $proxy
               || ($working['ip'] ?? null) !== config('services.betasms.ip')) {
            $this->newLine();
            $this->line('   <fg=yellow>→ The configured route is not the one that works. Put this in .env:</>');
            $this->line('     BETASMS_ENDPOINT='.$working['url']);
            if ($working['proxy'] ?? null) {
                $this->line('     BETASMS_PROXY='.$working['proxy']);
            }
            if ($working['ip'] ?? null) {
                $this->line('     BETASMS_IP='.$working['ip']);
            }
            $this->line('     then run: php artisan config:clear');
            $ok = false;
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

    /**
     * Is that route good enough to actually send on?
     *
     * This posts to the API rather than merely opening a socket, because
     * "reachable" and "usable" are not the same thing here: https://…/api/
     * accepts the connection and then answers 301 back to http://…, so a box
     * with only :443 open would look fine on a connectivity test and still fail
     * every send. A route counts as usable only when the gateway itself replies
     * with one of its numeric codes.
     *
     * Nothing is sent: the mobile number is deliberately invalid, which the
     * gateway rejects (1703) before any message goes out. Redirects are not
     * followed, for the reason above.
     *
     * @param  array{url:string, proxy:?string, ip:?string}  $route
     * @return array{0:bool, 1:string}
     */
    private function probe(array $route): array
    {
        $ch = curl_init($route['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'username' => config('services.betasms.username'),
                'password' => config('services.betasms.password'),
                'sender'   => config('services.betasms.sender') ?: 'KLASE',
                'message'  => 'diagnostic',
                'mobiles'  => '12',   // invalid on purpose: nothing can be delivered
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            // Diagnostic only, and the gateway's :443 serves a self-signed
            // certificate. Verifying here would mask the more useful finding —
            // that the https route answers a 301 back to plain http. Real sends
            // (BetaSmsService) leave verification on.
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        if ($route['proxy'] ?? null) {
            curl_setopt($ch, CURLOPT_PROXY, $route['proxy']);
        }

        if ($route['ip'] ?? null) {
            $parts = parse_url($route['url']);
            $host  = $parts['host'] ?? 'login.betasms.com';
            $port  = $parts['port'] ?? (($parts['scheme'] ?? 'http') === 'https' ? 443 : 80);
            curl_setopt($ch, CURLOPT_RESOLVE, [$host.':'.$port.':'.$route['ip']]);
        }

        $body = trim((string) curl_exec($ch));
        $err  = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            return [false, $err];
        }

        $code = trim(explode('|', $body)[0]);
        if (preg_match('/^\d{4}$/', $code)) {
            return [true, $code];
        }

        if ($http >= 300 && $http < 400) {
            return [false, 'HTTP '.$http.' redirect to plain http — cannot carry a send on its own'];
        }

        return [false, 'HTTP '.$http.', gateway did not answer with a status code'];
    }

    private function rawPost(array $fields): string
    {
        $ch = curl_init(config('services.betasms.endpoint') ?: 'http://login.betasms.com/api/');
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
