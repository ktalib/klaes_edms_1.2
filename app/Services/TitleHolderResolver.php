<?php

namespace App\Services;

use App\Models\FileIndexing;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the THREE distinct ownership concepts a file carries, per the client
 * spec of 2026-08-20 (item 12, "Major update on Transaction History"):
 *
 *   Root of Title   — where the title comes from. For SLTR/Conversion that is the
 *                     dealing that happened BEFORE the Ministry ever issued a
 *                     title; for a Direct Allocation it is the OP / Allocation
 *                     List the plot was allocated under.
 *   Original Holder — the person named on the FIRST Ministry grant (RofO, else
 *                     CofO). NOT simply the earliest party on record.
 *   Current Holder  — the grantee of the latest ownership-CHANGING dealing after
 *                     that grant; equal to the Original Holder when none exists.
 *
 * The rule this replaces — "the earliest party in the transaction history is the
 * Original Holder" — is wrong whenever a sale predates the Ministry title, which
 * is the normal case on SLTR and Conversion files.
 *
 * Chain data comes from TimelineWeightingService (the same deduped, weighted set
 * that already feeds Quick Search, the mobile File Search and the indexing
 * timeline), so this service adds interpretation only — never a second query
 * layer that could disagree with what the timeline shows.
 *
 * Legal Search is deliberately NOT a consumer: it keeps its own
 * LegalSearchService::annotateRootOfTitle() remark. The two should agree; they
 * are not wired together.
 */
class TitleHolderResolver
{
    public const TYPE_SLTR              = 'SLTR';
    public const TYPE_CONVERSION        = 'Conversion';
    public const TYPE_DIRECT_ALLOCATION = 'Direct Allocation';
    public const TYPE_UNKNOWN           = 'Unknown';

    /**
     * Dealings that MOVE the title to somebody else. Only these can promote a
     * party to Current Holder. Everything outside this list (mortgage, surrender
     * & release, caveat, recertification, change of purpose, a re-issued CofO...)
     * leaves ownership where it was — a mortgaged file is still the mortgagor's.
     */
    private const OWNERSHIP_CHANGING = [
        'deed of assignment',
        'transfer of title',
        'deed of sub-assignment',
        'deed of conveyance',
        'deed of gift',
        'deed of transfer',
        'vesting deed',
        'deed of vesting',
        'sale agreement',
        'deed of sale',
    ];

    /** Free-text fragments that also mean "ownership moved" on legacy rows. */
    private const OWNERSHIP_CHANGING_FRAGMENTS = [
        'assignment', 'transfer of title', 'conveyance', 'gift', 'vesting', 'sale',
    ];

    /** Never ownership-changing, whatever else the label happens to contain. */
    private const NEVER_OWNERSHIP_CHANGING = [
        'mortgage', 'surrender', 'release', 'caveat', 'search', 'recertification',
        'change of purpose', 'sub-lease', 'sublease', 'power of attorney', 'lease',
    ];

    public function __construct(private TimelineWeightingService $timeline)
    {
    }

    /**
     * The three holders for a file.
     *
     * @return array{
     *   application_type:string,
     *   root_of_title:?array{name:?string,instrument:?string,date:?string,source:?string},
     *   original_holder:?array{name:?string,instrument:?string,date:?string,source:?string},
     *   current_holder:?array{name:?string,instrument:?string,date:?string,source:?string}
     * }
     */
    public function resolve(?string $fileNumber, ?string $propId = null, ?FileIndexing $indexing = null): array
    {
        $fileNumber = trim((string) $fileNumber);
        if ($fileNumber === '' && ! $propId) {
            return $this->emptyResult(self::TYPE_UNKNOWN);
        }

        $indexing ??= $this->findIndexing($fileNumber);
        $source     = $this->commissioningSource($fileNumber);
        $chain      = $this->chain($fileNumber ?: null, $propId);
        $type       = $this->classify($fileNumber, $source, $indexing, $chain);

        // The first Ministry grant splits the chain: everything before it is Root
        // of Title territory, everything after it can move the Current Holder.
        $anchorIndex = $this->findGrantIndex($chain);
        $anchor      = $anchorIndex === null ? null : $chain[$anchorIndex];

        $root = $type === self::TYPE_DIRECT_ALLOCATION
            ? $this->directAllocationRoot($chain, $source, $anchor)
            : $this->preGrantRoot($chain, $anchorIndex);

        $original = $this->originalHolder($anchor, $type, $root, $indexing);
        $current  = $this->currentHolder($chain, $anchorIndex, $original, $indexing);

        return [
            'application_type' => $type,
            'root_of_title'    => $root,
            'original_holder'  => $original,
            'current_holder'   => $current,
        ];
    }

