<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Phone-number verification (OTP)
    |--------------------------------------------------------------------------
    |
    | Staff sign in as before, and are then held at a card that asks them to
    | confirm the mobile number on their account. A code is sent to it and typed
    | back; users.is_otp_verified goes to 1 and the system unlocks. The gate is
    | the same shape as the mandatory passport photo (RequireProfilePhoto) and
    | runs immediately after it, so a user never meets both cards at once.
    |
    | Nothing here lives only in .env. .env is gitignored and does NOT travel
    | with a code upload, so a key that existed only there would be absent on a
    | freshly deployed server — and the value that decides whether 1,562 people
    | can use the system is not a value to leave to chance.
    |
    */

    /*
     | THE MASTER SWITCH.
     |
     | Turning this off lets everybody straight through and stops any code being
     | sent; nothing is un-verified and no row changes, so it can be switched
     | back on at any time. This is the lever to pull if the SMS wallet empties
     | or the gateway goes down — see `php artisan phone:verify-status`.
     */
    'enabled' => env('PHONE_VERIFICATION_ENABLED', true),

    /*
     | How long a code stays usable, in minutes. Short on purpose: the code is
     | stored in plain text on the user row so ICT can read it back to somebody
     | whose handset is out of coverage, and a long-lived plain code sitting in
     | a column is a standing key to the account.
     */
    'ttl_minutes' => env('PHONE_VERIFICATION_TTL_MINUTES', 15),

    /*
     | How long before the user may ask for another one. Each request costs a
     | page of SMS credit, and the telcos can take a minute or two to deliver —
     | without a cooldown a user who has not received one yet simply presses the
     | button again and spends the wallet.
     */
    'resend_cooldown_seconds' => env('PHONE_VERIFICATION_RESEND_SECONDS', 60),

    /*
     | Digits in the code. Six is what people expect to be reading off a handset.
     */
    'code_length' => env('PHONE_VERIFICATION_CODE_LENGTH', 6),

    /*
     | Wrong codes accepted before the account must request a fresh one. Counted
     | in the cache, so it survives nothing worse than a cache flush; the route
     | throttle is the backstop that does not depend on the cache at all.
     */
    'max_attempts' => env('PHONE_VERIFICATION_MAX_ATTEMPTS', 5),

    /*
     | THE OFFICE CLOCK. config('app.timezone') is UTC on this deployment while
     | Kano is WAT (UTC+1), so anything shown to a user in local terms — "sent
     | at 09:12" — has to be rendered here rather than from now().
     */
    'timezone' => env('PHONE_VERIFICATION_TIMEZONE', 'Africa/Lagos'),

    /*
    |--------------------------------------------------------------------------
    | The message
    |--------------------------------------------------------------------------
    |
    | Sent through Bulk-SMS.ng (App\Services\BulkSmsNgService, sender ID
    | KANOMLPP) — the same gateway as the attendance SMS, and DELIBERATELY NOT
    | through the SMS Control Centre catalogue: every message there ships
    | disabled and can be switched off from a web page. A sign-in code that
    | somebody can turn off by accident locks the whole Ministry out.
    |
    | Two wordings. The gateways here run keyword filters that have refused
    | words outright while answering HTTP 200 — BetaSMS rejects "code" and
    | "approved" — so the fallback avoids the word entirely and is tried when
    | the gateway refuses the first on its one re-wordable status (602).
    | {code} is the only token.
    */
    'message' => env(
        'PHONE_VERIFICATION_MESSAGE',
        'KLAES verification: {code}. Type it into the confirmation card to finish setting up your account. Do not share it with anyone.'
    ),

    'message_fallback' => env(
        'PHONE_VERIFICATION_MESSAGE_FALLBACK',
        'KLAES: {code}'
    ),

];
