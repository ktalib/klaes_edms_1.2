<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * "The OP names one holder, File Indexing names another, and nothing on the file
 * explains how the title moved."
 *
 * A file whose root of title is an Occupancy Permit carries the OP's grantee in
 * pra.party_2. When that file is later transferred, a Transfer of Title row records
 * the move and File Indexing's file_title follows the new holder. On some files the
 * indexing was updated but the transfer was never captured, so the register says the
 * title is with somebody the chain never gave it to.
 *
 * This service answers two questions for one file number:
 *
 *   check()       — is this file in that state, and what does its chain look like?
 *   generateTot() — write the missing transfer (OP holder -> indexed holder).
 *
 * WHAT DOES *NOT* QUALIFY (2026-08-29 decision, from the data): a file whose OP
 * grantee differs from the file title but which ALREADY carries a working transfer.
 * On 9 of the 11 files that first looked wrong, the transfer was there and only the
 * spelling of its party_2 differed from the file title (OZATAMGBO/OZOTAMGBO,
 * MOHD/MUHD, ...). The dealing is recorded on those files; generating another would
 * put a second transfer on a file that already has one. Those are name corrections,
 * handled by a human, and this service deliberately leaves them alone.
 *
 * A "working" transfer is one whose two parties are different names. A self-transfer
 * (IDRIS SANI -> IDRIS SANI, which RES-2024-2236 carries) moves nothing and does not
 * count as the bridge.
 *
 * The rule is the one in database/sql/2026_08_29_op_holder_vs_indexing_check.sql;
 * that query and this service must keep answering the same way.
 */
class OpHolderMatchService
{
    /** transaction_type / instrument_type written on the row this service creates. */
    public const TOT_TYPE = 'Transfer of Title (OP)';

    /**
     * Dropped before two names are compared for being the same person. MOHD and MUHD
     * are in here because they are used interchangeably as a whole given name in this
     * register, not because they are titles.
     */
    private const HONORIFICS = [
        'MR', 'MRS', 'MISS', 'MS', 'ALH', 'ALHAJI', 'ALHAJA', 'HAJIYA', 'HAJIA',
        'MAL', 'MALAM', 'MALLAM', 'DR', 'ENGR', 'ARC', 'BARR', 'PROF', 'CHIEF',
        'SIR', 'LATE', 'HON', 'HRH', 'MOHD', 'MUHD',
    ];

    /** pra.source / system_source stamped on it, so the row is traceable to this flow. */
    private const SOURCE = 'OP Holder Match';
    private const SYSTEM_SOURCE = 'OPHOLDERMATCH';

    /**
     * Columns never carried over from the source OP: the OP's own identity, its
     * registration particulars, its batch/merger membership, and the caveat state,
     * which belongs to the OP row and not to a transfer derived from it.
     */
    private const DO_NOT_COPY = [
        'id', 'resolved_fileno', 'updated_at', 'updated_by', 'deleted_at',
        'instrument_capture_id', 'op_batch', 'merger_group_id', 'is_merger_op',
        'is_subdivided', 'op_count', 'source_pra_id', 'source_op_table', 'source_op_id',
        'is_caveated', 'caveated_comment', 'caveat_id',
    ];

    public function __construct(private TitleHolderResolver $titleHolders)
    {
    }

