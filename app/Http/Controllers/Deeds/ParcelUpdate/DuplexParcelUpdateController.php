<?php

namespace App\Http\Controllers\Deeds\ParcelUpdate;

use App\Http\Controllers\Controller;
use App\Models\DuplexParcelUpdate;
use App\Models\DuplexParcelUpdateFile;
use App\Models\DuplexParcelUpdateStage;
use App\Models\StreetName;
use App\Support\FileNumberLandUse;
use App\Services\DuplexCommitService;
use App\Services\DuplexSummaryService;
use App\Services\DuplexHoldingNumberService;
use App\Services\ParcelUpdateNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Duplex Parcel Update — several parcel updates carried as one instruction, on one
 * page, from capture through to commissioning.
 *
 * The page is self-contained by design: the five single-workflow pages are left
 * untouched, and the Land confirm/reject step lives here rather than as another tab
 * on the commissioning screen. A duplex carrying exactly ONE update is normal and
 * fully supported — sometimes there is only one thing to do, and the officer should
 * still be able to work here.
 *
 * Nothing in this controller writes to the registry. Real file numbers appear only
 * when commit() hands the duplex to DuplexCommitService, which delegates to the one
 * existing commissioning engine.
 */
class DuplexParcelUpdateController extends Controller
{
    public function __construct(
        protected ParcelUpdateNotificationService $parcelNotifier,
        protected DuplexHoldingNumberService $holding,
        protected DuplexCommitService $committer
    ) {}

