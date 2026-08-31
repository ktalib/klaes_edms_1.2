<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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

    public function __construct(
        private TitleHolderResolver $titleHolders,
        private LegalSearchService $legalSearch,
    ) {
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
     *   name_spelling_only:bool, matched:bool
     * }
     */
    public function check(?string $fileNumber): array
    {
        $fileNumber = trim((string) $fileNumber);

        if ($fileNumber === '') {
            return $this->inspect('');
        }

        // The chain comes from LegalSearchService::buildPrintReport(), which merges
        // four tables and takes 3-5 seconds. The form asks this question twice for
        // one capture — once to draw the card, once again as the officer saves — and
        // a second wait of that length on the save button would be paid by every
        // capture in the register, not just the handful this flow is about.
        //
        // Five minutes is long enough to cover one capture and short enough that a
        // file corrected in another screen is re-read while the officer is still
        // working. generateTot() clears the key the moment it writes, so the answer
        // can never outlive the state it describes.
        return Cache::remember(
            $this->cacheKey($fileNumber),
            300,
            fn () => $this->inspect($fileNumber)
        );
    }

    private function cacheKey(string $fileNumber): string
    {
        return 'op-holder-match.' . md5(strtoupper($fileNumber));
    }

    /** The real check. @see check() */
    private function inspect(string $fileNumber): array
    {
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
            'matched'              => false,
            // Set when the OP's pra row carries a DIFFERENT file number in another
            // column, so the officer can see the ambiguity before acting on it.
            'file_conflict'        => null,
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

        $rows = $this->chain($fileNumber, $indexing->prop_id ?? null);
        $rows = $this->markSystemGenerated($rows, $fileNumber);
        $rows = $this->nameCommissioningFromIndexing($rows, $fileNumber, $indexedName);
        $base['timeline'] = $rows;

        if ($indexedName === '') {
            $base['reason'] = 'File Indexing holds no file title for this file, so there is nothing to compare the OP against.';
            return $base;
        }

        // An OP anywhere in this file's payload is this file's OP. The report gathers
        // a parcel, and a row can name a second file number, but a pra row keyed to
        // this file by ANY of its four columns is on this file as far as the register
        // is concerned — hiding the card because the engine preferred the other
        // column takes the decision away from the officer instead of showing it.
        //
        // Where the two numbers disagree the card says so (see $base['file_conflict'])
        // rather than either silently trusting it or silently dropping it.
        $ops = array_values(array_filter($rows, fn ($r) => $r['is_op']));

        if (! $ops) {
            $base['reason'] = 'The root of title is not an Occupancy Permit.';
            return $base;
        }

        // Read the root of title through the shared resolver purely for display —
        // the officer sees the same wording here as on every other screen.
        $base['root_of_title'] = $this->rootOfTitleLabel($fileNumber, $indexing->prop_id ?? null);

        // An OP that already names the indexed holder means the file never moved.
        foreach ($ops as $op) {
            if ($this->sameName($op['party_2'], $indexedName)) {
                $base['matched'] = true;
                $base['reason'] = 'The Occupancy Permit was granted to ' . $indexedName . ' — nothing has moved since.';
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
            if ($this->looksLikeSamePerson($op['party_2'], $indexedName)) {
                $base['name_spelling_only'] = true;
                $base['reason'] = 'The Occupancy Permit names ' . trim((string) $op['party_2'])
                    . ' and File Indexing holds ' . $indexedName
                    . '. These look like the same person spelt two ways, so no transfer is recorded — '
                    . 'correct the spelling on whichever side is wrong.';
                return $base;
            }
        }

        // Any DEALING THAT MOVES OWNERSHIP counts as the explanation, not just a row
        // typed "Transfer of Title". COM-2010-39 reaches BLUEFIELDS OIL & PETROLEUM
        // through two Deeds of Assignment held in file_history_staging; reading only
        // pra's transfers, this service called that file unexplained and offered to
        // write a transfer it did not need. What is and is not ownership-changing is
        // TitleHolderResolver's rule (a mortgage or a caveat is not), asked here
        // rather than restated.
        $dealings = array_values(array_filter($rows, fn ($r) => $this->movesOwnership($r)));

        foreach ($dealings as $d) {
            if ($this->sameName($d['party_2'], $indexedName)) {
                $base['matched'] = true;
                $base['reason'] = 'The ' . $d['type'] . ' from ' . trim((string) $d['party_1'])
                    . ' to ' . trim((string) $d['party_2']) . ' is recorded on this file.';
                return $base;
            }
        }

        // The spelling-drift case: a dealing that moved the title is on file, so the
        // change IS recorded even though its grantee is not spelt the way File
        // Indexing spells it. Nothing to generate — that is a name correction.
        foreach ($dealings as $d) {
            if (! $this->sameName($d['party_1'], $d['party_2'])) {
                $base['has_working_transfer'] = true;
                $base['reason'] = 'A ' . $d['type'] . ' is already recorded on this file ('
                    . trim((string) $d['party_1']) . ' to ' . trim((string) $d['party_2'])
                    . '). If that name is wrong, it is a correction on the existing record — not a new transfer.';
                return $base;
            }
        }

        // Earliest OP is the grant the chain starts from.
        $op = $ops[0];

        // The pra row the transfer will be copied from. The chain above is read
        // through the report engine, which merges four tables and does not carry a
        // pra id, so the source row is looked up separately — and if the OP lives
        // only in one of the other tables there is nothing here to copy, which is
        // said plainly rather than guessed at.
        $source = $this->sourceOpRow($fileNumber, $op['party_2']);

        if (! $source) {
            $base['reason'] = 'The Occupancy Permit for this file is not held in the deeds register (pra), '
                . 'so the transfer cannot be reconstructed from it here.';
            return $base;
        }

        // pra #126911 is mlsFNo COM-2016-219 AND fileno COM-2026-219 — one Occupancy
        // Permit row claiming two separately indexed files. Only 3 rows estate-wide
        // do this, but on those the officer has to decide, so the card tells them.
        $otherNumber = $this->conflictingFileNumber($source, $fileNumber);
        if ($otherNumber !== null) {
            $base['file_conflict'] = $otherNumber;
        }

        $base['applies'] = true;
        $base['reason']  = 'The Occupancy Permit was granted to ' . trim((string) $op['party_2'])
            . ', File Indexing holds ' . $indexedName
            . ', and no dealing on this file explains the change.';
        $base['op'] = [
            'pra_id'           => (int) $source->id,
            'holder'           => trim((string) $op['party_2']),
            'grantor'          => trim((string) $op['party_1']),
            'transaction_type' => $op['type'],
            'date'             => $op['date'],
            'prop_id'          => $source->prop_id,
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

        // The file has just changed in the exact way the cached answer denies, so the
        // key goes before anything can read it again — including this method's own
        // caller, which re-checks to redraw the card.
        Cache::forget($this->cacheKey($state['file_number']));

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

    /**
     * The file's chain, read from the SAME engine every other timeline in KLAES
     * reads: LegalSearchService::buildPrintReport(). The Legal Search timeline, the
     * Property Timeline modal, the PHS portal and the emailed report all render
     * these rows, so this card cannot show a file differently from the rest of the
     * system — and, more importantly, cannot judge one differently.
     *
     * That matters because the register keeps dealings in four tables. Reading only
     * pra, this service missed the two Deeds of Assignment in file_history_staging
     * that carry COM-2010-39 from ALH INUWA WADA to BLUEFIELDS OIL & PETROLEUM, and
     * offered to write a transfer the file already had.
     *
     * Ordering is the engine's, not ours: it is the order every other screen shows,
     * and a card that reordered it would be the odd one out.
     *
     * Falls back to pra alone when the report engine yields nothing for a file it
     * cannot key on, so the card still works rather than silently going blank.
     *
     * @return array<int,array<string,mixed>>
     */
    private function chain(string $fileNumber, $propId): array
    {
        $rows = $this->reportRows($fileNumber, $propId);

        if ($rows === []) {
            $rows = $this->praFallbackRows($fileNumber);
        }

        return $this->promoteOccupancyPermit($rows, $fileNumber);
    }

    /**
     * Flag the timeline rows that THIS flow wrote, so the card can say so.
     *
     * Read off pra.system_source rather than guessed from the party names: a file can
     * carry a transfer between the same two people that an officer captured from a
     * real deed, and calling that "system generated" would misdescribe a document
     * somebody actually holds. Only rows stamped by generateTot() are marked.
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>>
     */
    private function markSystemGenerated(array $rows, string $fileNumber): array
    {
        $generated = DB::connection('sqlsrv')
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
            ->where('system_source', self::SYSTEM_SOURCE)
            ->get(['party_1', 'party_2', 'transaction_type', 'instrument_type']);

        if ($generated->isEmpty()) {
            return $rows;
        }

        // The instrument is part of the key, not just the two names. COM-2013-45
        // carries a Deed of Assignment between the SAME pair as the transfer this
        // flow wrote — a real deed an officer captured — and matching on names alone
        // labelled that deed "system generated", which is exactly the misdescription
        // this method exists to avoid.
        $keys = [];
        foreach ($generated as $row) {
            $type = $this->norm($row->transaction_type ?: $row->instrument_type);
            $keys[$type . '|' . $this->norm($row->party_1) . '|' . $this->norm($row->party_2)] = true;
        }

        foreach ($rows as $i => $row) {
            $key = $this->norm($row['type'] ?? '') . '|'
                . $this->norm($row['party_1'] ?? '') . '|'
                . $this->norm($row['party_2'] ?? '');
            $rows[$i]['system_generated'] = isset($keys[$key]);
        }

        return $rows;
    }

    /**
     * The OTHER file number on the OP's row, when it names one that is not the file
     * being captured — or null when the row is unambiguous.
     *
     * TEMP- numbers are excluded: an unlinked OP capture sits on a system placeholder
     * rather than on a file of its own, so it is not a competing claim.
     */
    private function conflictingFileNumber(object $source, string $fileNumber): ?string
    {
        $wanted = $this->norm($fileNumber);

        foreach ([$source->mlsFNo ?? '', $source->fileno ?? ''] as $candidate) {
            $value = trim((string) $candidate);

            if ($value === '' || $this->norm($value) === $wanted) {
                continue;
            }

            if (stripos($value, 'TEMP-') === 0) {
                continue;
            }

            return $value;
        }

        return null;
    }

    /**
     * On a file that has an Occupancy Permit, the OP leads the chain and IS the root
     * of title.
     *
     * The report engine does not always say so. COM-2016-219 comes back with File
     * Commissioning first, badged "RoT: Allocation List", and the OP third — while
     * TitleHolderResolver, which fills the Root of Title box at the top of this same
     * card, names the OP holder. Two answers to one question on one card is worse
     * than either answer, and on this screen the OP is the answer that matters: it is
     * the grant the missing transfer would be reconstructed from.
     *
     * The engine does not always agree. On COM-2016-219 it returns File Commissioning
     * first, badged "RoT: Allocation List", because the OP row names a second file
     * number (COM-2026-219) in another column and its rule 1 reads that one. On this
     * card the OP leads and holds the badge regardless — an OP is the root of title
     * wherever there is one — and where the row's two file numbers disagree the card
     * says so instead of quietly picking a side.
     *
     * Applied to this card only. The same ordering inside LegalSearchService drives
     * the printed Legal Search report, the PHS portal and the online report, and
     * implements an agreed spec — it is not rewritten from here.
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>>
     */
    private function promoteOccupancyPermit(array $rows, string $fileNumber): array
    {
        $first = null;
        foreach ($rows as $index => $row) {
            if ($row['is_op']) {
                $first = $index;
                break;
            }
        }

        if ($first === null) {
            return $rows;
        }

        $op = $rows[$first];
        unset($rows[$first]);

        // One root of title per chain: the OP takes the badge, and whatever the
        // engine had marked gives it up rather than the card showing two.
        foreach ($rows as $i => $row) {
            $rows[$i]['root_of_title'] = '';
        }

        $op['root_of_title'] = $op['root_of_title'] ?: $op['type'];

        return array_values(array_merge([$op], $rows));
    }

    /**
     * The File Commissioning row carries the name the file is INDEXED under.
     *
     * The report engine reads that row's Party 2 off the instrument that opened the
     * file (LegalSearchService::COMMISSIONING_HOLDER_SOURCES — an OP contributes its
     * grantee) and only falls back to the file title. On a file in exactly the state
     * this card exists for, that prints the OP holder twice: RES-2022-1447 shows the
     * OP granted to Lamash and then "commissioned for" Lamash, while the file itself
     * is indexed to YAHAYA KARAMI SANI. The card then reads as though only File
     * Indexing were out of step, when the point being made is that the file is held
     * in one name and the chain ends in another.
     *
     * So on this card the commissioning row shows the name on the file. The OP row
     * above keeps the OP's grantee, which is what keeps the two names — and the gap
     * between them — visible.
     *
     * Only the searched file's own commissioning rows: an ST, DCIV or successor
     * commissioning row belongs to a different file and keeps whatever the engine
     * gave it. "(T)" is ignored in that comparison so the Temporary File row, which
     * carries the same holder and the same file under its temporary number, is
     * renamed with it.
     *
     * Display only. A commissioning row is neither an OP nor an ownership-changing
     * dealing (TitleHolderResolver decides the latter), so no verdict below is read
     * from the name written here.
     *
     * Applied to this card only — the same row on the printed Legal Search report,
     * the PHS portal and the online report is the engine's to name.
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>>
     */
    private function nameCommissioningFromIndexing(array $rows, string $fileNumber, string $indexedName): array
    {
        if (trim($indexedName) === '') {
            return $rows;
        }

        $key = fn ($v) => strtoupper(preg_replace('/[\s\-_\/]+|\(\s*T\s*\)$/i', '', trim((string) $v)));
        $target = $key($fileNumber);

        foreach ($rows as $i => $row) {
            $source = trim((string) ($row['source'] ?? ''));

            if (! in_array($source, ['File Commissioning', 'Temporary File'], true)) {
                continue;
            }

            // A row with no file number of its own is the searched file's — the
            // engine only ever emits these two for the file being searched.
            $rowFileNo = trim((string) ($row['file_no'] ?? ''));
            if ($rowFileNo !== '' && $target !== '' && $key($rowFileNo) !== $target) {
                continue;
            }

            $rows[$i]['party_2'] = trim($indexedName);
        }

        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    private function reportRows(string $fileNumber, $propId): array
    {
        try {
            $query = ['file_number' => $fileNumber];
            if (trim((string) $propId) !== '') {
                $query['prop_id'] = trim((string) $propId);
            }

            $report = $this->legalSearch->buildPrintReport($query);

            if (($report['status'] ?? null) !== 200) {
                return [];
            }

            $rows = $report['payload']['data']['rows'] ?? [];
        } catch (\Throwable $e) {
            Log::warning('op-holder-match.report-engine-failed', [
                'file_number' => $fileNumber,
                'error'       => $e->getMessage(),
            ]);

            return [];
        }

        // "-" is the report's placeholder for an absent value; the card renders its
        // own em-dash for empty, so translate rather than printing a literal dash.
        $blank = fn ($v) => ($v === null || trim((string) $v) === '' || trim((string) $v) === '-')
            ? '' : trim((string) $v);

        $mapped = [];
        foreach ($rows as $row) {
            $type = $this->tidyInstrument($blank($row['instrument_type'] ?? null) ?: 'Transaction');

            $mapped[] = [
                'type'     => $type,
                'party_1'  => $blank($row['grantor'] ?? null),
                'party_2'  => $blank($row['grantee'] ?? null),
                // transaction_date FIRST, reg_date only as a fallback — for the OP
                // above all, whose date is the date of the grant. Registration is a
                // later, separate event, and on a chain that argues about when the
                // title moved, showing the registration date as if it were the
                // dealing's date misdates the grant the transfer is built from.
                'date'     => $blank($row['transaction_date'] ?? null) ?: $blank($row['reg_date'] ?? null),
                'reg_no'   => $blank($row['reg_no'] ?? null),
                'source'   => $blank($row['source_table'] ?? null),
                // Which file the row actually belongs to. The report gathers a whole
                // PARCEL, so a row here can belong to a different file that shares
                // the prop_id — and a dealing on someone else's file says nothing
                // about this one's title.
                'file_no'  => $blank($row['lifecycle_file_no'] ?? null) ?: $blank($row['file_no'] ?? null),
                // The engine stamps this on the ONE row that is the root of title,
                // and that row is the whole subject of this card — so it is carried
                // through and badged exactly as the Property Timeline badges it.
                'root_of_title' => $blank($row['root_of_title'] ?? null),
                'is_op'    => stripos($type, 'Occupancy Permit') !== false,
                'is_tot'   => stripos($type, 'Transfer of Title') !== false,
                'system_generated' => false,
            ];
        }

        return $mapped;
    }

    /**
     * pra on its own, shaped like a report row. Only reached when the report engine
     * returns nothing for the file.
     *
     * @return array<int,array<string,mixed>>
     */
    private function praFallbackRows(string $fileNumber): array
    {
        return array_map(function ($r) use ($fileNumber) {
            $type = (string) ($r->transaction_type ?: $r->instrument_type ?: 'Transaction');

            return [
                'type'    => $type,
                'party_1' => trim((string) $r->party_1),
                'party_2' => trim((string) $r->party_2),
                'date'    => $this->displayDate($r),
                'reg_no'  => trim((string) $r->regNo),
                'source'  => 'pra',
                'file_no' => $fileNumber,
                'root_of_title' => '',
                'is_op'   => stripos($type, 'Occupancy Permit') !== false,
                'is_tot'  => stripos($type, 'Transfer of Title') !== false,
                'system_generated' => false,
            ];
        }, $this->praRows($fileNumber));
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
                // Both file-number columns: conflictingFileNumber() compares them, and
                // a row that names two different files has to be able to say so.
                'mlsFNo', 'fileno',
            ])
            ->all();
    }

    /**
     * The pra Occupancy Permit row the new transfer is copied from.
     *
     * Matched on the holder the chain names rather than just taking the first OP:
     * the report may be showing an OP that came from another table, and copying the
     * wrong grant would carry the wrong parcel, location and land use onto the
     * transfer.
     */
    private function sourceOpRow(string $fileNumber, $holder): ?object
    {
        foreach ($this->praRows($fileNumber) as $row) {
            if ($this->isOp($row) && $this->sameName($row->party_2, $holder)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Does this dealing move the title? Asked of TitleHolderResolver so a mortgage,
     * a surrender, a caveat or a recertification is treated here exactly as it is
     * treated in Legal Search and on the holder lines.
     */
    private function movesOwnership(array $row): bool
    {
        return $this->titleHolders->movesOwnership([
            'transaction_type' => $row['type'],
            'instrument_type'  => $row['type'],
        ]);
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

    /**
     * Undo what the report engine's title-casing does to instrument names.
     *
     * It upper-cases the first letter of every word, which turns the acronyms into
     * words: "Occupancy Permit (OP)" comes back as "(Op)" and "(OSS)" as "(Oss)".
     * The engine cannot tell an acronym from a word, so the names are restored here,
     * where the card knows which ones they are.
     */
    private function tidyInstrument(string $type): string
    {
        $type = preg_replace_callback(
            '/\((op|oss|rc|cofo|dciv|sltr|st)\)/i',
            fn ($m) => '(' . strtoupper($m[1]) . ')',
            $type
        );

        // "Transfer Of Title" is the same casing artefact one word further in.
        // Word-bounded: unbounded, this reaches inside names like "Ofoegbu".
        return preg_replace('/\bOf\b/', 'of', $type);
    }

    private function isOp(object $row): bool
    {
        return $this->typeContains($row, 'Occupancy Permit');
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
