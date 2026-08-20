<?php

namespace App\Http\Controllers\Deeds\ParcelUpdate;

use App\Http\Controllers\Controller;
use App\Models\PlotSubdivisionApplication;
use App\Models\PlotApplicationSize;
use App\Models\StreetName;
use App\Services\ParcelUpdateNotificationService;
use App\Services\TitleStatusParcelRouter;
// Plot Subdivision logging goes to its own file (storage/logs/plot_subdivision.log),
// not laravel.log — see config/logging.php channel "plot_subdivision".
use App\Support\PlotSubdivisionLog as Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PlotSubdivisionController extends Controller
{
    /**
     * Files the MLS number generator will mint in one batch run
     * (MlsFileNoController::generateBatch validates batch_quantity max:200).
     * A subdivision bigger than this is commissioned in several runs.
     */
    public const BATCH_CAP = 200;

    public function __construct(
        protected ParcelUpdateNotificationService $parcelNotifier
    ) {}

    public function index(Request $request)
    {
        $limit = max(10, min((int) $request->input('limit', 50), 200));
        $search = trim((string) $request->input('search'));

        $records = PlotSubdivisionApplication::query()
            ->where(function($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            // Hide rows routed in from Title Status / File Indexing until processed.
            ->where(function($q) {
                $q->whereNull('status')->orWhere('status', '!=', TitleStatusParcelRouter::HIDDEN_STATUS);
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('file_no', 'LIKE', "%{$search}%")
                        ->orWhere('file_title', 'LIKE', "%{$search}%")
                        ->orWhere('plot_no', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($limit);

        $states      = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas        = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts   = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        $visible = fn($q) => $q->where(fn($x) => $x->whereNull('is_deleted')->orWhere('is_deleted', 0))
                               ->where(fn($x) => $x->whereNull('status')->orWhere('status', '!=', TitleStatusParcelRouter::HIDDEN_STATUS));
        $stats = [
            'total'        => PlotSubdivisionApplication::where($visible)->count(),
            'daily'        => PlotSubdivisionApplication::where($visible)->whereDate('created_at', today())->count(),
            'pending'      => PlotSubdivisionApplication::where(function($q){ $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->where('status', 'pending')->count(),
            'approved'     => PlotSubdivisionApplication::where(function($q){ $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->where('status', 'approved')->count(),
            'rejected'     => PlotSubdivisionApplication::where(function($q){ $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->where('status', 'rejected')->count(),
        ];

        return view('deeds.parcel_update.subdivision', compact(
            'records', 'limit', 'states', 'lgas', 'districts', 'streetNames', 'stats'
        ));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_no' => 'required|string|max:100',
            'file_title' => 'required|string|max:500',
            'applicant_name' => 'nullable|string|max:255',
            'num_plots' => 'required|integer|min:1|max:600',
            'plot_no' => 'nullable|string|max:100',
            'house_no' => 'nullable|string|max:100',
            'street_name' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'lga' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:100',
            'plot_sizes' => 'nullable|array',
            'plot_sizes.*' => 'nullable|numeric|min:0',
            'site_plan'          => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'ownership_document' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'application_letter' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'means_of_id'        => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'tax_clearance'      => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
        ]);

        if ($validator->fails()) {
            Log::warning('Subdivision capture rejected by validation', [
                'file_no' => $request->input('file_no'),
                'errors' => $validator->errors()->toArray(),
            ]);
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $application = PlotSubdivisionApplication::create([
                'file_no' => $request->file_no,
                'file_title' => $request->file_title,
                'applicant_name' => $request->applicant_name,
                'num_plots' => $request->num_plots,
                'plot_no' => $request->plot_no,
                'house_no' => $request->house_no,
                'street_name' => $request->street_name,
                'district' => $request->district,
                'lga' => $request->lga,
                'state' => $request->state,
                'land_use' => explode('-', $request->file_no)[0],
                'status' => PlotSubdivisionApplication::STATUS_PENDING,
                'captured_by' => Auth::id(),
                'land_value' => $request->land_value,
                'knupda_fee' => $request->knupda_fee,
            ]);

            $docUpdates = [];
            foreach (['site_plan', 'ownership_document', 'application_letter', 'means_of_id', 'tax_clearance'] as $field) {
                if ($request->hasFile($field)) {
                    $file = $request->file($field);
                    $filename = 'subdivision_' . $application->id . '_' . $field . '_' . time() . '.' . $file->getClientOriginalExtension();
                    $docUpdates[$field] = $file->storeAs('parcel_documents/subdivision', $filename, 'public');
                }
            }
            if (!empty($docUpdates)) {
                $application->update($docUpdates);
            }

            foreach (($request->plot_sizes ?? []) as $index => $size) {
                PlotApplicationSize::create([
                    'application_id' => $application->id,
                    'application_type' => 'subdivision',
                    'plot_number' => 'Plot ' . ($index + 1),
                    'plot_size' => ($size === null || $size === '') ? 0 : $size,
                    'type' => 'subdivision_fragment',
                ]);
            }

            DB::connection('sqlsrv')->commit();

            Log::info('Subdivision application captured', [
                'application_id' => $application->id,
                'file_no' => $application->file_no,
                'file_title' => $application->file_title,
                'num_plots' => (int) $application->num_plots,
                'plot_sizes' => array_values($request->plot_sizes ?? []),
                'documents' => array_keys($docUpdates),
            ]);

            $this->parcelNotifier->notifyCreated(
                'subdivision',
                $application->id,
                $application->file_no,
                $application->file_title,
                $application->applicant_name ?? ''
            );

            return response()->json(['success' => true, 'message' => 'Subdivision application created successfully.']);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('Subdivision capture failed, transaction rolled back', [
                'file_no' => $request->input('file_no'),
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $record = PlotSubdivisionApplication::with('plotSizes')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => array_merge($record->toArray(), [
                'commissioned_count' => $record->commissionedCount(),
                'remaining_plots'    => $record->remainingPlots(),
                'batches_done'       => count($record->commissionedBatches()),
                'batch_cap'          => self::BATCH_CAP,
            ]),
        ]);
    }


    public function approve(int $id)
    {
        $record = PlotSubdivisionApplication::findOrFail($id);
        $previousStatus = $record->status;

        // Never walk a commissioned application back to 'approved' — its fragments
        // already exist and the mother file is decommissioned. A partially commissioned
        // application legitimately sits at 'approved' (see recordCommissionedBatch), so
        // it is only the finished ones and any already-minted work that are protected.
        if ($previousStatus === PlotSubdivisionApplication::STATUS_COMMISSIONED || $record->commissionedCount() > 0) {
            return response()->json([
                'success' => false,
                'message' => "This application already has {$record->commissionedCount()} of {$record->num_plots} plots commissioned.",
            ], 422);
        }
        $record->update([
            'status' => PlotSubdivisionApplication::STATUS_APPROVED,
            'updated_by' => Auth::id(),
        ]);

        Log::info('Subdivision application approved', [
            'application_id' => $record->id,
            'file_no' => $record->file_no,
            'num_plots' => (int) $record->num_plots,
            'previous_status' => $previousStatus,
        ]);

        $approver = Auth::user();
        $approverName = $approver ? ($approver->name ?? $approver->username ?? '') : '';
        $this->parcelNotifier->notifyApproved(
            'subdivision',
            $record->id,
            $record->file_no,
            $record->file_title,
            $approverName
        );

        return response()->json(['success' => true, 'message' => 'Application approved.']);
    }

    public function reject(Request $request, int $id)
    {
        $record = PlotSubdivisionApplication::findOrFail($id);
        $reason = trim((string) $request->input('reason', ''));
        $previousStatus = $record->status;
        $record->update([
            'status' => PlotSubdivisionApplication::STATUS_REJECTED,
            'remarks' => $reason ? "Rejected: {$reason}" : 'Rejected',
            'updated_by' => Auth::id(),
        ]);

        Log::info('Subdivision application rejected', [
            'application_id' => $record->id,
            'file_no' => $record->file_no,
            'previous_status' => $previousStatus,
            'reason' => $reason ?: null,
        ]);

        return response()->json(['success' => true, 'message' => 'Application rejected.']);
    }

    public function generateApplication(int $id): JsonResponse
    {
        $record = PlotSubdivisionApplication::findOrFail($id);
        $record->update([
            'application_generated_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        Log::info('Subdivision application document generated', [
            'application_id' => $record->id,
            'file_no' => $record->file_no,
        ]);

        return response()->json(['success' => true, 'message' => 'Application generated.']);
    }

    public function printApplication(int $id)
    {
        $record = PlotSubdivisionApplication::with('plotSizes')->findOrFail($id);
        return view('deeds.parcel_update.print.subdivision_application', compact('record'));
    }

    public function generateRecommendation(int $id): JsonResponse
    {
        $record = PlotSubdivisionApplication::where('status', PlotSubdivisionApplication::STATUS_APPROVED)->findOrFail($id);
        $record->update([
            'recommendation_generated_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        Log::info('Subdivision recommendation generated', [
            'application_id' => $record->id,
            'file_no' => $record->file_no,
        ]);

        return response()->json(['success' => true, 'message' => 'Recommendation generated.']);
    }

    public function printRecommendation(int $id)
    {
        $record = PlotSubdivisionApplication::with('plotSizes')->findOrFail($id);
        return view('deeds.parcel_update.print.subdivision_recommendation', compact('record'));
    }

    public function updateKnupda(Request $request, int $id): JsonResponse
    {
        $record = PlotSubdivisionApplication::findOrFail($id);
        $knupdaStatus = $request->input('knupda_status');
        
        $updateData = [
            'land_value' => $request->input('land_value'),
            'knupda_fee' => $request->input('knupda_fee'),
            'knupda_status' => $knupdaStatus,
            'knupda_remarks' => $request->input('knupda_remarks'),
            'updated_by' => Auth::id(),
        ];

        // Auto-approve or Auto-reject based on KNUPDA status
        if ($knupdaStatus === 'Approved') {
            $updateData['status'] = PlotSubdivisionApplication::STATUS_APPROVED;
        } elseif ($knupdaStatus === 'Declined') {
            $updateData['status'] = PlotSubdivisionApplication::STATUS_REJECTED;
        }

        $record->update($updateData);

        Log::info('Subdivision KNUPDA status updated', [
            'application_id' => $record->id,
            'file_no' => $record->file_no,
            'knupda_status' => $knupdaStatus,
            'land_value' => $request->input('land_value'),
            'knupda_fee' => $request->input('knupda_fee'),
            // KNUPDA Approved/Declined silently flips the application status too.
            'auto_status' => $updateData['status'] ?? null,
        ]);

        return response()->json(['success' => true, 'message' => 'KNUPDA status updated.']);
    }

    public function findByFileNo(string $fileNo): JsonResponse
    {
        $record = PlotSubdivisionApplication::where('file_no', $fileNo)
            ->where('status', PlotSubdivisionApplication::STATUS_APPROVED)
            ->orderByDesc('created_at')
            ->first();

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'No approved subdivision application found for this file number.'], 404);
        }

        // The generator's batch mode tops out at self::BATCH_CAP files per run, so a
        // subdivision larger than that is commissioned across several runs. Tell the
        // caller what is left and how big the next chunk may be; the application stays
        // 'approved' (and so keeps being found here) until the last plot is minted.
        $remaining = $record->remainingPlots();

        return response()->json([
            'success' => true,
            'data'    => array_merge($record->toArray(), [
                'planned_plots'      => (int) $record->num_plots,
                'commissioned_count' => $record->commissionedCount(),
                'remaining_plots'    => $remaining,
                'batch_cap'          => self::BATCH_CAP,
                'next_batch_size'    => min($remaining ?: (int) $record->num_plots, self::BATCH_CAP),
                'batches_done'       => count($record->commissionedBatches()),
            ]),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = PlotSubdivisionApplication::findOrFail($id);
        
        if ($record->status === PlotSubdivisionApplication::STATUS_APPROVED) {
            Log::warning('Blocked delete of approved subdivision application', [
                'application_id' => $record->id,
                'file_no' => $record->file_no,
            ]);
            return response()->json(['success' => false, 'message' => 'Approved applications cannot be deleted.'], 403);
        }

        $record->update([
            'is_deleted' => 1,
            'deleted_by' => Auth::id(),
            'deleted_at' => now(),
        ]);

        Log::info('Subdivision application soft-deleted', [
            'application_id' => $record->id,
            'file_no' => $record->file_no,
            'status_at_delete' => $record->status,
        ]);

        return response()->json(['success' => true, 'message' => 'Application deleted successfully.']);
    }
}