    public function index(Request $request): View
    {
        $limit  = max(10, min((int) $request->input('limit', 50), 200));
        $search = trim((string) $request->input('search'));

        $records = DuplexParcelUpdate::query()
            ->visible()
            ->with('stageRows')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('duplex_id', 'LIKE', "%{$search}%")
                        ->orWhere('applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('file_title', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($limit);

        $states      = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas        = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts   = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        $stats = [
            'total'     => DuplexParcelUpdate::visible()->count(),
            'daily'     => DuplexParcelUpdate::visible()->whereDate('created_at', today())->count(),
            'draft'     => DuplexParcelUpdate::visible()->where('status', DuplexParcelUpdate::STATUS_DRAFT)->count(),
            'pending'   => DuplexParcelUpdate::visible()->whereIn('status', [
                DuplexParcelUpdate::STATUS_CAPTURED,
                DuplexParcelUpdate::STATUS_PENDING,
            ])->count(),
            'approved'  => DuplexParcelUpdate::visible()->whereIn('status', [
                DuplexParcelUpdate::STATUS_APPROVED,
                DuplexParcelUpdate::STATUS_IN_LAND,
            ])->count(),
            'committed' => DuplexParcelUpdate::visible()->where('status', DuplexParcelUpdate::STATUS_COMMITTED)->count(),
        ];

        return view('deeds.parcel_update.duplex.index', compact(
            'records', 'limit', 'search', 'states', 'lgas', 'districts', 'streetNames', 'stats'
        ) + ['types' => DuplexParcelUpdate::TYPES]);
    }

    /**
     * Step 1 + 2 — the ticked types with their ranks, and how many of each.
     *
     * Rank comes from the ORDER THE OFFICER TICKED and is stored as given. Nothing
     * downstream may re-derive it from a type list: the officer's order is the
     * execution order, and it is what the memo prints.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'applicant_name'   => 'required|string|max:255',
            'file_title'       => 'nullable|string|max:500',
            'source_file_nos'  => 'required|array|min:1',
            'source_file_nos.*' => 'required|string|max:100',
            'stages'           => 'required|array|min:1',
            'stages.*.type'    => 'required|string|in:' . implode(',', array_keys(DuplexParcelUpdate::TYPES)),
            'stages.*.rank'    => 'required|integer|min:1',
            'stages.*.count'   => 'nullable|integer|min:1|max:200',
            'plot_no'          => 'nullable|string|max:100',
            'house_no'         => 'nullable|string|max:100',
            'street_name'      => 'nullable|string|max:255',
            'district'         => 'nullable|string|max:255',
            'lga'              => 'nullable|string|max:255',
            'state'            => 'nullable|string|max:100',
            'phone'            => 'nullable|string|max:50',
            'address'          => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data   = $validator->validated();
        $stages = collect($data['stages'])->sortBy('rank')->values();

        // Ranks must be a clean 1..N sequence, or the runner cannot tell which stage
        // feeds which.
        $expected = range(1, $stages->count());
        if ($stages->pluck('rank')->map(fn ($r) => (int) $r)->all() !== $expected) {
            return response()->json([
                'success' => false,
                'message' => 'Stage ranks must run 1 to ' . $stages->count() . ' without gaps.',
            ], 422);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $duplexId = $this->holding->allocateDuplexId();
            $primary  = $data['source_file_nos'][0];

            $duplex = DuplexParcelUpdate::create([
                'duplex_id'       => $duplexId,
                'applicant_name'  => $data['applicant_name'],
                'file_title'      => $data['file_title'] ?? $data['applicant_name'],
                'source_file_nos' => $data['source_file_nos'],
                'stages'          => $stages->all(),
                'status'          => DuplexParcelUpdate::STATUS_DRAFT,
                'land_use'        => FileNumberLandUse::codeFor($primary),
                'plot_no'         => $data['plot_no'] ?? null,
                'house_no'        => $data['house_no'] ?? null,
                'street_name'     => $data['street_name'] ?? null,
                'district'        => $data['district'] ?? null,
                'lga'             => $data['lga'] ?? null,
                'state'           => $data['state'] ?? null,
                'phone'           => $data['phone'] ?? null,
                'address'         => $data['address'] ?? null,
                'captured_by'     => Auth::id(),
            ]);

            foreach ($stages as $stage) {
                DuplexParcelUpdateStage::create([
                    'duplex_parcel_update_id' => $duplex->id,
                    'duplex_id'   => $duplexId,
                    'type'        => $stage['type'],
                    'rank'        => (int) $stage['rank'],
                    'status'      => DuplexParcelUpdateStage::STATUS_PENDING,
                    'plot_count'  => isset($stage['count']) ? (int) $stage['count'] : null,
                    'captured_by' => Auth::id(),
                ]);
            }

            // The real files this duplex consumes. They are retired at commit by the
            // stage that reads them, not here.
            foreach (array_values($data['source_file_nos']) as $i => $fileNo) {
                DuplexParcelUpdateFile::create([
                    'duplex_parcel_update_id' => $duplex->id,
                    'duplex_id'         => $duplexId,
                    'role'              => DuplexParcelUpdateFile::ROLE_SOURCE,
                    'source_file_no'    => $fileNo,
                    'will_decommission' => 1,
                    'sequence'          => $i,
                ]);
            }

            DB::connection('sqlsrv')->commit();

            Log::info('Duplex created', [
                'duplex_id' => $duplexId,
                'stages'    => $stages->pluck('type')->all(),
                'sources'   => $data['source_file_nos'],
            ]);

            return response()->json([
                'success'   => true,
                'message'   => 'Duplex ' . $duplexId . ' created. Continue with the stages.',
                'duplex_id' => $duplexId,
                'id'        => $duplex->id,
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('Duplex creation failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::with(['stageRows.files', 'files'])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $duplex]);
    }

    /**
     * Save one stage and mint its holding numbers.
     *
     * Registry-free by construction: the only writes are to duplex_* tables.
     */
    public function saveStage(Request $request, int $id, int $stageId): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);
        $stage  = DuplexParcelUpdateStage::where('duplex_parcel_update_id', $duplex->id)->findOrFail($stageId);

