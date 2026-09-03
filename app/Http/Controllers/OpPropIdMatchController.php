<?php

namespace App\Http\Controllers;

use App\Services\OpPropIdMatchService;
use App\Services\PropertyIdAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * OP → File Property ID Matching.
 *
 * A two-panel page. On the left the officer finds the CONFIRMED file and loads its
 * Property ID; that is the control record and the target. On the right they search
 * the Occupancy Permits — by serial number first, because that is what is printed on
 * the permit in front of them — tick the ones that belong to the file, and press
 * Batch Match. Every ticked permit is moved onto the file's Property ID.
 *
 * WHY SERIAL, NOT NAME
 * OP serials are heavily reused (roughly three permits per serial, some carry twenty),
 * so a serial search returns a shortlist rather than an answer — which is exactly what
 * is wanted here, because the officer is choosing. Names are far worse: they are
 * transliterated inconsistently and a name search on 36k permits is a fishing trip.
 * Name is offered as a NARROWING filter, never as the primary one.
 *
 * TEMPORARY FILE NUMBERS
 * A permit captured before its file existed carries a TEMP-xxxxx in `fileno` and
 * `temp_fileno` while the confirmed number, if it was ever written back, sits in
 * `mlsFNo`. All three are searched, so a permit is findable by whichever number the
 * officer happens to be holding.
 *
 * @see \App\Services\OpPropIdMatchService  the rule and the write
 */
class OpPropIdMatchController extends Controller
{
    private const CONNECTION = 'sqlsrv';

    /** How many OP candidates a search hands back. Enough to choose from, not to browse. */
    private const OP_LIMIT = 200;

    /**
     * Every file_indexings column that can hold the number the file picker returns.
     * The picker's tabs hand back an MLS number, a KANGIS number or a New KANGIS
     * number depending on which one the officer used, and only the first of those is
     * what `file_number` holds.
     */
    private const INDEXING_ALIAS_COLUMNS = [
        'file_number',
        'mls_file_no',
        'kangis_file_no',
        'new_kangis_file_no',
        'temp_file_no',
    ];

    /** The PropID_Master columns that can carry a file's number, plus their _norm twins. */
    private const MASTER_ALIAS_COLUMNS = [
        'primary_file_number',
        'mlsFNo',
        'kangisFileNo',
        'NewKANGISFileno',
        'temp_fileno',
    ];

    public function __construct(private OpPropIdMatchService $matcher)
    {
    }

    public function index()
    {
        return view('op_propid_match.index', ['PageTitle' => 'OP → File Property ID Matching']);
    }


