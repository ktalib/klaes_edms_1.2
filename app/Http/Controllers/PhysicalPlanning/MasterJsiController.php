<?php

namespace App\Http\Controllers\PhysicalPlanning;

use App\Http\Controllers\Controller;
use App\Models\MasterJsiPortion;
use App\Models\MasterJsiReport;
use App\Services\ParcelUpdateNotificationService;
use App\Support\MasterJsiPortionTemplates;
use App\Support\MasterJsiSubjectResolver;
use App\Support\ParcelSizeSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * The Master JSI register — Physical Planning site observations for parcel updates.
 *
 * One page and one form for both categories (SPU, the five single workflows; APU,
 * the duplex) and all five sheets. The layout is chosen by parcel_update_type from
 * MasterJsiPortionTemplates, so nothing here branches per sheet.
 *
 * The report walks draft -> generated -> submitted -> approved, and an approved
 * report is the planning clearance the parcel update needs — the question is asked
 * through MasterJsiGate, never by reading status from a listing.
 *
 * Sending to Deeds is a stamp and a notification, nothing else. It follows
 * DuplexParcelUpdateController::sendToLand() deliberately: no registry writes
 * happen on a handover.
 */
class MasterJsiController extends Controller
{
    public function __construct(private ParcelUpdateNotificationService $parcelNotifier)
    {
    }