        if ($duplex->status === DuplexParcelUpdate::STATUS_COMMITTED) {
            return response()->json(['success' => false, 'message' => 'This duplex has already been commissioned.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'plot_count'        => 'nullable|integer|min:1|max:200',
            'new_land_use'      => 'nullable|string|max:50',
            'applies_to'        => 'nullable|array',
            'applies_to.*'      => 'nullable|string|max:100',
            'plots'             => 'nullable|array',
            'plots.*.size'      => 'nullable|numeric|min:0',
            'plots.*.plot_no'   => 'nullable|string|max:100',
            'plots.*.holder'    => 'nullable|string|max:255',
            'plots.*.file_title' => 'nullable|string|max:500',
            'tracking_id'       => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if ($stage->type === 'change_of_purpose' && empty($data['new_land_use'])) {
            return response()->json([
                'success' => false,
                'message' => 'Select the new land use for the Change of Purpose stage.',
            ], 422);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            // reorder() first: the stages relation already sorts by rank ascending, and
            // SQL Server rejects a query naming the same column twice in ORDER BY.
            $previous = $duplex->stageRows()
                ->where('rank', '<', $stage->rank)
                ->reorder('rank', 'desc')
                ->first();

            // What this stage consumes: the previous stage's holding numbers, or the
            // duplex's real source files when this is the first stage.
            $inputHoldings = $previous
                ? $previous->files()->whereNotNull('holding_no')->pluck('holding_no')->all()
                : [];

            $payload = [
                'plots'          => array_values($data['plots'] ?? []),
                'new_land_use'   => $data['new_land_use'] ?? null,
                'applies_to'     => array_values($data['applies_to'] ?? []),
                'input_holdings' => $inputHoldings,
                'sources'        => $previous ? [] : ($duplex->source_file_nos ?? []),
            ];

            $stage->update([
                'plot_count'       => $data['plot_count'] ?? $stage->plot_count,
                'payload'          => $payload,
                'input_holding_no' => $inputHoldings[0] ?? null,
                'tracking_id'      => $data['tracking_id'] ?? $stage->tracking_id,
                'status'           => DuplexParcelUpdateStage::STATUS_DONE,
                'completed_at'     => now(),
                'updated_by'       => Auth::id(),
            ]);

            // Re-running a stage replaces its holding numbers rather than adding to
            // them, so a corrected stage does not leave orphans behind.
            $stage->files()->delete();

            if ($stage->type === 'change_of_purpose' && $previous) {
                // A Change of Purpose renames ONLY the files it was applied to. Each of
                // those gets a new number and its old one is decommissioned; the rest
                // keep the number the previous stage gave them and simply travel on.
                //
                // Minting a number for every incoming file was wrong: a 5-plot
                // subdivision followed by a 2-file CoP uses 7 numbers, not 10.
                $selected = array_values($payload['applies_to'] ?? []);
                $numbers  = $this->holding->allocateHoldingNumbers($duplex, max(1, count($selected)));
                $minted   = 0;

                foreach ($inputHoldings as $i => $incoming) {
                    $changing = in_array($incoming, $selected, true);

                    DuplexParcelUpdateFile::create([
                        'duplex_parcel_update_id'       => $duplex->id,
                        'duplex_parcel_update_stage_id' => $stage->id,
                        'duplex_id'   => $duplex->duplex_id,
                        'role'        => $changing
                            ? DuplexParcelUpdateFile::ROLE_HOLDING
                            : DuplexParcelUpdateFile::ROLE_CARRIED,
                        'holding_no'  => $changing ? $numbers[$minted++] : $incoming,
                        'file_title'  => $duplex->file_title,
                        'holder_name' => $duplex->applicant_name,
                        // Only a file being renamed has an old number to retire.
                        'will_decommission' => $changing ? 1 : 0,
                        'sequence'    => $i,
                    ]);
                }
            } else {
                $outputs = $stage->fresh()->outputCount();
                $numbers = $this->holding->allocateHoldingNumbers($duplex, max(1, $outputs));

                foreach ($numbers as $i => $holdingNo) {
                    $plot = $payload['plots'][$i] ?? [];
                    DuplexParcelUpdateFile::create([
                        'duplex_parcel_update_id'       => $duplex->id,
                        'duplex_parcel_update_stage_id' => $stage->id,
                        'duplex_id'         => $duplex->duplex_id,
                        'role'              => DuplexParcelUpdateFile::ROLE_HOLDING,
                        'holding_no'        => $holdingNo,
                        'file_title'        => $plot['holder'] ?? $duplex->file_title,
                        'plot_size'         => $plot['size'] ?? null,
                        'holder_name'       => $plot['holder'] ?? $duplex->applicant_name,
                        'will_decommission' => 1,
                        'sequence'          => $i,
                    ]);
                }
            }

            // All stages done -> the duplex is captured and ready for KNUPDA/approval.
            $allDone = $duplex->stageRows()->where('status', '!=', DuplexParcelUpdateStage::STATUS_DONE)->count() === 0;
            if ($allDone && $duplex->status === DuplexParcelUpdate::STATUS_DRAFT) {
                $duplex->update([
                    'status'     => DuplexParcelUpdate::STATUS_CAPTURED,
                    'updated_by' => Auth::id(),
                ]);

                $this->parcelNotifier->notifyCreated(
                    'duplex',
                    $duplex->id,
                    $duplex->duplex_id,
                    (string) $duplex->file_title,
                    (string) $duplex->applicant_name
                );
            }

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success'          => true,
                'message'          => $stage->label() . ' stage saved.',
                // What leaves this stage, in order — new numbers for the files that
                // changed, existing ones for the files that did not.
                'holding_numbers'  => $stage->files()->pluck('holding_no')->all(),
                'all_stages_done'  => $allDone,
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('Duplex stage save failed', [
                'duplex_id' => $duplex->duplex_id,
                'stage'     => $stage->rank,
                'error'     => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Reject a single stage. The rest of the duplex holds its position — only this
     * stage reopens for a re-run.
     */
    public function rejectStage(Request $request, int $id, int $stageId): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);
        $stage  = DuplexParcelUpdateStage::where('duplex_parcel_update_id', $duplex->id)->findOrFail($stageId);

        $stage->update([
            'status'        => DuplexParcelUpdateStage::STATUS_REJECTED,
            'reject_reason' => trim((string) $request->input('reason', '')) ?: null,
            'updated_by'    => Auth::id(),
        ]);

        // The duplex drops back to draft so the wizard reopens at this stage; the
        // other stages keep their saved payloads and holding numbers.
        $duplex->update([
            'status'     => DuplexParcelUpdate::STATUS_DRAFT,
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $stage->label() . ' stage sent back for re-run. The other stages are untouched.',
        ]);
    }

    public function updateKnupda(Request $request, int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);

        $duplex->update([
            'land_value'     => $request->input('land_value'),
            'knupda_fee'     => $request->input('knupda_fee'),
            'knupda_status'  => $request->input('knupda_status'),
            'knupda_remarks' => $request->input('knupda_remarks'),
            'updated_by'     => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'KNUPDA status updated.']);
    }

    /** One approval for the whole duplex, by the authority the single workflows use. */
    public function approve(int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);

        if ($duplex->stageRows()->where('status', '!=', DuplexParcelUpdateStage::STATUS_DONE)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Every stage must be completed before the duplex can be approved.',
            ], 422);
        }

        $duplex->update([
            'status'      => DuplexParcelUpdate::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'updated_by'  => Auth::id(),
        ]);

        $approver = Auth::user();
        $this->parcelNotifier->notifyApproved(
            'duplex',
            $duplex->id,
            $duplex->duplex_id,
            (string) $duplex->file_title,
            $approver ? ($approver->name ?? $approver->username ?? '') : ''
        );

        return response()->json(['success' => true, 'message' => 'Duplex approved.']);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);
        $reason = trim((string) $request->input('reason', ''));