    /**
     * The file's state for the recommendation form.
     *
     * `applies` is the only field the caller has to act on: true means show the
     * Match option. Everything else is there so the officer can see WHY before
     * they press it — the chain, the two names, and the reason when it does not
     * apply.
     *
     * @return array{
     *   applies:bool, reason:string, file_number:string, indexing_name:?string,
     *   op:?array, timeline:array, root_of_title:?string, has_working_transfer:bool,
     *   name_spelling_only:bool
     * }
     */
    public function check(?string $fileNumber): array
    {
        $fileNumber = trim((string) $fileNumber);

        $base = [
            'applies'              => false,
            'reason'               => '',
            'file_number'          => $fileNumber,
            'indexing_name'        => null,
            'op'                   => null,
            'timeline'             => [],
            'root_of_title'        => null,
            'has_working_transfer' => false,
            'name_spelling_only'   => false,
        ];

        if ($fileNumber === '') {
            return $base + ['reason' => 'No file number given.'];
        }

        $indexing = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('file_number', $fileNumber)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->first(['id', 'file_number', 'file_title', 'prop_id']);

        if (! $indexing) {
            $base['reason'] = 'This file has no File Indexing record.';
            return $base;
        }

        $indexedName = trim((string) $indexing->file_title);
        $base['indexing_name'] = $indexedName;

        $rows = $this->praRows($fileNumber);
        $base['timeline'] = $this->timeline($rows);

        if ($indexedName === '') {
            $base['reason'] = 'File Indexing holds no file title for this file, so there is nothing to compare the OP against.';
            return $base;
        }

        $ops = array_values(array_filter($rows, fn ($r) => $this->isOp($r)));

        if (! $ops) {
            $base['reason'] = 'The root of title is not an Occupancy Permit.';
            return $base;
        }

        // Read the root of title through the shared resolver purely for display —
        // the officer sees the same wording here as on every other screen.
        $base['root_of_title'] = $this->rootOfTitleLabel($fileNumber, $indexing->prop_id ?? null);

        // An OP that already names the indexed holder means the file never moved.
        foreach ($ops as $op) {
            if ($this->sameName($op->party_2, $indexedName)) {
                $base['reason'] = 'The Occupancy Permit already names the indexed holder.';
                return $base;
            }
        }

        // The OP names the same person, spelt differently ("ALH KABIRU USMAN KULO"
        // against "KABIRU USMAN KULO", "MARYSAM MUSA" against "MARYAM MUSA").
        //
        // Nothing moved here, so there is no transfer to record: Match would write a
        // row transferring the title from a man to himself. 36 files in the estate
        // are in this state, and every one of them needs its name corrected on one
        // side or the other — which is a person's judgement about which spelling is
        // right, not something this flow can decide.
        //
        // Deliberately generous (any close match withholds the button): an officer
        // sent to check a name they did not need to check has lost a minute, while a
        // self-transfer written into the register is a false dealing on somebody's
        // title.
        foreach ($ops as $op) {
            if ($this->looksLikeSamePerson($op->party_2, $indexedName)) {
                $base['name_spelling_only'] = true;
                $base['reason'] = 'The Occupancy Permit names ' . trim((string) $op->party_2)
                    . ' and File Indexing holds ' . $indexedName
                    . '. These look like the same person spelt two ways, so no transfer is recorded — '
                    . 'correct the spelling on whichever side is wrong.';
                return $base;
            }
        }

        $transfers = array_values(array_filter($rows, fn ($r) => $this->isTransfer($r)));

        foreach ($transfers as $t) {
            if ($this->sameName($t->party_2, $indexedName)) {
                $base['reason'] = 'A transfer on this file already names the indexed holder.';
                return $base;
            }
        }

        // The spelling-drift case: a transfer that moved the title is on file, so the
        // dealing is recorded even though its party_2 is not spelt the way File
        // Indexing spells it. Nothing to generate — that is a name correction.
        foreach ($transfers as $t) {
            if (! $this->sameName($t->party_1, $t->party_2)) {
                $base['has_working_transfer'] = true;
                $base['reason'] = 'A transfer of title is already recorded on this file ('
                    . trim((string) $t->party_1) . ' to ' . trim((string) $t->party_2)
                    . '). If that name is wrong, it is a correction on the existing record — not a new transfer.';
                return $base;
            }
        }

        // Earliest OP is the grant the chain starts from.
        $op = $ops[0];

        $base['applies'] = true;
        $base['reason']  = 'The Occupancy Permit was granted to ' . trim((string) $op->party_2)
            . ', File Indexing holds ' . $indexedName
            . ', and no transfer on this file explains the change.';
        $base['op'] = [
            'pra_id'           => (int) $op->id,
            'holder'           => trim((string) $op->party_2),
            'grantor'          => trim((string) $op->party_1),
            'transaction_type' => (string) ($op->transaction_type ?: $op->instrument_type),
            'date'             => $this->displayDate($op),
            'prop_id'          => $op->prop_id,
        ];

        return $base;
    }