    /**
     * Load one file as the target: its Property ID, and what is already sitting on it.
     *
     * PropID_Master is the authority. file_indexings.prop_id is NOT consulted — a bulk
     * import wrote row ordinals into that column on thousands of files, and reading it
     * here would aim a batch of permits at a number that identifies nothing.
     *
     * A FILE WITH NO REGISTERED PROPERTY ID IS NOT GIVEN ONE SILENTLY.
     *
     * The picker offers KANGIS and New KANGIS tabs, and a land file, its Old KANGIS file
     * and its New KANGIS file each hold their OWN prop_id by design — they are three
     * physical files over one parcel, linked upward by parent_prop_id, not one file with
     * three names. So an unregistered KANGIS number is a routine thing to pick, and
     * quietly minting a parcel id for it would be the worst possible answer: the batch
     * would land on a brand-new id belonging to nothing, splitting the file instead of
     * consolidating it — the exact failure this page exists to repair. (Observed: picking
     * "KNML 4545" minted a fresh id while the land file behind it already held 10373.)
     *
     * Instead the endpoint reports `needs_allocation` along with every related number on
     * the file and the prop_id each one carries, and the officer either picks the related
     * file that already has an id or asks for a new one explicitly (`allocate=1`).
     */
    public function fileTarget(Request $request): JsonResponse
    {
        $fileNo = trim((string) $request->query('file_no', ''));

        if ($fileNo === '') {
            return response()->json(['success' => false, 'message' => 'A file number is required.'], 422);
        }

        $conn = DB::connection(self::CONNECTION);

        $mls = $conn->table('mls_file_no')
            ->whereRaw('LTRIM(RTRIM(full_file_number)) = ?', [$fileNo])
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')
            ->first();

        // Every column of file_indexings that can hold the number the selector handed
        // back. The picker's KANGIS and New KANGIS tabs return a KANGIS number, which is
        // NOT what file_number holds — matching that column alone would 404 a file that
        // is plainly indexed, on the tab the officer was invited to use.
        $indexing = $conn->table('file_indexings')
            ->where(function ($q) use ($fileNo) {
                foreach (self::INDEXING_ALIAS_COLUMNS as $column) {
                    $q->orWhereRaw("LTRIM(RTRIM($column)) = ?", [$fileNo]);
                }
            })
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')
            ->first();

        if (! $mls && ! $indexing) {
            return response()->json([
                'success' => false,
                'message' => 'No confirmed file with that number. A file must be in MLS File Numbers '
                    . 'or File Indexing before permits can be matched to it.',
            ], 404);
        }

        // Every number this physical file is known by, the picked one first. Each is
        // looked up separately: they are DIFFERENT parcels in PropID_Master by design,
        // so this is a list to show the officer, never a set to collapse into one answer.
        $related = $this->relatedFileNumbers($fileNo, $mls, $indexing);
        $propIds = $this->propIdsForFileNumbers($related);
        $propId = $propIds[mb_strtoupper($fileNo)] ?? null;
        $allocated = false;

        // Allocation happens only when the officer asks for it, having seen the related
        // numbers below and decided none of them is the file in front of them.
        if ($propId === null && $request->boolean('allocate')) {
            try {
                $propId = (int) app(PropertyIdAllocationService::class)->allocateOrRetrievePropId($fileNo, $fileNo);
                $allocated = $propId > 0;

                Log::channel('op_batch')->warning('OP prop_id match: allocated a new Property ID on request', [
                    'file_no' => $fileNo,
                    'prop_id' => $propId,
                    'user' => \Illuminate\Support\Facades\Auth::id(),
                ]);
            } catch (\Throwable $e) {
                Log::channel('op_batch')->warning('OP prop_id match: could not allocate a target prop_id', [
                    'file_no' => $fileNo,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'This file has no registered Property ID and one could not be allocated: '
                        . $e->getMessage(),
                ], 422);
            }
        }

        // What the file is called in its own indexing row. When the officer picked a
        // KANGIS number, this is the land file number behind it — worth showing, because
        // the permits they are about to move will be carrying THAT one.
        $canonical = trim((string) ($indexing->file_number ?? ''));

        return response()->json([
            'success' => true,
            'data' => [
                'file_number' => $fileNo,
                'also_known_as' => ($canonical !== '' && mb_strtoupper($canonical) !== mb_strtoupper($fileNo)) ? $canonical : null,
                // Null means "not registered". The page must offer the choice below rather
                // than treat this as a target.
                'needs_allocation' => $propId === null,
                'related' => collect($related)
                    ->reject(fn ($number) => mb_strtoupper($number) === mb_strtoupper($fileNo))
                    ->map(fn ($number) => [
                        'file_number' => $number,
                        'prop_id' => $propIds[mb_strtoupper($number)] ?? null,
                    ])
                    ->values()
                    ->all(),
                'file_title' => trim((string) ($mls->file_name ?? $indexing->file_title ?? '')),
                'land_use' => trim((string) ($mls->land_use ?? $indexing->land_use_type ?? '')),
                'lga' => trim((string) ($mls->lga ?? $indexing->lga ?? '')),
                'location' => trim((string) ($mls->location ?? $indexing->location ?? '')),
                'plot_no' => trim((string) ($mls->plot_no ?? $indexing->plot_number ?? '')),
                'district' => trim((string) ($mls->district ?? $indexing->district ?? '')),
                'commissioned' => (bool) ($mls->commissioning_date ?? false),
                'in_mls' => (bool) $mls,
                'indexed' => (bool) $indexing,
                'prop_id' => $propId,
                'prop_id_allocated' => $allocated,
                'existing' => $propId ? $this->recordsOnPropId($propId) : [],
            ],
        ]);
    }

