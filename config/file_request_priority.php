<?php

/**
 * Requester seniority hierarchy for File Search Requests.
 *
 * When multiple people request the same file, the front desk honors the most
 * senior requester at the log step (/create-file-tracker). Priority is derived
 * from the Receiving Officer chosen on a Digital File Request (the senior person
 * the file is being requested FOR) — matched case-insensitively (substring) against
 * the keys below. Higher weight = honored first.
 *
 * Keys may be a designation/rank (matched against the officer's name or users.rank)
 * — refine these to your Management hierarchy. 'default' applies when nothing matches
 * (e.g. front-desk / Quick Search requests with no receiving officer).
 */
return [
    'ranks' => [
        'Commissioner' => 100,
        'Permanent Secretary' => 90,
        'Director'            => 70,
        'Deputy Director'     => 60,
        'Assistant Director'  => 50,
    ],

    'default' => 0,
];
