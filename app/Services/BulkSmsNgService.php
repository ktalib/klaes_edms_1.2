<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Bulk-SMS.ng gateway (account.bulk-sms.ng), the SPAS notice provider.
 *
 * NOT the same vendor as App\Services\BulkSmsNigeriaService, which talks to
 * bulksmsnigeria.com with a bearer token. This one authenticates with the
 * account's own email and password in the request body.
 *
 * WHY SPAS USES THIS AND NOT BETASMS
 * BetaSMS serves its API on plain HTTP port 80 only, and the production server
 * cannot make outbound :80 connections — every notice failed there with a
 * connection timeout while sending fine on a developer machine. This API is
 * https, so it reaches the internet by the port production actually has open.
 * See App\Console\Commands\SpaSmsDoctor for the diagnosis.
 *
 * ONLY THE PROMOTIONAL ROUTE IS AVAILABLE
 * The transactional route (/api/transactional/v2/send) answers 608 "User not
 * Unauthorised for this route" on this account — it is not enabled. Probed
 * 2026-08-18. If the Ministry later enables it, switch the path: transactional
 * traffic reaches DND-blocked handsets, which promotional traffic does not, and
 * a statutory contravention notice is exactly the kind of message that should
 * be going out on that route.
 */
class BulkSmsNgService
{
    private string $base = 'https://account.bulk-sms.ng/api';

    protected ?string $lastCode   = null;
    protected ?string $lastReason = null;

    /**
     * Documented error codes, plus the ones found by probing the live account.
     * 600 is the success code the balance endpoint returns; 608 is undocumented
     * and means the route is not enabled for the account.
     */
    private const STATUS_TEXT = [
        '600' => 'success',
        '601' => 'authentication failed — check BULK_SMS_NG_EMAIL / BULK_SMS_NG_PASSWORD',
        '602' => 'invalid request (a required field is missing or malformed)',
        '604' => 'insufficient balance on the Bulk-SMS.ng account',
        '606' => 'gateway internal error, try again later',
        '607' => 'more than 100 recipients in one request',
        '608' => 'this account is not authorised for that route',
    ];

    /** Plain-language reason the last send() failed, or null if it succeeded. */
    public function lastFailureReason(): ?string
    {
        return $this->lastReason;
    }

    /** Status code from the last call, or null if the request never completed. */
    public function lastStatusCode(): ?string
    {
        return $this->lastCode;
    }

    /**
     * The only status worth rewording a message for.
     *
     * Unlike BetaSMS — whose 1713 is an outright content filter — this gateway
     * documents no such rejection. 602 ("invalid request") is the one code that
     * could plausibly be about the payload rather than the account, so it is
     * the only one a second wording is tried on. Everything else (bad
     * credentials, no balance, route not enabled) is an account problem that
     * rephrasing cannot fix.
     */
    public const CODE_REWORDABLE = '602';

    /**
     * Send the first wording the gateway accepts, and report which one won.
     *
     * Mirrors BetaSmsService::sendFirstAccepted so callers can be switched
     * between the two gateways without changing shape. A rejected message was
     * delivered to nobody, so falling through cannot double-send.
     *
     * @param  array<int,string|null>  $messages  Best wording first.
     * @return string|null  The message that was accepted, or null if none was.
     */
    public function sendFirstAccepted(string $phone, array $messages): ?string
    {
        foreach (array_values(array_filter($messages)) as $i => $message) {
            if ($this->send($phone, $message)) {
                return $message;
            }

            if ($this->lastCode !== self::CODE_REWORDABLE) {
                return null;
            }

            Log::warning('BulkSmsNgService: wording rejected, trying the next one', [
                'variant'   => $i,
                'remaining' => count($messages) - $i - 1,
                'reason'    => $this->lastReason,
            ]);
        }

        return null;
    }

