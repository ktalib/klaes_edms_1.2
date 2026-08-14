<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'ai_folder_search' => [
        'url' => env('AI_FOLDER_SEARCH_URL', 'http://10.50.1.2:7000/'),
    ],

    'google_maps' => [
        // Used by the File Indexing "Apply & Pin on Map" geocoder. Override in
        // .env with GOOGLE_MAPS_API_KEY once billing/API-restrictions are set up.
        'key' => env('GOOGLE_MAPS_API_KEY', 'AIzaSyCFb7XF_3_LCPlK-O5Yp4IZEO1w0ccgQJM'),

        // Server-side key for the Geocoding HTTP API (fileindexing:backfill-coordinates).
        // Must NOT have an HTTP-referrer restriction — Google rejects referrer-restricted
        // keys on server-to-server API calls. Restrict by IP or API instead.
        'geocoding_key' => env('GOOGLE_GEOCODING_API_KEY'),
    ],

    'paystack' => [
        'secret'   => env('PAYSTACK_SECRET_KEY'),
        'public'   => env('PAYSTACK_PUBLIC_KEY'),
        'base_url' => 'https://api.paystack.co',
    ],

    'ebulksms' => [
        'username' => env('EBULKSMS_USERNAME'),
        'apikey' => env('EBULKSMS_APIKEY'),
        'sender' => env('EBULKSMS_SENDER', 'Klaesedms'),
    ],

    // Gateway used for Special Assignment contravention notices (first / second serve)
    'betasms' => [
        'username' => env('BETASMS_USERNAME'),
        'password' => env('BETASMS_PASSWORD'),
        'sender'   => env('BETASMS_SENDER', 'KLASE'),
    ],

    'bulksmsnigeria' => [
        'api_token' => env('BULKSMSNG_API_TOKEN', '488|sKKj8eoZkgxFWLvdaBobT2aISYSZT055IcFJjZA994a7f693'),
        'sender' => env('BULKSMSNG_SENDER', 'KANGIS'),
        // When true, posts to /api/sandbox/v2/sms (no real SMS, no wallet
        // deduction). Useful for staging or before production access is
        // activated. Flip BULKSMSNG_SANDBOX=false in .env to send live SMS.
        'sandbox' => env('BULKSMSNG_SANDBOX', false),
    ],

];