    /**
     * Write the missing Transfer of Title: OP holder -> indexed holder.
     *
     * Re-running check() first is not optional — this is the same guard the button
     * was drawn from, applied at the moment of writing, so a file that was matched
     * in another tab a second earlier cannot be matched twice.
     *
     * @return array{ok:bool, message:string, pra_id:?int}
     */
    public function generateTot(?string $fileNumber, ?int $userId = null): array
    {
        $state = $this->check($fileNumber);

        if (! $state['applies']) {
            return ['ok' => false, 'message' => $state['reason'] ?: 'This file does not need a transfer.', 'pra_id' => null];
        }

        $op = DB::connection('sqlsrv')->table('pra')->where('id', $state['op']['pra_id'])->first();

        if (! $op) {
            return ['ok' => false, 'message' => 'The Occupancy Permit record could not be read back.', 'pra_id' => null];
        }

        $grantor = trim((string) $op->party_2);          // the OP's grantee is the transferor
        $grantee = trim((string) $state['indexing_name']); // File Indexing's holder is the transferee

        if ($grantor === '' || $grantee === '') {
            return ['ok' => false, 'message' => 'Both party names are needed to record a transfer.', 'pra_id' => null];
        }

        $payload = $this->buildTotPayload($op, $grantor, $grantee, $userId);

        $id = (int) DB::connection('sqlsrv')->table('pra')->insertGetId($payload);

        Log::info('op-holder-match.tot-generated', [
            'file_number' => $state['file_number'],
            'op_pra_id'   => (int) $op->id,
            'new_pra_id'  => $id,
            'party_1'     => $grantor,
            'party_2'     => $grantee,
            'user_id'     => $userId,
        ]);

        return [
            'ok'      => true,
            'message' => 'Transfer of Title recorded: ' . $grantor . ' to ' . $grantee . '.',
            'pra_id'  => $id,
        ];
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * The transfer row. Modelled on GenerateStagedTots::buildTotPayload so a
     * transfer reconstructed here is indistinguishable from one reconstructed by
     * the backfill command: copied from the OP, re-typed, re-partied, with the
     * registration particulars zeroed.
     *
     * prop_id comes across untouched: the transfer is a dealing on the same parcel,
     * and prop_id identifies a parcel, not a transaction.
     */
    private function buildTotPayload(object $op, string $grantor, string $grantee, ?int $userId): array
    {
        $payload = (array) $op;

        foreach (self::DO_NOT_COPY as $column) {
            unset($payload[$column]);
        }

        $now = Carbon::now();

        $payload['transaction_type'] = self::TOT_TYPE;
        $payload['instrument_type']  = self::TOT_TYPE;

        $payload['Grantor'] = $grantor;
        $payload['party_1'] = $grantor;
        $payload['Grantee'] = $grantee;
        $payload['party_2'] = $grantee;

        // A reconstructed bridging transfer was never presented to the registry, so
        // it carries no registration particulars — the 0/0/0 convention the OSS
        // Change-of-Name flow and tot:generate-from-staging both use.
        $payload['regNo']     = '0/0/0';
        $payload['serialNo']  = '0';
        $payload['pageNo']    = '0';
        $payload['volumeNo']  = '0';

        $payload['source_op_table'] = 'pra';
        $payload['source_op_id']    = (int) $op->id;

        $payload['is_deleted']    = 0;
        $payload['source']        = self::SOURCE;
        $payload['system_source'] = self::SYSTEM_SOURCE;
        $payload['created_at']    = $now->toDateTimeString();
        $payload['created_by']    = $userId !== null ? (string) $userId : null;

        $payload['remarks'] = trim(
            trim((string) ($op->remarks ?? ''))
            . ' Transfer of Title reconstructed from the OP holder / File Indexing mismatch on '
            . $now->toDateString() . ' (Recommendation capture, Match).'
        );

        return $payload;
    }

    /** Every live pra row for the file, oldest first. */
    private function praRows(string $fileNumber): array
    {
        return DB::connection('sqlsrv')
            ->table('pra')
            ->where(function ($q) use ($fileNumber) {
                $q->where('mlsFNo', $fileNumber)
                  ->orWhere('fileno', $fileNumber)
                  ->orWhere('kangisFileNo', $fileNumber)
                  ->orWhere('NewKANGISFileno', $fileNumber);
            })
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->orderBy('id')
            ->get([
                'id', 'party_1', 'party_2', 'transaction_type', 'instrument_type',
                'transaction_date', 'created_at', 'prop_id', 'regNo', 'source',
            ])
            ->all();
    }

    /** The chain, shaped for the card in the form. */
    private function timeline(array $rows): array
    {
        return array_map(function ($r) {
            return [
                'pra_id'  => (int) $r->id,
                'type'    => (string) ($r->transaction_type ?: $r->instrument_type ?: 'Transaction'),
                'party_1' => trim((string) $r->party_1),
                'party_2' => trim((string) $r->party_2),
                'date'    => $this->displayDate($r),
                'reg_no'  => trim((string) $r->regNo),
                'is_op'   => $this->isOp($r),
                'is_tot'  => $this->isTransfer($r),
            ];
        }, $rows);
    }

    private function rootOfTitleLabel(string $fileNumber, $propId): ?string
    {
        try {
            $holders = $this->titleHolders->resolveForDisplay($fileNumber, $propId ?: null);
            return $holders['root_of_title'] ?: null;
        } catch (\Throwable $e) {
            // The card is worth showing without this line; it is context, not the rule.
            Log::warning('op-holder-match.root-of-title-failed', [
                'file_number' => $fileNumber,
                'error'       => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function isOp(object $row): bool
    {
        return $this->typeContains($row, 'Occupancy Permit');
    }

    private function isTransfer(object $row): bool
    {
        return $this->typeContains($row, 'Transfer of Title');
    }

    private function typeContains(object $row, string $needle): bool
    {
        return stripos((string) $row->transaction_type, $needle) !== false
            || stripos((string) $row->instrument_type, $needle) !== false;
    }

    /**
     * Case- and whitespace-insensitive only. Deliberately NOT fuzzy: a name that
     * merely SOUNDS like the file title is the spelling-drift case, which must fall
     * through to has_working_transfer rather than be treated as a match.
     */
    private function sameName($a, $b): bool
    {
        return $this->norm($a) !== '' && $this->norm($a) === $this->norm($b);
    }

    private function norm($value): string
    {
        return preg_replace('/\s+/', ' ', strtoupper(trim((string) $value)));
    }

    /**
     * Honorifics and spelling apart, are these two the same person?
     *
     * Titles are stripped (ALH, HAJIYA, DR, ...) and the remaining words sorted, so
     * "AHMAD MUHAMMAD" and "MUHAMMAD AHMAD" collapse together; what is left is
     * compared on edit distance, which catches MARYSAM/MARYAM and IBRAHIM/IBRAJIM.
     *
     * 0.80 was read off the data rather than picked: every pair at or above it in
     * the estate is one person written twice, and the nearest genuinely different
     * pair sits well below.
     *
     * This is the OPPOSITE of sameName(), which is exact on purpose. The two answer
     * different questions — "is this the holder on record" (exact) against "would
     * transferring between these two be a nonsense" (fuzzy).
     */
    private function looksLikeSamePerson($a, $b): bool
    {
        $left  = $this->personKey($a);
        $right = $this->personKey($b);

        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right) {
            return true;
        }

        $longest = max(strlen($left), strlen($right));

        return $longest > 0 && (1 - (levenshtein($left, $right) / $longest)) >= 0.80;
    }

    /** Upper-cased, punctuation-free, honorific-free, word-sorted. */
    private function personKey($value): string
    {
        $clean = preg_replace('/[^A-Z0-9 ]+/', ' ', strtoupper(trim((string) $value)));
        $words = array_values(array_filter(
            preg_split('/\s+/', trim($clean)),
            fn ($w) => $w !== '' && ! in_array($w, self::HONORIFICS, true)
        ));

        sort($words);

        return implode(' ', $words);
    }

    private function displayDate(object $row): ?string
    {
        $raw = $row->transaction_date ?: ($row->created_at ?? null);

        if (! $raw) {
            return null;
        }

        try {
            return Carbon::parse($raw)->format('d M Y');
        } catch (\Throwable $e) {
            return (string) $raw;
        }
    }
}
