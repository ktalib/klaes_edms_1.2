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
    |--------------------------------------------------------------------------
    | Which way the code travels
    |--------------------------------------------------------------------------
    |
    | Two routes to the same code. EMAIL LEADS, and the reason is the data —
    | counted on 2026-09-07 across 1,562 active accounts:
    |
    |     495  have an email address that is a real mailbox
    |     282  have a mobile number the SMS gateway can reach
    |   1,061  have neither, and must type something real into the card
    |
    | and the SMS route is worse than that near-parity suggests. It is
    | promotional traffic: the telcos hold it between 19:45 and 08:00 (status
    | 609) and never deliver it to a DND-blocked handset, so a code asked for at
    | nine in the evening arrives at half past eight the next morning, and for
    | some staff never at all. Email costs nothing per message and reaches a
    | laptop.
    |
    | 1,555 accounts hold SOMETHING address-shaped; the gap between that and 495
    | is a thousand seeded placeholders (see 'placeholder_email_domains' below).
    | SMS therefore stays on the card underneath, because neither route reaches
    | everybody. The user picks; the default is what the card opens on.
    |
    */

    'default_channel' => env('PHONE_VERIFICATION_DEFAULT_CHANNEL', 'email'),

    /*
     | Either route can be closed off entirely. Turning EMAIL off falls the card
     | back to the SMS-only behaviour it had before; turning SMS off leaves email
     | as the only way through, which is the lever to pull when the SMS wallet
     | empties rather than switching the whole gate off. Closing both is treated
     | as closing the gate — nobody is held by a card with no button.
     */
    'channels' => [
        'email' => env('PHONE_VERIFICATION_EMAIL_ENABLED', true),
        'sms' => env('PHONE_VERIFICATION_SMS_ENABLED', true),
    ],

    /*
     | Addresses the code is REFUSED to. About a thousand accounts were seeded
     | with example.com / example.org / example.net addresses and another sixty
     | with klaes.local — none of which is a mailbox. Sending there "succeeds":
     | the SMTP server accepts it, the card says a code is on its way, and the
     | user waits for something that will never arrive, with no way through.
     | Refusing outright lets the card say the one useful thing instead — type
     | your real address in — which is the whole point of the exercise.
     |
     | Matched on the domain, case-insensitively. A leading dot means "and every
     | subdomain of it".
     */
    'placeholder_email_domains' => [
        'example.com',
        'example.org',
        'example.net',
        'example.edu',
        'test.com',
        'email.com',
        'domain.com',
        'localhost',
        'klaes.local',
        '.local',
        '.test',
        '.invalid',
        '.localdomain',
        '.example',
    ],

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

    /*
    |--------------------------------------------------------------------------
    | The email
    |--------------------------------------------------------------------------
    |
    | Sent through the ordinary Laravel mailer (App\Mail\AccountVerificationCode)
    | on whatever MAIL_* settings the server carries — which is the one thing to
    | check before enabling this route, because .env does not travel with a code
    | upload and an unconfigured mailer throws rather than failing quietly.
    |
    | DELIVERED SYNCHRONOUSLY. QUEUE_CONNECTION is `database` on this deployment
    | and there is no guarantee a worker is running; a sign-in code that sits in
    | a jobs table until somebody notices is the same as a code that never came.
    | See the mailable — it must never implement ShouldQueue.
    |
    */

    'email_subject' => env(
        'PHONE_VERIFICATION_EMAIL_SUBJECT',
        'Your KLAES verification code'
    ),

];
