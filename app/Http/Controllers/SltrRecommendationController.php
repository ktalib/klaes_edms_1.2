<?php

namespace App\Http\Controllers;

use App\Models\LandUse;
use App\Models\Purpose;
use App\Models\PrintLog;
use App\Models\SltrRecommendation;
use App\Models\StreetName;
use App\Services\Pra\RofoPraSyncer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Concerns\ExecutesMasterDelete;
use App\Services\RofoRecommendationPurgeService;
use Illuminate\Support\Facades\Log;

class SltrRecommendationController extends Controller
{
    use ExecutesMasterDelete;

    public function index(Request $request)
    {
        $query = SltrRecommendation::with('creator');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('sltr_number', 'LIKE', "%{$s}%")
                  ->orWhere('applicant_name', 'LIKE', "%{$s}%")
                  ->orWhere('location', 'LIKE', "%{$s}%")
                  ->orWhere('lga', 'LIKE', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $recommendations = $query->latest()->paginate(20);

        $stats = [
            'total'    => SltrRecommendation::count(),
            'pending'  => SltrRecommendation::where('status', SltrRecommendation::STATUS_PENDING)->count(),
            'approved' => SltrRecommendation::where('status', SltrRecommendation::STATUS_APPROVED)->count(),
            'rofo_generated' => SltrRecommendation::where('rofo_status', SltrRecommendation::ROFO_GENERATED)->count(),
        ];

        $PageTitle   = 'SLTR Recommendations';
        $states      = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas        = DB::connection('sqlsrv')->table('StatLGAs')
                         ->join('States', 'StatLGAs.StateID', '=', 'States.StateID')
                         ->where('States.StateName', 'Kano')
                         ->orderBy('LGAName')->get();
        $districts   = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();
        $streetOptions = $streetNames->merge([(object)['name' => 'Other']]);
        $landUseOptions = LandUse::orderBy('landuse')->get();
        $purposeOptions = Purpose::orderBy('name')->get();

        $canApprove = $this->userCanApprove();

        // Which rows have had their proof run off: the official print opens on the
        // strength of it, and the White Copy closes with it.
        $whiteCopyDone = array_flip(PrintLog::whiteCopyPrinted(
            'SLTR Recommendation',
            $recommendations->getCollection()->pluck('sltr_number')->filter()->all()
        ));

        return view('sltr_recommendations.index', compact(
            'recommendations', 'stats', 'PageTitle',
            'states', 'lgas', 'districts', 'streetOptions',
            'landUseOptions', 'purposeOptions', 'canApprove', 'whiteCopyDone'
        ));
    }

    /**
     * Only a Supper Admin (assign_role) or a user whose rank is "Director SLTR"
     * may approve SLTR recommendations.
     */
    private function userCanApprove(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        $roleNames = method_exists($user, 'assignedRoleNames') ? $user->assignedRoleNames() : [];
        if (\in_array('supper admin', $roleNames, true)) {
            return true;
        }

        return strcasecmp(trim((string) ($user->rank ?? '')), 'Director SLTR') === 0;
    }

    public function checkFileNumber(Request $request)
    {
        $fileNumber = $request->input('file_number');
        $excludeId  = $request->input('exclude_id');

        if (!$fileNumber) {
            return response()->json(['exists' => false]);
        }

        $query = SltrRecommendation::where('sltr_number', $fileNumber);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $existing = $query->select('id', 'applicant_name', 'sltr_number', 'status')->first();

        return response()->json([
            'exists' => (bool) $existing,
            'record' => $existing,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sltr_number'      => ['nullable', 'string', 'max:100', Rule::unique('sqlsrv.sltr_recommendations', 'sltr_number')->whereNull('deleted_at')],
            'applicant_name'   => 'required|string|max:300',
            'applicant_address'=> 'nullable|string|max:500',
            'application_date' => 'nullable|date',
            'location'         => 'nullable|string|max:300',
            'lga'              => 'nullable|string|max:200',
            'land_use'         => 'nullable|string|max:200',
            'plot_number'      => 'nullable|string|max:100',
            'page_application' => 'nullable|integer|min:1',
            'page_survey'      => 'nullable|integer|min:1',
            'page_planning'    => 'nullable|integer|min:1',
            'term'             => 'nullable|integer|min:1',
            'ground_rent'      => 'nullable|numeric|min:0',
            'processing_fee'   => 'nullable|numeric|min:0',
            'purpose_of_clause'=> 'nullable|string|max:300',
            'notes'            => 'nullable|string',
        ]);

        $rec = SltrRecommendation::create(array_merge($validated, [
            'status'     => SltrRecommendation::STATUS_PENDING,
            'rofo_status'=> SltrRecommendation::ROFO_PENDING,
            'created_by' => Auth::id(),
        ]));

        return response()->json(['success' => true, 'message' => 'Recommendation created.', 'data' => $rec]);
    }

    public function update(Request $request, $id)
    {
        $rec = SltrRecommendation::findOrFail($id);

        $validated = $request->validate([
            'sltr_number'      => ['nullable', 'string', 'max:100', Rule::unique('sqlsrv.sltr_recommendations', 'sltr_number')->ignore($rec->id)->whereNull('deleted_at')],
            'applicant_name'   => 'required|string|max:300',
            'applicant_address'=> 'nullable|string|max:500',
            'application_date' => 'nullable|date',
            'location'         => 'nullable|string|max:300',
            'lga'              => 'nullable|string|max:200',
            'land_use'         => 'nullable|string|max:200',
            'plot_number'      => 'nullable|string|max:100',
            'page_application' => 'nullable|integer|min:1',
            'page_survey'      => 'nullable|integer|min:1',
            'page_planning'    => 'nullable|integer|min:1',
            'term'             => 'nullable|integer|min:1',
            'ground_rent'      => 'nullable|numeric|min:0',
            'processing_fee'   => 'nullable|numeric|min:0',
            'purpose_of_clause'=> 'nullable|string|max:300',
            'notes'            => 'nullable|string',
        ]);

        $rec->update(array_merge($validated, ['updated_by' => Auth::id()]));

        return response()->json(['success' => true, 'message' => 'Recommendation updated.', 'data' => $rec]);
    }

    public function destroy($id)
    {
        $rec = SltrRecommendation::findOrFail($id);
        $rec->delete();

        return response()->json(['success' => true, 'message' => 'Recommendation deleted.']);
    }

    public function approve(Request $request, $id)
    {
        if (!$this->userCanApprove()) {
            return response()->json(['success' => false, 'message' => 'You are not authorized to approve recommendations.'], 403);
        }

        $rec = SltrRecommendation::findOrFail($id);

        if ($rec->status === SltrRecommendation::STATUS_APPROVED) {
            return response()->json(['success' => false, 'message' => 'Already approved.'], 422);
        }

        $rec->update([
            'status'           => SltrRecommendation::STATUS_APPROVED,
            'approved_at'      => now(),
            'approved_by'      => Auth::id(),
            'rofo_status'      => SltrRecommendation::ROFO_GENERATED,
            'rofo_generated_at'=> now(),
            'updated_by'       => Auth::id(),
        ]);

        app(RofoPraSyncer::class)->syncSltr($rec->fresh());

        return response()->json(['success' => true, 'message' => 'Recommendation approved.']);
    }

    public function show($id)
    {
        $rec = SltrRecommendation::with('creator', 'approver')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $rec]);
    }

    /**
     * The White Copy: a black & white proof of the SLTR recommendation, read against
     * the record before an official copy is run off.
     *
     * The same record through the same template with every mark of an issued
     * document taken off — the serial, the signature blocks, the acknowledgement
     * sheet — and marked WHITE COPY instead.
     *
     * Crucially it does NOT stamp printed_at. That column is what switches this
     * record's action from "Print" to "View Recommendation", so a proof that set it
     * would close the official print behind a document nobody has issued. No serial
     * is minted either — this template mints it as it renders, so a preview alone
     * would have spent one.
     */
    public function printWhiteCopy($id)
    {
        $recommendation = SltrRecommendation::findOrFail($id);
        $isWhiteCopy = true;

        PrintLog::logWhiteCopy('SLTR Recommendation', $recommendation->sltr_number, Auth::id());

        return view('sltr_recommendations.templates.recommendation_print', compact('recommendation', 'isWhiteCopy'));
    }

    public function printRecommendation($id)
    {
        $recommendation = SltrRecommendation::findOrFail($id);

        // Mark the recommendation as printed the first time its print view is opened
        // so the listing can switch the "Print" action to "View Recommendation".
        if (!$recommendation->printed_at) {
            $recommendation->update(['printed_at' => now()]);
        }

        return view('sltr_recommendations.templates.recommendation_print', compact('recommendation'));
    }

    /**
     * MASTER DELETE — erase an SLTR recommendation from every table it reached.
     *
     * Destructive and irreversible. On SLTR the recommendation record IS the RofO
     * record, so this takes both: the row, its PRA transaction, its security paper
     * (back to the pool, or retired if the sheet has been printed), its
     * `security_codes` tracking row and its print history.
     *
     * To undo only the issuance and keep the approved recommendation, use the
     * Master Delete on the SLTR RofO screen instead.
     */
    public function masterDestroy(Request $request, $id)
    {
        if ($deny = $this->denyUnlessMasterDeleter()) {
            return $deny;
        }

        $rec = SltrRecommendation::find($id);
        if (!$rec) {
            return response()->json(['success' => false, 'message' => 'Recommendation not found.'], 404);
        }

        if ($deny = $this->denyUnlessConfirmationMatches($request, $rec->sltr_number)) {
            return $deny;
        }

        $snapshot = $rec->toArray();

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $counts = app(RofoRecommendationPurgeService::class)->purgeSltrRecommendation($rec);

            DB::connection('sqlsrv')->commit();

            $this->logMasterDelete(
                'sltr_recommendation',
                $rec->id,
                $snapshot,
                $counts,
                'SLTR Recommendation ' . $rec->sltr_number
            );

            return response()->json([
                'success' => true,
                'message' => 'Recommendation ' . $rec->sltr_number . ' deleted from all tables.',
                'details' => $counts,
            ]);
        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('SLTR recommendation master delete failed', [
                'id'    => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting recommendation: ' . $e->getMessage(),
            ], 500);
        }
    }
}
