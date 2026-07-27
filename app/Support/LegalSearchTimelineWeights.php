<?php

namespace App\Support;

/**
 * The single source of truth for Legal Search timeline weights.
 *
 * Mirrors TIMELINE_WEIGHTS in resources/views/legal_search/KLAES Legal Search Timeline.md.
 * Both consumers read these numbers rather than carrying their own copies:
 *   - the print slip, via LegalSearchService::buildPrintReport()
 *   - the on-screen timeline, via `@json(LegalSearchTimelineWeights::MAP)` in
 *     resources/views/legal_search/js.blade.php
 *
 * Weights drive the PRIMARY sort (descending), with event timestamp ascending as the
 * secondary key. A null weight marks a "floating" event: it carries no rank of its own
 * and is injected chronologically without disturbing the weighted hierarchy around it.
 */
class LegalSearchTimelineWeights
{
    public const FILE_COMMISSIONING       = 'FILE_COMMISSIONING';
    public const TEMP_FILE_COMMISSIONING  = 'TEMP_FILE_COMMISSIONING';
    public const OCCUPANCY_PERMIT         = 'OCCUPANCY_PERMIT';
    public const TRANSFER_OF_TITLE_OP     = 'TRANSFER_OF_TITLE_OP';
    public const RIGHT_OF_OCCUPANCY       = 'RIGHT_OF_OCCUPANCY';
    public const KANGIS_RECERTIFICATION   = 'KANGIS_RECERTIFICATION';
    public const CERTIFICATE_OF_OCCUPANCY = 'CERTIFICATE_OF_OCCUPANCY';
    public const OTHER_INSTRUMENTS        = 'OTHER_INSTRUMENTS';
    public const PARCEL_UPDATE            = 'PARCEL_UPDATE';
    public const TITLE_STATUS_UPDATE      = 'TITLE_STATUS_UPDATE';
    public const FILE_DECOMMISSIONING     = 'FILE_DECOMMISSIONING';
    public const DCIV_COMMISSIONING       = 'DCIV_COMMISSIONING';

    /** @var array<string, int|null> */
    public const MAP = [
        self::OCCUPANCY_PERMIT         => 14,
        self::TRANSFER_OF_TITLE_OP     => 13,
        self::FILE_COMMISSIONING       => 12,
        self::TEMP_FILE_COMMISSIONING  => 12,
        self::RIGHT_OF_OCCUPANCY       => 9,
        // 8 keeps a recertification above the C of O it produced. placeKangisRecertBeforeCofo()
        // still enforces adjacency when both share a timestamp.
        self::KANGIS_RECERTIFICATION   => 8,
        self::CERTIFICATE_OF_OCCUPANCY => 1,
        self::OTHER_INSTRUMENTS        => 1,
        self::PARCEL_UPDATE            => null,
        self::TITLE_STATUS_UPDATE      => null,
        self::FILE_DECOMMISSIONING     => null,
        self::DCIV_COMMISSIONING       => null,
    ];

    /**
     * Transaction types that create or retire files. These anchor where lineage
     * commissioning rows sit, and are floating events in their own right.
     */
    public const PARCEL_UPDATE_PATTERN = '/subdivision|merger|change of purpose|plot extension|separation|parcel update/';

    /**
     * Classify a timeline row into one of the event keys above.
     *
     * $canonicalType must already have been through the caller's canonicaliser
     * (LegalSearchService's $canonicalTransactionType, or canonicalWeightingInstrumentType
     * in JS) — this class does not repeat that matching.
     */
    public static function classify(array $row, string $canonicalType): string
    {
        $source = trim((string) ($row['source_table'] ?? ''));

        // Sources that only ever carry one synthetic event.
        switch ($source) {
            case 'File Commissioning':
                return self::FILE_COMMISSIONING;
            case 'Temporary File':
                return self::TEMP_FILE_COMMISSIONING;
            case 'File Decommissioning':
                return self::FILE_DECOMMISSIONING;
            case 'DCIV File Commissioning':
                return self::DCIV_COMMISSIONING;
        }

        // 'Related Fileno' is deliberately NOT in that list: it is a source, not an event,
        // and its rows carry real types — a Merger or Subdivision links files just as a
        // recertification does (see LegalSearchService::fetchDecommissionLineageRows).
        // Classifying on the source would rank a Subdivision as a recertification (8) and
        // lift it out of the floating events. Type wins; the source is only the fallback.
        if ($canonicalType === 'occupancy permit') {
            return self::OCCUPANCY_PERMIT;
        }
        if ($canonicalType === 'transfer of title') {
            return self::TRANSFER_OF_TITLE_OP;
        }
        if ($canonicalType === 'right of occupancy') {
            return self::RIGHT_OF_OCCUPANCY;
        }
        if (str_contains($canonicalType, 'recertification')) {
            return self::KANGIS_RECERTIFICATION;
        }
        if (str_contains($canonicalType, 'certificate of occupanc')) {
            return self::CERTIFICATE_OF_OCCUPANCY;
        }
        if (preg_match(self::PARCEL_UPDATE_PATTERN, $canonicalType)) {
            return self::PARCEL_UPDATE;
        }

        // An untyped 'Related Fileno' row is the synthetic KANGIS recertification marker
        // emitted by LegalSearchService::fetchRelatedRecertificationRows().
        if ($source === 'Related Fileno') {
            return self::KANGIS_RECERTIFICATION;
        }

        return self::OTHER_INSTRUMENTS;
    }

    /**
     * The sort weight for a row, or null when the event floats (see class docblock).
     */
    public static function weightFor(array $row, string $canonicalType): ?int
    {
        $key = self::classify($row, $canonicalType);

        // array_key_exists, not ??: a floating event's weight IS null, and ?? would read
        // that as "absent" and hand back the OTHER_INSTRUMENTS default of 0 — which would
        // rank parcel updates and decommissionings instead of letting them float.
        return array_key_exists($key, self::MAP)
            ? self::MAP[$key]
            : self::MAP[self::OTHER_INSTRUMENTS];
    }
}