    /**
     * Flat, display-ready strings ("NAME (Instrument)") for the blade/JS layers
     * that only want three lines. Missing holders come back as null so a caller
     * can hide the row rather than print an empty label.
     *
     * @return array{application_type:string,root_of_title:?string,original_holder:?string,current_holder:?string}
     */
    public function resolveForDisplay(?string $fileNumber, ?string $propId = null, ?FileIndexing $indexing = null): array
    {
        $r = $this->resolve($fileNumber, $propId, $indexing);

        return [
            'application_type' => $r['application_type'],
            'root_of_title'    => self::label($r['root_of_title']),
            'original_holder'  => self::label($r['original_holder']),
            'current_holder'   => self::label($r['current_holder']),
        ];
    }

    /** "MUSA IBRAHIM (Sale Agreement)" — or just the name when no instrument is known. */
    public static function label(?array $holder): ?string
    {
        $name = trim((string) ($holder['name'] ?? ''));
        if ($name === '') {
            return null;
        }
        $instrument = trim((string) ($holder['instrument'] ?? ''));

        return $instrument === '' ? $name : "{$name} ({$instrument})";
    }

    // ---------------------------------------------------------------- chain --

    /** The deduped, weighted, chronologically ordered dealings on this file. */
    private function chain(?string $fileNumber, ?string $propId): array
    {
        $records = $this->timeline->getRawRecords($fileNumber, $propId);
        if (empty($records)) {
            return [];
        }

        $weighted = $this->timeline->getWeightedRecords($records);
        if (empty($weighted)) {
            return [];
        }

        // Resolve each row's date ONCE and carry it on the row: every rule below
        // compares dates rather than positions, because a row the digitisers left
        // undated must never be read as "this happened first".
        foreach ($weighted as &$row) {
            $row['_holder_date'] = $this->sortDate($row);
        }
        unset($row);

        // Strictly chronological — the holder chain is a sequence in TIME, unlike
        // holderHistory() which sorts by source weight first for display purposes.
        // An undated row sorts last but keeps its relative order (stable by id).
        usort($weighted, function ($a, $b) {
            $da = $a['_holder_date'] ?? '9999-12-31';
            $db = $b['_holder_date'] ?? '9999-12-31';
            if ($da !== $db) {
                return $da <=> $db;
            }
            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return array_values($weighted);
    }

    /**
     * A row's transaction date as Y-m-d, or null when it has none we can trust.
     *
     * Sentinel dates are treated as NO date. The staging tables are full of
     * "1900-01-01" / "1899-12-30" placeholders written where a date was unknown,
     * and taking them literally puts a later assignment BEFORE the CofO — which
     * would promote its assignee to Root of Title and freeze the Current Holder
     * at the original grantee. (Seen on CON-RES-1987-672.)
     */
    private function sortDate(array $row): ?string
    {
        $raw = $row['transaction_date'] ?? null;
        if (empty($raw)) {
            return null;
        }

        $parsed = rescue(fn () => \Carbon\Carbon::parse($raw)->toDateString(), null, false);

        return ($parsed === null || $parsed <= '1900-01-01') ? null : $parsed;
    }

    // ------------------------------------------------------------- classify --

    /**
     * SLTR / Conversion / Direct Allocation. The three routes carry different
     * Root of Title rules, so misclassifying a file silently moves a name to the
     * wrong line — hence the explicit, ordered tests below.
     */
    private function classify(string $fileNumber, ?string $source, ?FileIndexing $indexing, array $chain): string
    {
        $src = strtolower(trim((string) $source));
        $fno = strtoupper(trim($fileNumber));

        // A Conversion file carries the CON- prefix in its own number; mls_file_no
        // only knows the 4.5% of files commissioned through KLAES.
        if (str_starts_with($fno, 'CON-') || str_contains($src, 'conversion')) {
            return self::TYPE_CONVERSION;
        }

        // SLTR is a registry, not a number format — same test the mobile File
        // Search uses to badge a file's registry.
        $registry = strtoupper(trim((string) ($indexing->general_registry ?? '')))
            . ' ' . strtoupper(trim((string) ($indexing->registry ?? '')));
        if (str_contains($registry, 'SLTR')) {
            return self::TYPE_SLTR;
        }

        if (str_contains($src, 'allocation') || str_contains($src, 'resettlement')) {
            return self::TYPE_DIRECT_ALLOCATION;
        }

        // No recorded source (the legacy majority): an Occupancy Permit on the
        // file is itself the evidence that the plot came through a direct
        // allocation.
        foreach ($chain as $row) {
            if ($this->canonical($row) === 'occupancy permit') {
                return self::TYPE_DIRECT_ALLOCATION;
            }
        }

        return self::TYPE_UNKNOWN;
    }

    // ------------------------------------------------------------- the grant --

    /** RofO and CofO both grant title; nothing else the Ministry issues does. */
    private function isMinistryGrant(array $row): bool
    {
        return in_array(
            $this->canonical($row),
            ['right of occupancy', 'certificate of occupancy'],
            true
        );
    }

    /**
     * Index of the EARLIEST Ministry grant in the chain — the line that defines
     * the Original Holder.
     *
     * It is the earliest grant of either kind, not "the first RofO, else a CofO":
     * plenty of files carry a CofO dated years before the RofO row that was
     * captured for them, and anchoring on the later RofO left that earlier CofO
     * sitting in the pre-grant window, where it was read as the Root of Title.
     * (Seen on RES-1982-144, RES-RC-1981-234, RES-1981-684.) On the same date a
     * RofO wins, since the certificate follows the right.
     */
    private function findGrantIndex(array $chain): ?int
    {
        $best = null;

        foreach ($chain as $i => $row) {
            if (! $this->isMinistryGrant($row)) {
                continue;
            }
            if ($best === null) {
                $best = $i;
                continue;
            }

            $bestDate = $chain[$best]['_holder_date'] ?? null;
            $rowDate  = $row['_holder_date'] ?? null;

            // An undated grant never displaces a dated one.
            if ($rowDate === null) {
                continue;
            }
            if ($bestDate === null || $rowDate < $bestDate) {
                $best = $i;
                continue;
            }
            if ($rowDate === $bestDate
                && $this->canonical($row) === 'right of occupancy'
                && $this->canonical($chain[$best]) !== 'right of occupancy') {
                $best = $i;
            }
        }

        return $best;
    }

    // ------------------------------------------------------- root of title --

    /**
     * SLTR / Conversion: the earliest real dealing that predates the Ministry
     * grant. Returns null when nothing predates it — a blank Root of Title is
     * correct, and far better than falling back to the earliest party, which is
     * exactly the assumption the spec removes.
     */
    private function preGrantRoot(array $chain, ?int $anchorIndex): ?array
    {
        if ($anchorIndex === null) {
            return null;
        }

        // "Before the Ministry title" is only answerable when the grant itself is
        // dated. An undated grant sorts to the END of the chain, so falling back
        // to position would read every later dealing as pre-grant and hand the
        // Root of Title to the wrong party. Blank is the honest answer.
        $anchorDate = $chain[$anchorIndex]['_holder_date'] ?? null;
        if ($anchorDate === null) {
            return null;
        }

        foreach ($chain as $i => $row) {
            if ($i === $anchorIndex) {
                continue;
            }
            $rowDate = $row['_holder_date'] ?? null;
            if ($rowDate === null || $rowDate >= $anchorDate) {
                continue;
            }

            $canonical = $this->canonical($row);

            // A second Ministry grant is a re-issue of the title, never the root
            // it springs from — skip it even though it predates the anchor.
            if ($this->isMinistryGrant($row)) {
                continue;
            }

            // A pre-grant OP is the allocation itself, not a private dealing, but
            // it is still the root the title springs from.
            if ($canonical !== 'occupancy permit' && $this->isNeverOwnershipChanging($row)) {
                continue;
            }
            if ($this->rawType($row) === '') {
                continue;
            }

            $name = $this->receivingParty($row);
            if ($name === null) {
                continue;
            }

            return $this->holder($name, $this->instrumentLabel($row), $row);
        }

        return null;
    }

    /**
     * Direct Allocation: the root is the allocation instrument — the OP if one is
     * on the chain, otherwise the Allocation List recorded as the commissioning
     * source. The allottee's name goes on the line; the instrument sits in
     * brackets ("MUSA ABDULLAHI (OP)").
     */
    private function directAllocationRoot(array $chain, ?string $source, ?array $anchor): ?array
    {
        foreach ($chain as $row) {
            if ($this->canonical($row) !== 'occupancy permit') {
                continue;
            }
            $name = $this->receivingParty($row);
            if ($name !== null) {
                return $this->holder($name, 'OP', $row);
            }
        }

        $src = strtolower(trim((string) $source));
        $isOpSource = str_contains($src, 'op direct allocation') || str_contains($src, 'resettlement');
        $instrument = $isOpSource ? 'OP' : 'Allocation List';

        // No OP row on record: the allottee is whoever the Ministry granted to,
        // because on a direct allocation the allottee and the first grantee are
        // the same person.
        $name = $anchor ? $this->receivingParty($anchor) : null;
        if ($name === null) {
            return null;
        }

        return $this->holder($name, $instrument, $anchor);
    }

    // ------------------------------------------------------------- holders --

    /**
     * The name on the first Ministry grant. When the file has no grant at all,
     * fall back to the Root of Title holder (a direct allocation whose OP is the
     * only record), then to the indexed original_holder — never to the earliest
     * party on the chain.
     */
    private function originalHolder(?array $anchor, string $type, ?array $root, ?FileIndexing $indexing): ?array
    {
        if ($anchor) {
            $name = $this->receivingParty($anchor);
            if ($name !== null) {
                return $this->holder($name, $this->instrumentLabel($anchor), $anchor);
            }
        }

        if ($type === self::TYPE_DIRECT_ALLOCATION && $root) {
            // On a direct allocation the allottee IS the original holder; the
            // instrument is dropped here so the two lines aren't identical.
            return $this->holder($root['name'], null, null, $root['date'] ?? null);
        }

        $indexed = $indexing?->formattedHolder('original_holder');

        return $indexed ? $this->holder($indexed, null, null) : null;
    }

    /**
     * The grantee of the latest ownership-changing dealing AFTER the grant. With
     * no such dealing the Original Holder is still the Current Holder.
     */
    private function currentHolder(array $chain, ?int $anchorIndex, ?array $original, ?FileIndexing $indexing): ?array
    {
        $anchorDate = $anchorIndex === null ? null : ($chain[$anchorIndex]['_holder_date'] ?? null);

        // Split the eligible dealings in two. A DATED dealing after the grant is
        // the strong answer; an undated one is a maybe. Undated rows sort last in
        // the chain, so without this split a row with no date at all would
        // outrank a genuinely later, dated transfer.
        $dated = [];
        $undated = [];
        foreach ($chain as $i => $row) {
            if ($i === $anchorIndex || ! $this->isOwnershipChanging($row)) {
                continue;
            }
            $rowDate = $row['_holder_date'] ?? null;
            if ($rowDate === null) {
                $undated[] = $row;
                continue;
            }
            // Only dealings from the grant onwards can move the Current Holder —
            // anything earlier is Root of Title territory. With no dated grant on
            // record every dealing is eligible.
            if ($anchorDate === null || $rowDate >= $anchorDate) {
                $dated[] = $row;
            }
        }

        foreach ([array_reverse($dated), array_reverse($undated)] as $candidates) {
            foreach ($candidates as $row) {
                $name = $this->receivingParty($row);
                if ($name === null) {
                    continue;
                }
                return $this->holder($name, $this->instrumentLabel($row), $row);
            }
        }

        if ($original) {
            return $original;
        }

        $indexed = $indexing?->formattedHolder('current_holder')
            ?? $indexing?->formattedHolder('original_holder');

        return $indexed ? $this->holder($indexed, null, null) : null;
    }

    // -------------------------------------------------------------- helpers --

    /**
     * Who the dealing moved the property TO. party_2 (grantee/assignee) is the
     * receiving side; a one-sided legacy row records only party_1, and on those
     * the single name IS the holder being recorded.
     */
    private function receivingParty(array $row): ?string
    {
        $party2 = trim((string) ($row['party_2'] ?? ''));
        if ($party2 !== '' && ! $this->isGovernmentParty($party2)) {
            return $party2;
        }

        $party1 = trim((string) ($row['party_1'] ?? ''));
        if ($party1 !== '' && ! $this->isGovernmentParty($party1)) {
            return $party1;
        }

        // Both sides are the State (a grant from government to government, or a
        // mis-captured row) — prefer whatever is there over nothing.
        return ($party2 ?: $party1) ?: null;
    }

    /**
     * The State grants land; it never "holds" it in the sense this display means.
     * On a RofO/CofO row the Grantor is the Kano State Government, so it must not
     * be picked up as a holder.
     */
    private function isGovernmentParty(string $name): bool
    {
        $n = strtolower($name);

        return str_contains($n, 'state government')
            || str_contains($n, 'governor')
            || str_contains($n, 'ministry of land');
    }

    /** Display label for a row's instrument ("RofO", "CofO", "Deed Of Assignment"). */
    private function instrumentLabel(array $row): ?string
    {
        $canonical = $this->canonical($row);

        return match ($canonical) {
            'right of occupancy'       => 'RofO',
            'certificate of occupancy' => 'CofO',
            'occupancy permit'         => 'OP',
            ''                         => ($this->rawType($row) ?: null),
            default                    => ucwords($canonical),
        };
    }

    private function rawType(array $row): string
    {
        $type = trim((string) ($row['transaction_type'] ?? ($row['instrument_type'] ?? '')));

        return ($type === '-' ) ? '' : $type;
    }

    private function canonical(array $row): string
    {
        return $this->timeline->canonicalInstrumentType($this->rawType($row));
    }

    private function isOwnershipChanging(array $row): bool
    {
        if ($this->isNeverOwnershipChanging($row)) {
            return false;
        }

        $canonical = $this->canonical($row);
        if (in_array($canonical, self::OWNERSHIP_CHANGING, true)) {
            return true;
        }

        $raw = strtolower($this->rawType($row));
        foreach (self::OWNERSHIP_CHANGING_FRAGMENTS as $fragment) {
            if (str_contains($raw, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function isNeverOwnershipChanging(array $row): bool
    {
        $haystack = strtolower($this->rawType($row) . ' ' . $this->canonical($row));
        foreach (self::NEVER_OWNERSHIP_CHANGING as $word) {
            if (str_contains($haystack, $word)) {
                return true;
            }
        }
        return false;
    }

    private function holder(?string $name, ?string $instrument, ?array $row = null, ?string $date = null): ?array
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return [
            'name'       => $name,
            'instrument' => $instrument ?: null,
            'date'       => $date ?? ($row ? $this->displayDate($row['transaction_date'] ?? null) : null),
            'source'     => $row['source_table'] ?? null,
        ];
    }

    private function displayDate($raw): ?string
    {
        if (empty($raw)) {
            return null;
        }
        return rescue(fn () => \Carbon\Carbon::parse($raw)->format('M j, Y'), is_string($raw) ? $raw : null, false);
    }

    /** How the file was commissioned, when KLAES commissioned it (mls_file_no). */
    private function commissioningSource(string $fileNumber): ?string
    {
        if ($fileNumber === '') {
            return null;
        }

        $base = preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $fileNumber);
        $candidates = array_values(array_unique(array_filter([$fileNumber, $base, $base . '(T)'])));

        $source = DB::connection('sqlsrv')->table('mls_file_no')
            ->whereIn('full_file_number', $candidates)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')
            ->value('source');

        return trim((string) $source) ?: null;
    }

    private function findIndexing(string $fileNumber): ?FileIndexing
    {
        if ($fileNumber === '') {
            return null;
        }

        return FileIndexing::whereRaw('UPPER(file_number) = ?', [strtoupper($fileNumber)])->first();
    }

    private function emptyResult(string $type): array
    {
        return [
            'application_type' => $type,
            'root_of_title'    => null,
            'original_holder'  => null,
            'current_holder'   => null,
        ];
    }
}