    /**
     * Send an SMS to a single Nigerian number.
     *
     * Messages are NOT truncated: the statutory notice texts run past one page
     * on purpose and cutting them would change their legal meaning. Longer
     * texts simply bill as multiple pages.
     */
    public function send(string $phone, string $message): bool
    {
        $this->lastCode   = null;
        $this->lastReason = null;

        $email    = config('services.bulk_sms_ng.email');
        $password = config('services.bulk_sms_ng.password');
        $sender   = config('services.bulk_sms_ng.sender', 'SPAS');

        if (!$email || !$password) {
            // .env is gitignored, so it is NOT copied by a code upload — this is
            // the first thing to fail on a freshly deployed server.
            $this->lastReason = 'BULK_SMS_NG_EMAIL / BULK_SMS_NG_PASSWORD are not set in this environment';
            Log::warning('BulkSmsNgService: credentials not configured.');
            return false;
        }

        $mobile = $this->normalizePhone($phone);
        if (!$mobile) {
            $this->lastReason = 'the phone number "'.$phone.'" is not a usable Nigerian mobile number';
            Log::warning('BulkSmsNgService: invalid phone number', ['phone' => $phone]);
            return false;
        }

        [$body, $http, $error] = $this->post('/promotional/send', [
            'email'      => $email,
            'password'   => $password,
            'message'    => $message,
            'recipient'  => $mobile,
            'senderid'   => substr($sender, 0, 11),
            'smsgateway' => (string) config('services.bulk_sms_ng.gateway', '1'),
        ]);

        if ($error) {
            $this->lastReason = 'could not reach account.bulk-sms.ng ('.$error.')';
            Log::error('BulkSmsNgService: request failed', ['phone' => $mobile, 'error' => $error]);
            return false;
        }

        $ok = $this->interpret($body, $http);

        Log::log($ok ? 'info' : 'warning', 'BulkSmsNgService: send result', [
            'phone'     => $mobile,
            'sender'    => $sender,
            'http_code' => $http,
            'response'  => $body === '' ? '<empty>' : mb_substr($body, 0, 200),
            'code'      => $this->lastCode,
            'reason'    => $this->lastReason,
            'success'   => $ok,
            'pages'     => (int) ceil(mb_strlen($message) / 160),
        ]);

        return $ok;
    }

    /** Account balance in naira, or null if it could not be read. */
    public function balance(): ?string
    {
        [$body, , $error] = $this->post('/promotional/balance', [
            'email'    => config('services.bulk_sms_ng.email'),
            'password' => config('services.bulk_sms_ng.password'),
        ]);

        if ($error) {
            return null;
        }

        return json_decode($body, true)['balance'] ?? null;
    }

    /**
     * Did that response mean the message was accepted?
     *
     * The vendor documents a JSON body with status/statusCode/messageid. A
     * SUCCESSFUL promotional send does not return it: the endpoint answers
     * HTTP 200 with an EMPTY body. Verified 2026-08-18 by reading the wallet
     * either side of a send — the balance dropped by one page's cost each
     * time, so an empty body is an accepted message, not a silent failure.
     * Failures do return the documented JSON (608 was observed that way).
     *
     * So: JSON is trusted when present, and an empty 2xx counts as accepted.
     * Anything else — an HTML error page, a 5xx — is treated as a failure
     * rather than assumed good, because guessing wrong here marks a statutory
     * notice as served when nobody was told.
     */
    private function interpret(string $body, int $http): bool
    {
        $decoded = json_decode($body, true);

        if (is_array($decoded)) {
            $this->lastCode = isset($decoded['statusCode']) ? (string) $decoded['statusCode'] : null;
            $status         = strtolower((string) ($decoded['status'] ?? ''));

            if ($status === 'success') {
                return true;
            }

            $this->lastReason = self::STATUS_TEXT[$this->lastCode]
                ?? ($decoded['message'] ?? 'unrecognised gateway response');

            return false;
        }

        if ($http >= 200 && $http < 300 && trim($body) === '') {
            return true;
        }

        $this->lastReason = $http >= 200 && $http < 300
            ? 'gateway replied with something other than its documented response'
            : 'gateway returned HTTP '.$http;

        return false;
    }

    /**
     * @return array{0:string, 1:int, 2:string} body, HTTP status, curl error
     */
    private function post(string $path, array $payload): array
    {
        $ch = curl_init($this->base.$path);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 40,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $body  = curl_exec($ch);
        $error = curl_error($ch);
        $http  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [trim((string) $body), $http, $error];
    }

    /**
     * Normalise to the 234XXXXXXXXXX form the gateway expects.
     */
    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '234') && strlen($digits) === 13) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '234'.substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '234'.$digits;
        }

        return null;
    }
}
