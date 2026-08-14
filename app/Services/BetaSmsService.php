<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * BetaSMS gateway (login.betasms.com).
 *
 * The vendor ships a PHP proxy script that forwards a form POST to their API;
 * this is that same call made directly, so no extra hop is involved.
 *
 * Messages are NOT truncated at 160 characters: the statutory notice texts run
 * past one page on purpose, and cutting them mid-sentence would change their
 * legal meaning. Longer texts simply bill as multiple pages.
 */
class BetaSmsService
{
    private string $endpoint = 'http://login.betasms.com/api/';

    /**
     * Send an SMS to a single Nigerian phone number.
     *
     * @param  string  $phone    Raw number (0xx, +234xx, or 234xx)
     * @param  string  $message  Text to send; sent whole, across pages if needed
     * @return bool              True only when the gateway accepts the message
     */
    public function send(string $phone, string $message): bool
    {
        $username = config('services.betasms.username');
        $password = config('services.betasms.password');
        $sender   = config('services.betasms.sender', 'KLASE');

        if (!$username || !$password) {
            Log::warning('BetaSmsService: username or password not configured.');
            return false;
        }

        $mobile = $this->normalizePhone($phone);
        if (!$mobile) {
            Log::warning('BetaSmsService: invalid phone number', ['phone' => $phone]);
            return false;
        }

        $postData = http_build_query([
            'username' => $username,
            'password' => $password,
            'message'  => $message,
            'mobiles'  => $mobile,
            'sender'   => substr($sender, 0, 11),
        ]);

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError || $response === false) {
            Log::error('BetaSmsService: request failed', ['phone' => $mobile, 'error' => $curlError]);
            return false;
        }

        $ok = $this->isSuccess($response);

        Log::info('BetaSmsService: send result', [
            'phone'     => $mobile,
            'sender'    => $sender,
            'http_code' => $httpCode,
            'response'  => trim((string) $response),
            'success'   => $ok,
            'pages'     => (int) ceil(mb_strlen($message) / 160),
        ]);

        return $ok;
    }

    /**
     * BetaSMS answers with a numeric status code, optionally followed by
     * pipe-separated detail ("1701|2348012345678|1"). 1701 is the only
     * accepted-for-delivery code; everything else (1702 bad URL, 1703 bad
     * credentials, 1707/1025 no credit, …) is a failure worth logging.
     */
    private function isSuccess(string $response): bool
    {
        $body = trim($response);

        if ($body === '') {
            return false;
        }

        $code = trim(explode('|', $body)[0]);

        return $code === '1701' || strcasecmp($body, 'OK') === 0;
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

        // 0803… → 234803…
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '234' . substr($digits, 1);
        }

        // 803… (leading zero already stripped somewhere upstream)
        if (strlen($digits) === 10) {
            return '234' . $digits;
        }

        return null;
    }
}
