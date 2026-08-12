<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'print-manager/*',
        'api/file-trackers/*',
        'api/mobile/*',

        // Diagnostics beacon for the Add/Edit Buyers screen. A stale token is one
        // of the failures it exists to report, and it is sent by sendBeacon on
        // unload — where a 419 would silently discard the last thing the page had
        // to say. Writes nothing but bounded strings to a log file.
        'buyer/client-log',
    ];
}
