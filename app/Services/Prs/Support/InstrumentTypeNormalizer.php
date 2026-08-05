<?php

namespace App\Services\Prs\Support;

/**
 * Folds the instrument-type spellings across the three deed tables onto the five
 * groups the PRS report sections need.
 *
 * The "verify variants" warning in docs/prs-2025/11-implementation-plan.md turned
 * out to be understated — every variant is real and in use. Measured 2026-08-02:
 *
 *   pra                    Deed of Surrender and Release 3,300 · Surrender and Release 333 ·
 *                          Deed of Release 311   (three spellings, one PRS section)
 *                          Deed of Assignment 8,638 · Deed of Mortgage 5,817 ·
 *                          Tripartite Mortgage 1,833 · Deed of Devolution 604
 *   file_history_staging   adds upper-case duplicates: ASSIGNMENT 2,658 · MORTGAGE 1,407 ·
 *                          DEED OF SURRENDER AND RELEASE 1,072 · SURRENDER AND RELEASE 467
 *   deed_registrations     uses "Devolution Order" where pra uses "Deed of Devolution"
 *
 * A naive GROUP BY on instrument_type splits every one of these sections in two.
 */
class InstrumentTypeNormalizer
{
    public const ASSIGNMENT = 'assignment';
    public const MORTGAGE   = 'mortgage';
    public const RELEASE    = 'release';
    public const DEVOLUTION = 'devolution';
    public const COFO       = 'cofo';
    public const OP         = 'occupancy_permit';
    public const ROO        = 'right_of_occupancy';

    /** Section key => human label, for section titles. */
    public const LABELS = [
        self::ASSIGNMENT => 'Deed of Assignment',
        self::MORTGAGE   => 'Deed of Mortgage',
        self::RELEASE    => 'Deed of Release',
        self::DEVOLUTION => 'Deed of Devolution',
        self::COFO       => 'Certificate of Occupancy',
        self::OP         => 'Occupancy Permit',
        self::ROO        => 'Right of Occupancy',
    ];

    public function normalize(?string $raw): ?string
    {
        $v = strtoupper(trim((string) $raw));

        if ($v === '') {
            return null;
        }

        $v = trim(preg_replace('/\s+/', ' ', $v));

        // Order matters. "Tripartite Mortgage" must not fall through to assignment;
        // "Deed of Surrender and Release" must not read as a surrender only; and
        // "ST Assignment (Transfer of Title)" is an OP transfer, not an assignment,
        // so the OP test has to run before the assignment fallback.
        return match (true) {
            str_contains($v, 'MORTGAGE')                              => self::MORTGAGE,
            str_contains($v, 'OCCUPANCY PERMIT') || str_contains($v, 'TRANSFER OF TITLE')
                || $v === 'OP'                                        => self::OP,
            str_contains($v, 'RIGHT OF OCCUPANCY')                    => self::ROO,
            str_contains($v, 'RELEASE') || str_contains($v, 'SURRENDER') => self::RELEASE,
            str_contains($v, 'DEVOLUTION') || str_contains($v, 'VESTING ASSENT')
                || str_contains($v, 'LETTERS OF ADMINISTRATION')      => self::DEVOLUTION,
            str_contains($v, 'CERTIFICATE OF OCCUPANCY')
                || $v === 'COFO' || $v === 'C OF O'                    => self::COFO,
            str_contains($v, 'ASSIGNMENT')                            => self::ASSIGNMENT,
            default                                                   => null,
        };
    }

    /**
     * SQL fragment listing every raw spelling that belongs to a group, for pushing
     * the filter into the database rather than pulling 133,939 rows into PHP.
     */
    public function sqlPredicate(string $group, string $column): string
    {
        $like = match ($group) {
            self::MORTGAGE   => ["%MORTGAGE%"],
            self::RELEASE    => ["%RELEASE%", "%SURRENDER%"],
            self::DEVOLUTION => ["%DEVOLUTION%", "%VESTING ASSENT%", "%LETTERS OF ADMINISTRATION%"],
            self::COFO       => ["%CERTIFICATE OF OCCUPANCY%"],
            self::OP         => ["%OCCUPANCY PERMIT%", "%TRANSFER OF TITLE%"],
            self::ROO        => ["%RIGHT OF OCCUPANCY%"],
            self::ASSIGNMENT => ["%ASSIGNMENT%"],
            default          => [],
        };

        if ($like === []) {
            return '1 = 0';
        }

        $clauses = array_map(fn ($p) => "UPPER($column) LIKE '$p'", $like);
        $sql     = '(' . implode(' OR ', $clauses) . ')';

        // Assignment is the fallback in normalize(), so exclude the groups a bare
        // LIKE '%ASSIGNMENT%' would otherwise sweep in — notably
        // "ST Assignment (Transfer of Title)", which is an OP transfer.
        if ($group === self::ASSIGNMENT) {
            $sql .= " AND UPPER($column) NOT LIKE '%MORTGAGE%'"
                  . " AND UPPER($column) NOT LIKE '%TRANSFER OF TITLE%'";
        }

        // "Right of Occupancy" and "Certificate of Occupancy" both contain
        // "OCCUPANCY"; keep the OP filter off both.
        if ($group === self::OP) {
            $sql .= " AND UPPER($column) NOT LIKE '%RIGHT OF OCCUPANCY%'"
                  . " AND UPPER($column) NOT LIKE '%CERTIFICATE OF OCCUPANCY%'";
        }

        return $sql;
    }
}