    /**
     * Advanced OP search.
     *
     * Serial is the primary key into this and can be a LIST — a permit book hands the
     * officer a run of serials, and typing them one at a time is how a batch gets half
     * done. Split on comma, semicolon and newline ONLY: KANGIS-era numbers contain
     * spaces, so splitting on whitespace would tear them in half.
     */
    public function searchOps(Request $request): JsonResponse
    {
        $serialInput = trim((string) $request->query('serial', ''));
        $fileNo = trim((string) $request->query('file_no', ''));
        $party = trim((string) $request->query('party', ''));
        $propId = trim((string) $request->query('prop_id', ''));
        $opType = trim((string) $request->query('op_type', ''));
        $mode = in_array($request->query('serial_mode'), ['exact', 'starts', 'contains'], true)
            ? $request->query('serial_mode')
            : 'exact';
        $excludePropId = trim((string) $request->query('exclude_prop_id', ''));
        $unlinkedOnly = $request->boolean('unlinked_only');

        $serials = $this->splitList($serialInput);

        if (empty($serials) && $fileNo === '' && $party === '' && $propId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Give at least one of: OP serial number, file number, holder name, or Property ID.',
            ], 422);
        }

        // A name on its own would sweep 36k permits. It narrows a search; it does not make one.
        if (empty($serials) && $fileNo === '' && $propId === '' && mb_strlen($party) < 3) {
            return response()->json([
                'success' => false,
                'message' => 'A holder name on its own must be at least 3 characters — or add a serial, file number or Property ID.',
            ], 422);
        }

        $conn = DB::connection(self::CONNECTION);

        try {
            $praRows = $this->searchPra($conn, $serials, $mode, $fileNo, $party, $propId, $opType, $excludePropId, $unlinkedOnly);
            $icRows = $this->searchInstrumentCapture($conn, $serials, $mode, $fileNo, $party, $propId, $opType, $excludePropId, $unlinkedOnly);
        } catch (\Throwable $e) {
            Log::channel('op_batch')->error('OP prop_id match: OP search failed', [
                'serials' => $serials,
                'file_no' => $fileNo,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The OP search could not be run: ' . $e->getMessage(),
            ], 500);
        }

        $rows = $praRows->concat($icRows)
            ->sortBy([['op_serial_number', 'asc'], ['op_id', 'desc']])
            ->take(self::OP_LIMIT)
            ->values();

        $rows = $this->attachCompanionCounts($conn, $rows);

        Log::channel('op_batch')->info('OP prop_id match: OP search', [
            'serials' => count($serials),
            'file_no' => $fileNo,
            'party' => $party !== '' ? 'yes' : 'no',
            'hits' => $rows->count(),
        ]);

        // Either sub-query coming back FULL means the database had more to give — the
        // sum overflowing the cap is not the only way this search was cut short, and a
        // page that under-reports truncation is a page that hides permits the officer
        // believes they have seen all of.
        $truncated = $praRows->count() >= self::OP_LIMIT
            || $icRows->count() >= self::OP_LIMIT
            || $praRows->count() + $icRows->count() > self::OP_LIMIT;

        return response()->json([
            'success' => true,
            'count' => $rows->count(),
            'truncated' => $truncated,
            'data' => $rows,
        ]);
    }

    /** Move the selected OPs onto the target file's Property ID. */
    public function batchMatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_no' => 'nullable|string|max:100',
            'prop_id' => 'required|integer|min:1',
            'ops' => 'required|array|min:1',
            'ops.*.source_table' => 'required|string|in:pra,instrument_capture',
            'ops.*.op_id' => 'required|integer|min:1',
            'move_companions' => 'nullable|boolean',
        ], [
            'ops.required' => 'Tick at least one OP before matching.',
            'prop_id.required' => 'Select a confirmed file on the left first.',
        ]);

        $result = $this->matcher->batchMatch(
            (int) $validated['prop_id'],
            isset($validated['file_no']) ? trim((string) $validated['file_no']) : null,
            $validated['ops'],
            $request->boolean('move_companions', true)
        );

        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'batch_ref' => $result['batch_ref'],
            'moved' => $result['moved'],
            'companions' => $result['companions'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors'],
            'existing' => $this->recordsOnPropId((int) $validated['prop_id']),
        ]);
    }

    /** The last few batches this page performed, newest first, for the Undo strip. */
    public function recentBatches(): JsonResponse
    {
        if (! Schema::connection(self::CONNECTION)->hasTable('op_propid_matches')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $batches = DB::connection(self::CONNECTION)->table('op_propid_matches')
            ->selectRaw('batch_ref, MAX(target_file_number) AS target_file_number, MAX(target_prop_id) AS target_prop_id,'
                . ' COUNT(*) AS records, SUM(CASE WHEN reverted_at IS NULL THEN 0 ELSE 1 END) AS reverted,'
                . ' MAX(created_at) AS created_at')
            ->groupBy('batch_ref')
            ->orderByDesc(DB::raw('MAX(created_at)'))
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'batch_ref' => $r->batch_ref,
                'file_number' => $r->target_file_number,
                'prop_id' => (int) $r->target_prop_id,
                'records' => (int) $r->records,
                'undone' => (int) $r->reverted === (int) $r->records,
                'created_at' => $r->created_at ? \Illuminate\Support\Carbon::parse($r->created_at)->format('d M Y g:i A') : null,
            ]);

        return response()->json(['success' => true, 'data' => $batches]);
    }

    /** Put one batch back. */
    public function undoBatch(Request $request): JsonResponse
    {
        $validated = $request->validate(['batch_ref' => 'required|string|max:40']);

        $result = $this->matcher->undo(trim($validated['batch_ref']));

        return response()->json(
            ['success' => $result['ok'], 'message' => $result['message']],
            $result['ok'] ? 200 : 422
        );
    }

    // ---------------------------------------------------------------- search internals

    private function searchPra($conn, array $serials, string $mode, string $fileNo, string $party, string $propId, string $opType, string $excludePropId, bool $unlinkedOnly)
    {
        $query = $conn->table('pra as o')
            ->where('o.instrument_type', 'LIKE', OpPropIdMatchService::PRA_OP_TYPE_LIKE)
            ->where(fn ($q) => $q->whereNull('o.is_deleted')->orWhere('o.is_deleted', 0));

        $this->applySerialFilter($query, 'o.op_serial_number', $serials, $mode);
        $this->applyFileNumberFilter($query, ['o.mlsFNo', 'o.fileno', 'o.temp_fileno'], $fileNo);
        $this->applyPartyFilter($query, ['o.party_1', 'o.party_2', 'o.Grantee', 'o.Assignee'], $party);

        if ($propId !== '') {
            $query->where('o.prop_id', $propId);
        }

        if ($opType !== '') {
            $query->where('o.op_type', $opType);
        }

        if ($excludePropId !== '') {
            $query->where(function ($q) use ($excludePropId) {
                $q->whereNull('o.prop_id')->orWhere('o.prop_id', '<>', $excludePropId);
            });
        }

        if ($unlinkedOnly) {
            $this->applyUnlinkedFilter($query, 'pra', 'o.id', 'o.prop_id');
        }

        return $query->select([
                'o.id', 'o.prop_id', 'o.op_type', 'o.op_serial_number', 'o.mlsFNo', 'o.fileno', 'o.temp_fileno',
                'o.party_1', 'o.party_2', 'o.transaction_date', 'o.regNo', 'o.serialNo', 'o.pageNo', 'o.volumeNo',
                'o.plot_no', 'o.tp_no', 'o.lgsaOrCity as lga', 'o.location', 'o.property_description',
                'o.land_use', 'o.op_batch', 'o.created_at',
            ])
            ->orderByDesc('o.id')
            ->limit(self::OP_LIMIT)
            ->get()
            ->map(fn ($r) => [
                'source_table' => 'pra',
                'op_id' => (int) $r->id,
                'prop_id' => trim((string) ($r->prop_id ?? '')),
                'op_type' => trim((string) ($r->op_type ?? '')),
                'op_serial_number' => trim((string) ($r->op_serial_number ?? '')),
                'file_no' => $this->firstNonEmpty([$r->mlsFNo, $r->fileno, $r->temp_fileno]),
                // The temporary number only. `fileno` is NOT a fallback here: on most rows
                // it holds the confirmed number, and showing that in the TEMP badge would
                // label a settled file as provisional.
                'temp_file_no' => trim((string) ($r->temp_fileno ?? '')),
                'grantor' => trim((string) ($r->party_1 ?? '')),
                'holder' => trim((string) ($r->party_2 ?? '')),
                'transaction_date' => $r->transaction_date ?: null,
                'reg_particulars' => $this->regParticulars($r->serialNo ?? null, $r->pageNo ?? null, $r->volumeNo ?? null, $r->regNo ?? null),
                'plot_no' => trim((string) ($r->plot_no ?? '')),
                'tp_no' => trim((string) ($r->tp_no ?? '')),
                'lga' => trim((string) ($r->lga ?? '')),
                'location' => $this->firstNonEmpty([$r->location, $r->property_description]),
                'land_use' => trim((string) ($r->land_use ?? '')),
                'op_batch' => trim((string) ($r->op_batch ?? '')),
                'created_at' => $r->created_at,
            ]);
    }

    private function searchInstrumentCapture($conn, array $serials, string $mode, string $fileNo, string $party, string $propId, string $opType, string $excludePropId, bool $unlinkedOnly)
    {
        $query = $conn->table('instrument_capture as o')
            ->where('o.instrument_type', OpPropIdMatchService::IC_OP_TYPE)
            ->where(fn ($q) => $q->whereNull('o.is_deleted')->orWhere('o.is_deleted', 0));

        $this->applySerialFilter($query, 'o.op_serial_number', $serials, $mode);
        $this->applyFileNumberFilter($query, ['o.mlsFNo', 'o.temp_fileno'], $fileNo);
        $this->applyPartyFilter($query, ['o.party_1_name', 'o.party_2_name'], $party);

        // prop_id is bigint here, so a non-numeric filter cannot match anything and must
        // not be handed to the driver as a comparison.
        if ($propId !== '') {
            if (! ctype_digit($propId)) {
                return collect();
            }
            $query->where('o.prop_id', (int) $propId);
        }

        if ($opType !== '') {
            $query->where('o.op_type', $opType);
        }

        if ($excludePropId !== '' && ctype_digit($excludePropId)) {
            $query->where(function ($q) use ($excludePropId) {
                $q->whereNull('o.prop_id')->orWhere('o.prop_id', '<>', (int) $excludePropId);
            });
        }

        if ($unlinkedOnly) {
            $this->applyUnlinkedFilter($query, 'instrument_capture', 'o.id', 'o.prop_id');
        }

        return $query->select([
                'o.id', 'o.prop_id', 'o.op_type', 'o.op_serial_number', 'o.mlsFNo', 'o.temp_fileno',
                'o.party_1_name', 'o.party_2_name', 'o.instrument_date', 'o.registration_number',
                'o.serial_no', 'o.page_no', 'o.volume_no', 'o.plot_number', 'o.tp_no',
                'o.lga', 'o.property_location', 'o.property_description', 'o.land_use', 'o.created_at',
            ])
            ->orderByDesc('o.id')
            ->limit(self::OP_LIMIT)
            ->get()
            ->map(fn ($r) => [
                'source_table' => 'instrument_capture',
                'op_id' => (int) $r->id,
                'prop_id' => trim((string) ($r->prop_id ?? '')),
                'op_type' => trim((string) ($r->op_type ?? '')),
                'op_serial_number' => trim((string) ($r->op_serial_number ?? '')),
                'file_no' => $this->firstNonEmpty([$r->mlsFNo, $r->temp_fileno]),
                'temp_file_no' => trim((string) ($r->temp_fileno ?? '')),
                'grantor' => trim((string) ($r->party_1_name ?? '')),
                'holder' => trim((string) ($r->party_2_name ?? '')),
                'transaction_date' => $r->instrument_date ?: null,
                'reg_particulars' => $this->regParticulars($r->serial_no ?? null, $r->page_no ?? null, $r->volume_no ?? null, $r->registration_number ?? null),
                'plot_no' => trim((string) ($r->plot_number ?? '')),
                'tp_no' => trim((string) ($r->tp_no ?? '')),
                'lga' => trim((string) ($r->lga ?? '')),
                'location' => $this->firstNonEmpty([$r->property_location, $r->property_description]),
                'land_use' => trim((string) ($r->land_use ?? '')),
                'op_batch' => '',
                'created_at' => $r->created_at,
            ]);
    }

    /**
     * Serials, matched the way the officer asked.
     *
     * Exact is the default and is what a permit in hand calls for; the two LIKE forms
     * are for a serial that was written down with a prefix or a suffix nobody kept.
     */
    private function applySerialFilter($query, string $column, array $serials, string $mode): void
    {
        if (empty($serials)) {
            return;
        }

        $query->where(function ($q) use ($column, $serials, $mode) {
            foreach ($serials as $serial) {
                if ($mode === 'exact') {
                    $q->orWhereRaw("LTRIM(RTRIM($column)) = ?", [$serial]);
                } else {
                    $pattern = $mode === 'starts'
                        ? $this->escapeLike($serial) . '%'
                        : '%' . $this->escapeLike($serial) . '%';
                    $q->orWhere(DB::raw("LTRIM(RTRIM($column))"), 'LIKE', $pattern);
                }
            }
        });
    }

    /**
     * A file number, matched against every column that can hold one.
     *
     * Temporary numbers matter here: a permit captured before its file existed carries
     * TEMP-xxxxx and nothing else, and it is the permit most in need of this page.
     */
    private function applyFileNumberFilter($query, array $columns, string $fileNo): void
    {
        if ($fileNo === '') {
            return;
        }

        $pattern = '%' . $this->escapeLike($fileNo) . '%';

        $query->where(function ($q) use ($columns, $fileNo, $pattern) {
            foreach ($columns as $column) {
                $q->orWhereRaw("LTRIM(RTRIM($column)) = ?", [$fileNo])
                    ->orWhere($column, 'LIKE', $pattern);
            }
        });
    }

    private function applyPartyFilter($query, array $columns, string $party): void
    {
        if ($party === '') {
            return;
        }

        $pattern = '%' . $this->escapeLike($party) . '%';

        $query->where(function ($q) use ($columns, $pattern) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'LIKE', $pattern);
            }
        });
    }

    /**
     * Only permits that no Transfer of Title has claimed.
     *
     * Same two representations as OpResettlementApplicationController checks, for the
     * same reason: they disagree in the data, so either one counts as claimed.
     */
    private function applyUnlinkedFilter($query, string $table, string $idColumn, string $propColumn): void
    {
        $query->whereNotExists(function ($q) use ($table, $idColumn) {
            $q->select(DB::raw(1))->from('pra as lnk')
                ->whereRaw("lnk.source_op_id = $idColumn")
                ->where('lnk.source_op_table', $table);
        })->whereNotExists(function ($q) use ($propColumn) {
            $q->select(DB::raw(1))->from('pra as lnk2')
                ->whereRaw("lnk2.prop_id = CAST($propColumn AS nvarchar(100))")
                ->where('lnk2.instrument_type', 'LIKE', '%Transfer of Title%');
        });
    }

    /**
     * How many rows would travel with each permit if Batch Match is pressed.
     *
     * Shown per row so the officer sees the true size of the change before making it —
     * a batch of eight permits that quietly moves twelve rows is a surprise, and this
     * page's whole job is to stop parcel ids being changed by surprise.
     */
    private function attachCompanionCounts($conn, $rows)
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        // ONE query for the whole page, not one per row: this list runs to 200 permits
        // and a per-row count would be 200 round trips for a decoration.
        $opIds = $rows->pluck('op_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $propIds = $rows->pluck('prop_id')->filter(fn ($v) => trim((string) $v) !== '')->unique()->values()->all();

        try {
            $candidates = $conn->table('pra')
                ->select('id', 'source_op_table', 'source_op_id', 'prop_id', 'instrument_type')
                ->where(function ($q) use ($opIds, $propIds) {
                    $q->where(function ($pointer) use ($opIds) {
                        $pointer->whereIn('source_op_id', $opIds)
                            ->whereIn('source_op_table', OpPropIdMatchService::OP_TABLES);
                    });

                    if (! empty($propIds)) {
                        $q->orWhere(function ($shared) use ($propIds) {
                            $shared->whereIn('prop_id', $propIds)
                                ->where('instrument_type', 'LIKE', '%Transfer of Title%');
                        });
                    }
                })
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->get();
        } catch (\Throwable $e) {
            // A count is decoration. Failing it must not lose the officer their search.
            Log::channel('op_batch')->warning('OP prop_id match: companion count failed', [
                'error' => $e->getMessage(),
            ]);

            return $rows->map(function ($row) {
                $row['companions'] = null;

                return $row;
            });
        }

        // Distinct rows per permit, matching OpPropIdMatchService::findCompanions exactly:
        // a ToT that BOTH points at the permit and shares its prop_id is one companion,
        // not two, and a permit is never its own companion.
        return $rows->map(function ($row) use ($candidates) {
            $propId = trim((string) $row['prop_id']);

            $ids = $candidates->filter(function ($candidate) use ($row, $propId) {
                if ($row['source_table'] === 'pra' && (int) $candidate->id === (int) $row['op_id']) {
                    return false;
                }

                $byPointer = (string) $candidate->source_op_table === $row['source_table']
                    && (int) $candidate->source_op_id === (int) $row['op_id'];

                // The shared-prop_id arm counts Transfers of Title only. The query above
                // pulled non-ToT rows in too — they were fetched by the pointer arm, for
                // some OTHER permit — so the type has to be re-checked here or a pointer
                // row would be counted against every permit that shares its parcel id.
                $byPropId = $propId !== ''
                    && trim((string) $candidate->prop_id) === $propId
                    && stripos((string) $candidate->instrument_type, 'Transfer of Title') !== false;

                return $byPointer || $byPropId;
            })->pluck('id')->unique();

            $row['companions'] = $ids->count();

            return $row;
        });
    }

    // ---------------------------------------------------------------- shared helpers

    /**
     * prop_id for a set of file numbers, read from PropID_Master only.
     *
     * @param  array<int,string>  $fileNumbers
     * @return array<string,int>  keyed by UPPERCASED file number
     */
    private function propIdsForFileNumbers(array $fileNumbers): array
    {
        $wanted = [];
        foreach ($fileNumbers as $fileNumber) {
            $value = mb_strtoupper(trim((string) $fileNumber));
            if ($value !== '') {
                $wanted[$value] = true;
            }
        }

        if (empty($wanted) || ! $this->masterExists()) {
            return [];
        }

        $wanted = array_keys($wanted);
        $columns = array_merge(
            self::MASTER_ALIAS_COLUMNS,
            array_map(fn ($c) => $c . '_norm', self::MASTER_ALIAS_COLUMNS)
        );

        $out = [];

        try {
            DB::connection(self::CONNECTION)->table('PropID_Master')
                ->select(array_merge(['prop_id'], self::MASTER_ALIAS_COLUMNS))
                ->where(function ($q) use ($columns, $wanted) {
                    foreach ($columns as $column) {
                        $q->orWhereIn($column, $wanted);
                    }
                })
                ->orderBy('updated_at')
                ->get()
                ->each(function ($row) use (&$out) {
                    foreach (self::MASTER_ALIAS_COLUMNS as $column) {
                        $alias = mb_strtoupper(trim((string) ($row->{$column} ?? '')));
                        if ($alias !== '') {
                            $out[$alias] = (int) $row->prop_id;
                        }
                    }
                });
        } catch (\Throwable $e) {
            Log::channel('op_batch')->warning('OP prop_id match: PropID_Master lookup failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        // Only the numbers actually asked for; the loop above also indexed every other
        // alias on the same master rows.
        return array_intersect_key($out, array_flip($wanted));
    }

    /** What already sits on the target Property ID, so the officer sees the file's current shape. */
    private function recordsOnPropId(int $propId): array
    {
        $conn = DB::connection(self::CONNECTION);
        $value = (string) $propId;

        try {
            $rows = $conn->table('pra')
                ->where('prop_id', $value)
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->select('id', 'instrument_type', 'op_serial_number', 'mlsFNo', 'fileno', 'temp_fileno', 'party_1', 'party_2', 'transaction_date')
                ->orderBy('id')
                ->limit(50)
                ->get();

            $ic = $conn->table('instrument_capture')
                ->where('prop_id', $propId)
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->select('id', 'instrument_type', 'op_serial_number', 'mlsFNo', 'temp_fileno', 'party_1_name', 'party_2_name', 'instrument_date')
                ->orderBy('id')
                ->limit(50)
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        $out = $rows->map(fn ($r) => [
            'source_table' => 'pra',
            'id' => (int) $r->id,
            'instrument_type' => trim((string) ($r->instrument_type ?? '')),
            'op_serial_number' => trim((string) ($r->op_serial_number ?? '')),
            'file_no' => $this->firstNonEmpty([$r->mlsFNo, $r->fileno, $r->temp_fileno]),
            'party_1' => trim((string) ($r->party_1 ?? '')),
            'party_2' => trim((string) ($r->party_2 ?? '')),
            'date' => $r->transaction_date ?: null,
        ])->concat($ic->map(fn ($r) => [
            'source_table' => 'instrument_capture',
            'id' => (int) $r->id,
            'instrument_type' => trim((string) ($r->instrument_type ?? '')),
            'op_serial_number' => trim((string) ($r->op_serial_number ?? '')),
            'file_no' => $this->firstNonEmpty([$r->mlsFNo, $r->temp_fileno]),
            'party_1' => trim((string) ($r->party_1_name ?? '')),
            'party_2' => trim((string) ($r->party_2_name ?? '')),
            'date' => $r->instrument_date ?: null,
        ]));

        return $out->values()->all();
    }

    /**
     * Every number this one physical file is known by, the picked one first.
     *
     * Used to SHOW the officer the alternatives when the number they picked has no
     * Property ID of its own — never to pick one on their behalf. A land file, its Old
     * KANGIS file and its New KANGIS file are three files over one parcel and hold three
     * distinct prop_ids deliberately, so collapsing them here would silently retarget the
     * batch at a file the officer did not choose.
     *
     * @return array<int,string>
     */
    private function relatedFileNumbers(string $fileNo, $mls, $indexing): array
    {
        $numbers = [$fileNo];

        foreach (self::INDEXING_ALIAS_COLUMNS as $column) {
            $numbers[] = trim((string) ($indexing->{$column} ?? ''));
        }

        $numbers[] = trim((string) ($mls->full_file_number ?? ''));
        $numbers[] = trim((string) ($mls->old_fileno ?? ''));

        $seen = [];
        $out = [];

        foreach ($numbers as $number) {
            $upper = mb_strtoupper($number);
            if ($number === '' || isset($seen[$upper])) {
                continue;
            }
            $seen[$upper] = true;
            $out[] = $number;
        }

        return $out;
    }

    private function masterExists(): bool
    {
        try {
            return Schema::connection(self::CONNECTION)->hasTable('PropID_Master');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Split a typed list on comma, semicolon and newline — never on whitespace.
     * KANGIS-era numbers contain spaces ("KNML 1"), and splitting those tears them apart.
     *
     * @return array<int,string>
     */
    private function splitList(string $input): array
    {
        if (trim($input) === '') {
            return [];
        }

        $parts = preg_split('/[,;\r\n]+/', $input) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $parts), fn ($v) => $v !== '')));
    }

    /** LIKE metacharacters are literal when an officer types them into a search box. */
    private function escapeLike(string $value): string
    {
        return str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $value);
    }

    private function firstNonEmpty(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function regParticulars($serial, $page, $volume, $regNo): string
    {
        $serial = trim((string) $serial);
        $page = trim((string) $page);
        $volume = trim((string) $volume);

        if ($serial !== '' || $page !== '' || $volume !== '') {
            return trim(($serial !== '' ? $serial : '—') . '/' . ($page !== '' ? $page : '—') . '/' . ($volume !== '' ? $volume : '—'));
        }

        return trim((string) $regNo);
    }
}