        $duplex->update([
            'status'     => DuplexParcelUpdate::STATUS_REJECTED,
            'remarks'    => $reason ? "Rejected: {$reason}" : 'Rejected',
            'updated_by' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Duplex rejected.']);
    }

    public function generateApplication(int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);
        $duplex->update(['application_generated_at' => now(), 'updated_by' => Auth::id()]);

        return response()->json(['success' => true, 'message' => 'Application generated.']);
    }

    public function printApplication(int $id): View
    {
        $duplex = DuplexParcelUpdate::with(['stageRows.files'])->findOrFail($id);

        return view('deeds.parcel_update.duplex.print.application', compact('duplex'));
    }

    /**
     * The memo lists the component updates in the officer's ticked order — that
     * order is the instruction, and printing it in any other order misstates it.
     */
    public function generateRecommendation(int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);

        if (strcasecmp((string) $duplex->knupda_status, 'Approved') !== 0) {
            return response()->json([
                'success' => false,
                'message' => 'KNUPDA approval is required before the recommendation can be generated.',
            ], 422);
        }

        $duplex->update(['recommendation_generated_at' => now(), 'updated_by' => Auth::id()]);

        return response()->json(['success' => true, 'message' => 'Recommendation generated.']);
    }

    public function printRecommendation(int $id): View
    {
        $duplex = DuplexParcelUpdate::with(['stageRows.files'])->findOrFail($id);

        return view('deeds.parcel_update.duplex.print.recommendation', compact('duplex'));
    }

    public function generateConveyance(int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);
        $duplex->update(['conveyance_generated_at' => now(), 'updated_by' => Auth::id()]);

        return response()->json(['success' => true, 'message' => 'Conveyance generated.']);
    }

    public function printConveyance(int $id): View
    {
        $duplex = DuplexParcelUpdate::with(['stageRows.files'])->findOrFail($id);

        return view('deeds.parcel_update.duplex.print.conveyance', compact('duplex'));
    }

    /** Hand the approved duplex to Land. Still no registry writes. */
    public function sendToLand(int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);

        if ($duplex->status !== DuplexParcelUpdate::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Only an approved duplex can be sent to Land.',
            ], 422);
        }

        if (!$duplex->conveyance_generated_at || !$duplex->recommendation_generated_at) {
            return response()->json([
                'success' => false,
                'message' => 'Generate the memo and the conveyance before sending to Land.',
            ], 422);
        }

        $duplex->update([
            'status'        => DuplexParcelUpdate::STATUS_IN_LAND,
            'sent_to_land_at' => now(),
            'updated_by'    => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Duplex sent to Land for commissioning.']);
    }

    /**
     * The Land step, in this page — holding numbers on the left, the file numbers
     * they will become on the right, and everything that will be retired listed in
     * execution order. Read-only: Land confirms or rejects the whole duplex.
     */
    public function commissionView(int $id): View
    {
        $duplex = DuplexParcelUpdate::with(['stageRows.files', 'files'])->findOrFail($id);

        return view('deeds.parcel_update.duplex.commission', compact('duplex'));
    }

    /**
     * Approved duplexes, for the Duplex picker on the MLS commissioning modal.
     *
     * A duplex is commissioned from that modal like every other parcel update, so it
     * needs the same "pick an approved application" list the Subdivision, Merger and
     * Separation selectors use.
     */
    public function approvedList(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search'));

        $records = DuplexParcelUpdate::query()
            ->visible()
            ->with('stageRows')
            ->whereIn('status', [
                DuplexParcelUpdate::STATUS_APPROVED,
                DuplexParcelUpdate::STATUS_IN_LAND,
            ])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('duplex_id', 'LIKE', "%{$search}%")
                        ->orWhere('applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('file_title', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $records->map(fn ($d) => [
                'id'          => $d->id,
                'duplex_id'   => $d->duplex_id,
                'applicant'   => $d->applicant_name,
                'file_title'  => $d->file_title,
                'sources'     => implode(', ', (array) ($d->source_file_nos ?? [])),
                'status'      => $d->status,
                'stages'      => $d->stageSummary(),
                // NEW file numbers only. Counting every file row double-counts the files
                // a stage carried through unchanged — they already have a number, and
                // commissioning does not mint another for them.
                'file_count'  => $d->files()
                    ->whereNotNull('holding_no')
                    ->where('role', '!=', DuplexParcelUpdateFile::ROLE_CARRIED)
                    ->count(),
            ])->values(),
        ]);
    }

    /**
     * The whole account of a duplex: source parcels, every stage in execution order,
     * the numbers it issued, and everything it retired.
     *
     * Available at any status — before commissioning it reports holding numbers and
     * what is planned, afterwards the real file numbers — because an officer asks
     * "what is this duplex going to do" as often as "what did it do".
     */
    public function summary(int $id): JsonResponse
    {
        $duplex  = DuplexParcelUpdate::with(['stageRows.files', 'files'])->findOrFail($id);
        $service = app(DuplexSummaryService::class);

        return response()->json([
            'success' => true,
            'data'    => $service->build($duplex) + [
                'storage_summary' => $service->storageSummary($duplex),
            ],
        ]);
    }

    /** One click, one pass: every file number for the whole duplex. */
    public function commit(Request $request, int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::with(['stageRows.files', 'files'])->findOrFail($id);

        try {
            $summary = $this->committer->commit($duplex, [
                'commissioned_by' => $request->input('commissioned_by'),
                'commission_date' => $request->input('commission_date'),
                'commission_time' => $request->input('commission_time'),
                'customer_type'   => $request->input('customer_type', 'Individual'),
                'gender'          => $request->input('gender', 'Male'),
                // Per-file overrides typed on the commissioning modal, in generation
                // order. Anything left blank falls back to the duplex's own capture.
                'location_entries' => (array) $request->input('location_entries', []),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Duplex ' . $duplex->duplex_id . ' commissioned.',
                'summary' => $summary,
            ]);
        } catch (\Exception $e) {
            Log::error('Duplex commit failed', [
                'duplex_id' => $duplex->duplex_id,
                'error'     => $e->getMessage(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $duplex = DuplexParcelUpdate::findOrFail($id);

        if ($duplex->status === DuplexParcelUpdate::STATUS_COMMITTED) {
            return response()->json([
                'success' => false,
                'message' => 'A commissioned duplex cannot be deleted.',
            ], 403);
        }

        $duplex->update([
            'is_deleted' => 1,
            'deleted_by' => Auth::id(),
            'deleted_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Duplex deleted.']);
    }
}