    /** The register. */
    public function index(Request $request): View
    {
        $query = MasterJsiReport::visible();

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($type = $request->query('type')) {
            $query->where('parcel_update_type', $type);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('jsi_ref', 'LIKE', "%{$search}%")
                    ->orWhere('file_number', 'LIKE', "%{$search}%")
                    ->orWhere('applicant_name', 'LIKE', "%{$search}%")
                    ->orWhere('file_title', 'LIKE', "%{$search}%");
            });
        }

        $records = $query->orderByDesc('id')->paginate(25)->withQueryString();

        // Counted off the whole register, not the filtered page: the tiles say what
        // the office is holding, and a tile that moved because someone typed in the
        // search box would be read as work appearing and disappearing.
        $counts = MasterJsiReport::visible()
            ->select('status', DB::raw('COUNT(*) AS total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('physical_planning.master_jsi.index', [
            'records' => $records,
            'stats'   => [
                'total'     => (int) $counts->sum(),
                'draft'     => (int) $counts->get(MasterJsiReport::STATUS_DRAFT, 0),
                'generated' => (int) $counts->get(MasterJsiReport::STATUS_GENERATED, 0),
                'submitted' => (int) $counts->get(MasterJsiReport::STATUS_SUBMITTED, 0),
                'approved'  => (int) $counts->get(MasterJsiReport::STATUS_APPROVED, 0),
                'to_deeds'  => (int) MasterJsiReport::visible()->whereNotNull('sent_to_deeds_at')->count(),
            ],
            'filters'         => $request->only(['category', 'type', 'status', 'search']),
            'PageTitle'       => 'Master JSI',
            'PageDescription' => 'Physical Planning site observations for SPU and APU parcel updates.',
        ]);
    }

    /**
     * The capture form.
     *
     * Opens blank from the register, or pre-filled when a parcel-update listing sent
     * the officer here through its action menu (?subject_type=&subject_id=).
     */
    public function create(Request $request): View
    {
        $subjectType = $request->query('subject_type');
        $subjectId   = $request->query('subject_id');

        $record = MasterJsiSubjectResolver::find($subjectType, $subjectId);

        // A parcel update already knows which kind of update it is; only a report
        // started from the register asks.
        $type = $request->query('type')
            ?: ($subjectType && $subjectType !== 'duplex' ? $subjectType : null);

        return view('physical_planning.master_jsi.form', [
            'report'       => null,
            'subjectType'  => $subjectType,
            'subjectId'    => $subjectId ? (int) $subjectId : null,
            'category'     => $request->query('category')
                ?: ($subjectType ? MasterJsiSubjectResolver::categoryFor($subjectType) : null),
            'parcelUpdateType' => $type,
            'prefill'      => MasterJsiSubjectResolver::prefill($subjectType, $record),
            'returnUrl'    => $request->query('return') ?: route('master-jsi.index'),
            'PageTitle'    => 'New Master JSI',
        ]);
    }

    public function edit(int $id, Request $request): View
    {
        $report = MasterJsiReport::visible()->with('portions')->findOrFail($id);

        return view('physical_planning.master_jsi.form', [
            'report'           => $report,
            'subjectType'      => $report->subject_type,
            'subjectId'        => $report->subject_id ? (int) $report->subject_id : null,
            'category'         => $report->category,
            'parcelUpdateType' => $report->parcel_update_type,
            'prefill'          => [],
            'returnUrl'        => $request->query('return') ?: route('master-jsi.index'),
            'PageTitle'        => 'Master JSI ' . $report->jsi_ref,
        ]);
    }

    public function show(int $id): View
    {
        $report = MasterJsiReport::visible()->with('portions')->findOrFail($id);

        return view('physical_planning.master_jsi.show', [
            'report'    => $report,
            'subject'   => MasterJsiSubjectResolver::find($report->subject_type, $report->subject_id),
            'PageTitle' => 'Master JSI ' . $report->jsi_ref,
        ]);
    }

    /** Create or update — the form posts the whole sheet either way. */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $report = DB::connection('sqlsrv')->transaction(function () use ($data, $request) {
            $report = MasterJsiReport::create(array_merge($this->reportAttributes($data), [
                'jsi_ref'    => MasterJsiReport::allocateRef(),
                'status'     => MasterJsiReport::STATUS_DRAFT,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]));

            $this->syncPortions($report, $request->input('portions', []));

            return $report;
        });

        Log::info('Master JSI captured', [
            'jsi_ref'      => $report->jsi_ref,
            'category'     => $report->category,
            'type'         => $report->parcel_update_type,
            'subject_type' => $report->subject_type,
            'subject_id'   => $report->subject_id,
            'user_id'      => Auth::id(),
        ]);

        return response()->json([
            'success'  => true,
            'message'  => 'Master JSI ' . $report->jsi_ref . ' saved.',
            'id'       => $report->id,
            'jsi_ref'  => $report->jsi_ref,
            'redirect' => route('master-jsi.show', $report->id),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $report = MasterJsiReport::visible()->findOrFail($id);

        // An approved report is the clearance the recommendation was drawn on, so
        // it is not edited underneath that — it is rejected and captured again.
        if ($report->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'An approved Master JSI cannot be edited. Reject it first if the observation was wrong.',
            ], 422);
        }

        $data = $this->validated($request);

        DB::connection('sqlsrv')->transaction(function () use ($report, $data, $request) {
            $report->update(array_merge($this->reportAttributes($data), [
                'updated_by' => Auth::id(),
            ]));

            $this->syncPortions($report, $request->input('portions', []));
        });

        return response()->json([
            'success'  => true,
            'message'  => 'Master JSI ' . $report->jsi_ref . ' updated.',
            'id'       => $report->id,
            'redirect' => route('master-jsi.show', $report->id),
        ]);
    }

    /** The sheet is drawn — from here it can be printed and sent up. */
    public function generate(int $id): JsonResponse
    {
        $report = MasterJsiReport::visible()->with('portions')->findOrFail($id);

        if ($report->portions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Record the measurements before generating the sheet.',
            ], 422);
        }

        $report->update([
            'status'       => MasterJsiReport::STATUS_GENERATED,
            'generated_at' => now(),
            'generated_by' => Auth::id(),
            'updated_by'   => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Master JSI generated.']);
    }

    public function submit(int $id): JsonResponse
    {
        $report = MasterJsiReport::visible()->findOrFail($id);

        if (!$report->generated_at) {
            return response()->json([
                'success' => false,
                'message' => 'Generate the sheet before submitting it for approval.',
            ], 422);
        }

        $report->update([
            'status'       => MasterJsiReport::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'submitted_by' => Auth::id(),
            'updated_by'   => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Master JSI submitted for approval.']);
    }

    /**
     * The approval that opens the parcel update's recommendation.
     *
     * This is the answer MasterJsiGate reads, so it is the moment Generate
     * Recommendation and Approve unlock on the parcel-update listing.
     */
    public function approve(int $id): JsonResponse
    {
        $report = MasterJsiReport::visible()->findOrFail($id);

        if (!$report->submitted_at) {
            return response()->json([
                'success' => false,
                'message' => 'Submit the Master JSI before approving it.',
            ], 422);
        }

        $report->update([
            'status'          => MasterJsiReport::STATUS_APPROVED,
            'approved_at'     => now(),
            'approved_by'     => Auth::id(),
            'rejected_reason' => null,
            'updated_by'      => Auth::id(),
        ]);

        Log::info('Master JSI approved', [
            'jsi_ref'      => $report->jsi_ref,
            'subject_type' => $report->subject_type,
            'subject_id'   => $report->subject_id,
            'user_id'      => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Master JSI approved. The parcel update can now be recommended.',
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $report = MasterJsiReport::visible()->findOrFail($id);
        $reason = trim((string) $request->input('reason', ''));

        $report->update([
            'status'          => MasterJsiReport::STATUS_REJECTED,
            'rejected_reason' => $reason ?: 'Rejected',
            'updated_by'      => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Master JSI rejected.']);
    }

    /**
     * Hand the approved inspection to Deeds. A stamp and a notification — no
     * registry writes, exactly as the duplex hands itself to Land.
     */
    public function sendToDeeds(int $id): JsonResponse
    {
        $report = MasterJsiReport::visible()->findOrFail($id);

        if (!$report->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Only an approved Master JSI can be sent to Deeds.',
            ], 422);
        }

        if ($report->sentToDeeds()) {
            return response()->json([
                'success' => false,
                'message' => 'This Master JSI was already sent to Deeds on '
                    . $report->sent_to_deeds_at->format('d M Y') . '.',
            ], 422);
        }

        $report->update([
            'sent_to_deeds_at' => now(),
            'sent_to_deeds_by' => Auth::id(),
            'updated_by'       => Auth::id(),
        ]);

        $sender = Auth::user();

        $this->parcelNotifier->notifyJsiSentToDeeds(
            (string) $report->parcel_update_type,
            (int) $report->id,
            (string) $report->jsi_ref,
            (string) $report->file_number,
            (string) $report->file_title,
            $sender ? ($sender->name ?? $sender->username ?? '') : ''
        );

        Log::info('Master JSI sent to Deeds', [
            'jsi_ref' => $report->jsi_ref,
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Master JSI ' . $report->jsi_ref . ' sent to Deeds.',
        ]);
    }

    /** The printable sheet. */
    public function print(int $id): View
    {
        $report = MasterJsiReport::visible()->with('portions')->findOrFail($id);

        return view('physical_planning.master_jsi.print.report', [
            'report'   => $report,
            'template' => MasterJsiPortionTemplates::for($report->parcel_update_type),
        ]);
    }

    /** Soft delete, matching the flag every parcel-update table carries. */
    public function destroy(int $id): JsonResponse
    {
        $report = MasterJsiReport::visible()->findOrFail($id);

        if ($report->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'An approved Master JSI cannot be deleted.',
            ], 422);
        }

        $report->update([
            'is_deleted' => true,
            'deleted_by' => Auth::id(),
            'deleted_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Master JSI deleted.']);
    }

    /**
     * Which parcel-update application a chosen file number belongs to.
     *
     * The form asks for a FILE NUMBER through the global selector — that is what the
     * officer is holding — and this resolves it back to the application so the
     * clearance has something to attach to. Not finding one is a normal answer, not
     * an error: an inspection may be carried out before the application is captured,
     * and the form says so rather than refusing the file number.
     */
    public function resolveSubject(Request $request): JsonResponse
    {
        $fileNumber  = (string) $request->query('file_number');
        $subjectType = (string) $request->query('subject_type');

        if (MasterJsiSubjectResolver::modelClass($subjectType) === null) {
            return response()->json([
                'success' => false,
                'message' => 'Choose the parcel update type first.',
            ], 422);
        }

        $record = MasterJsiSubjectResolver::findByFileNumber($subjectType, $fileNumber);

        if ($record === null) {
            return response()->json([
                'success' => true,
                'found'   => false,
                'message' => 'No ' . (MasterJsiReport::SUBJECTS[$subjectType][1] ?? $subjectType)
                    . ' application found for ' . $fileNumber
                    . '. The inspection will be captured unlinked.',
            ]);
        }

        return response()->json([
            'success'      => true,
            'found'        => true,
            'subject_type' => $subjectType,
            'subject_id'   => $record->id,
            'label'        => MasterJsiSubjectResolver::label($subjectType, $record),
            'prefill'      => MasterJsiSubjectResolver::prefill($subjectType, $record),
            'message'      => 'Linked to ' . (MasterJsiReport::SUBJECTS[$subjectType][1] ?? $subjectType)
                . ' — ' . MasterJsiSubjectResolver::label($subjectType, $record),
        ]);
    }

    /** The register search, kept for callers that pick a record rather than a file. */
    public function searchSubjects(Request $request): JsonResponse
    {
        $subjectType = (string) $request->query('subject_type');

        if (MasterJsiSubjectResolver::modelClass($subjectType) === null) {
            return response()->json(['success' => false, 'results' => []], 422);
        }

        return response()->json([
            'success' => true,
            'results' => MasterJsiSubjectResolver::search($subjectType, $request->query('q')),
        ]);
    }

    // -------------------------------------------------------------------------

    private function validated(Request $request): array
    {
        return $request->validate([
            'category'           => 'required|in:SPU,APU',
            'parcel_update_type' => 'required|in:' . implode(',', MasterJsiPortionTemplates::types()),
            'subject_type'       => 'nullable|in:' . implode(',', array_keys(MasterJsiSubjectResolver::MODELS)),
            'subject_id'         => 'nullable|integer',
            'duplex_stage_id'    => 'nullable|integer',

            // Required: it is what the sheet is filed against and what resolves the
            // parcel update. subject_id may still be empty — a site can be inspected
            // before the application is captured — but the file number cannot.
            'file_number'    => 'required|string|max:100',
            'file_title'     => 'nullable|string|max:500',
            'applicant_name' => 'nullable|string|max:255',
            'inspection_date' => 'nullable|date',
            'location'       => 'nullable|string|max:500',
            'plot_number'    => 'nullable|string|max:100',
            'district'       => 'nullable|string|max:255',
            'lga'            => 'nullable|string|max:255',
            'inspection_officer'    => 'nullable|string|max:255',
            'inspection_officer_id' => 'nullable|integer',

            'available_on_ground'  => 'nullable|string|max:50',
            'boundary_segments'    => 'nullable|array',
            'boundary_description' => 'nullable|string',
            'road_reservation'     => 'nullable|string|max:700',
            'conformity'           => 'nullable|boolean',
            'prevailing_land_use'  => 'nullable|string|max:255',
            'existing_land_use'    => 'nullable|string|max:255',
            'recommended_land_use' => 'nullable|string|max:255',
            'existing_purpose'     => 'nullable|string|max:255',
            'recommended_purpose'  => 'nullable|string|max:255',
            'number_of_units'      => 'nullable|integer|min:0',
            'average_size'         => 'nullable|string|max:100',
            'narrative_summary'    => 'nullable|string',
            'additional_observations' => 'nullable|string',

            'portions'                => 'nullable|array|max:60',
            'portions.*.role'         => 'nullable|string|max:40',
            'portions.*.label'        => 'nullable|string|max:255',
            'portions.*.land_use'     => 'nullable|string|max:255',
            'portions.*.unit_count'   => 'nullable|integer|min:0',
            'portions.*.area_sqm'     => 'nullable|numeric|min:0',
            'portions.*.dimensions'   => 'nullable|array|max:24',
            'portions.*.dimensions.*' => 'nullable|numeric|min:0',
        ]);
    }

    /** The report columns, with the two derived ones resolved. */
    private function reportAttributes(array $data): array
    {
        $attributes = collect($data)->except(['portions', 'boundary_segments'])->all();

        // The four direction boxes are the source; boundary_description is compiled
        // from them by the shared trait so it reads the same as the OSS JSI sheet.
        if (array_key_exists('boundary_segments', $data)) {
            $attributes['boundary_description'] = MasterJsiReport::compileBoundaryDescription(
                $data['boundary_segments'],
                $data['boundary_description'] ?? null
            );
        }

        return $attributes;
    }

    /**
     * Replace the sheet's rows with what was submitted.
     *
     * A whole-table replace rather than a diff: the rows are a single observation
     * taken on one visit, they are few, and matching them up by id would let a
     * renamed row keep an area that was measured against a different portion.
     */
    private function syncPortions(MasterJsiReport $report, $portions): void
    {
        MasterJsiPortion::where('master_jsi_report_id', $report->id)->delete();

        $sequence = 0;

        foreach ((array) $portions as $portion) {
            $dimensions = ParcelSizeSummary::dimensionsText($portion['dimensions'] ?? null);
            $area       = $portion['area_sqm'] ?? null;
            $landUse    = $portion['land_use'] ?? null;
            $count      = $portion['unit_count'] ?? null;

            // The form derives the area on screen and posts it, but the sides are
            // what was measured — so if an area did not arrive, derive it here by
            // the same rule rather than storing a row with a blank measurement.
            if (($area === null || $area === '') && !empty($portion['dimensions'])) {
                $area = ParcelSizeSummary::areaFromSides($portion['dimensions']);
            }

            // A row with nothing measured on it is not an observation — the sheet
            // prints the rows that were filled in, not the empty template.
            if ($dimensions === null && ($area === null || $area === '')
                && ($landUse === null || $landUse === '')
                && ($count === null || $count === '')) {
                continue;
            }

            $sequence++;

            MasterJsiPortion::create([
                'master_jsi_report_id' => $report->id,
                'sn'                   => $sequence,
                'role'                 => $portion['role'] ?? null,
                'label'                => $portion['label'] ?? null,
                'dimensions'           => $dimensions,
                'area_sqm'             => ($area === null || $area === '') ? null : $area,
                'land_use'             => $landUse ?: null,
                'unit_count'           => ($count === null || $count === '') ? null : $count,
                'sequence'             => $sequence,
            ]);
        }
    }
}
