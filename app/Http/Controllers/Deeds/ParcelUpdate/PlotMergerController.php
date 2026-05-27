<?php

namespace App\Http\Controllers\Deeds\ParcelUpdate;

use App\Http\Controllers\Controller;
use App\Models\PlotMergerApplication;
use App\Models\PlotApplicationSize;
use App\Models\StreetName;
use App\Services\ParcelUpdateNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PlotMergerController extends Controller
{
    public function __construct(
        protected ParcelUpdateNotificationService $parcelNotifier
    ) {}

    public function index(Request $request)
    {
        $limit = max(10, min((int) $request->input('limit', 50), 200));
        $search = trim((string) $request->input('search'));

        $records = PlotMergerApplication::query()
            ->with(['plotSizes' => function($q) {
                $q->whereIn('type', ['source', 'merger_source']);
            }])
            ->where(function($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('file_no', 'LIKE', "%{$search}%")
                        ->orWhere('file_title', 'LIKE', "%{$search}%")
                        ->orWhere('temp_file_no', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($limit);

        $states      = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas        = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts   = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        $stats = [
            'total'        => PlotMergerApplication::where(function($q){ $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->count(),
            'daily'        => PlotMergerApplication::where(function($q){ $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->whereDate('created_at', today())->count(),
            'pending'      => PlotMergerApplication::where(function($q){ $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->where('status', 'pending')->count(),
            'approved'     => PlotMergerApplication::where(function($q){ $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->where('status', 'approved')->count(),
            'rejected'     => PlotMergerApplication::where(function($q){ $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })->where('status', 'rejected')->count(),
        ];

        return view('deeds.parcel_update.merger', compact(
            'records', 'limit', 'states', 'lgas', 'districts', 'streetNames', 'stats'
        ));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'temp_file_no' => 'required|string|max:100',
            'num_plots' => 'required|integer|min:2|max:50',
            'file_no' => 'required|string|max:100',
            'file_title' => 'required|string|max:500',
            'applicant_name' => 'nullable|string|max:255',
            'plot_no' => 'nullable|string|max:100',
            'house_no' => 'nullable|string|max:100',
            'street_name' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'lga' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:100',
            'plot_sizes' => 'required|array',
            'plot_sizes.*' => 'required|numeric|min:0',
            'site_plan'          => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'ownership_document' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'application_letter' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'means_of_id'        => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'tax_clearance'      => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $application = PlotMergerApplication::create([
                'temp_file_no' => $request->temp_file_no,
                'num_plots' => $request->num_plots,
                'file_no' => $request->file_no,
                'file_title' => $request->file_title,
                'applicant_name' => $request->applicant_name,
                'plot_no' => $request->plot_no,
                'house_no' => $request->house_no,
                'street_name' => $request->street_name,
                'district' => $request->district,
                'lga' => $request->lga,
                'state' => $request->state,
                'land_use' => explode('-', $request->file_no)[0],
                'status' => PlotMergerApplication::STATUS_PENDING,
                'captured_by' => Auth::id(),
                'land_value' => $request->land_value,
                'knupda_fee' => $request->knupda_fee,
            ]);

            $docUpdates = [];
            foreach (['site_plan', 'ownership_document', 'application_letter', 'means_of_id', 'tax_clearance'] as $field) {
                if ($request->hasFile($field)) {
                    $file = $request->file($field);
                    $filename = 'merger_' . $application->id . '_' . $field . '_' . time() . '.' . $file->getClientOriginalExtension();
                    $docUpdates[$field] = $file->storeAs('parcel_documents/merger', $filename, 'public');
                }
            }
            if (!empty($docUpdates)) {
                $application->update($docUpdates);
            }

            $locationDetails = [];
            if ($request->has('location_details_json')) {
                $locationDetails = json_decode($request->location_details_json, true);
            }

            foreach ($request->plot_sizes as $index => $size) {
                $idx = $index + 1;
                $sourceFileNo = (!empty($locationDetails[$idx]['source_file_no'])) 
                    ? $locationDetails[$idx]['source_file_no'] 
                    : ('Plot ' . $idx);
                $sourcePlotNo = (!empty($locationDetails[$idx]['plot_no'])) 
                    ? $locationDetails[$idx]['plot_no'] 
                    : '—';
                $sourceFileTitle = (!empty($locationDetails[$idx]['source_file_title'])) 
                    ? $locationDetails[$idx]['source_file_title'] 
                    : '—';
                
                PlotApplicationSize::create([
                    'application_id' => $application->id,
                    'application_type' => 'merger',
                    'plot_number' => $sourcePlotNo,
                    'source_file_no' => $sourceFileNo,
                    'source_file_title' => $sourceFileTitle,
                    'plot_size' => $size,
                    'type' => 'merger_source',
                ]);
            }

            DB::connection('sqlsrv')->commit();

            $this->parcelNotifier->notifyCreated(
                'merger',
                $application->id,
                $application->file_no,
                $application->file_title,
                $application->applicant_name ?? ''
            );

            return response()->json(['success' => true, 'message' => 'Merger application created successfully.']);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $record = PlotMergerApplication::with('plotSizes')
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $record]);
    }


    public function approve(int $id)
    {
        $record = PlotMergerApplication::findOrFail($id);
        $record->update([
            'status' => PlotMergerApplication::STATUS_APPROVED,
            'updated_by' => Auth::id(),
        ]);

        $approver = Auth::user();
        $approverName = $approver ? ($approver->name ?? $approver->username ?? '') : '';
        $this->parcelNotifier->notifyApproved(
            'merger',
            $record->id,
            $record->file_no,
            $record->file_title,
            $approverName
        );

        return response()->json(['success' => true, 'message' => 'Application approved.']);
    }

    public function reject(Request $request, int $id)
    {
        $record = PlotMergerApplication::findOrFail($id);
        $reason = trim((string) $request->input('reason', ''));
        $record->update([
            'status' => PlotMergerApplication::STATUS_REJECTED,
            'remarks' => $reason ? "Rejected: {$reason}" : 'Rejected',
            'updated_by' => Auth::id(),
        ]);
        return response()->json(['success' => true, 'message' => 'Application rejected.']);
    }

    public function generateApplication(int $id): JsonResponse
    {
        $record = PlotMergerApplication::findOrFail($id);
        $record->update([
            'application_generated_at' => now(),
            'updated_by' => Auth::id(),
        ]);
        return response()->json(['success' => true, 'message' => 'Application generated.']);
    }

    public function printApplication(int $id)
    {
        $record = PlotMergerApplication::with('plotSizes')->findOrFail($id);
        return view('deeds.parcel_update.print.merger_application', compact('record'));
    }

    public function generateRecommendation(int $id): JsonResponse
    {
        $record = PlotMergerApplication::where('status', PlotMergerApplication::STATUS_APPROVED)->findOrFail($id);
        $record->update([
            'recommendation_generated_at' => now(),
            'updated_by' => Auth::id(),
        ]);
        return response()->json(['success' => true, 'message' => 'Recommendation generated.']);
    }

    public function printRecommendation(int $id)
    {
        $record = PlotMergerApplication::with('plotSizes')->findOrFail($id);
        return view('deeds.parcel_update.print.merger_recommendation', compact('record'));
    }

    public function updateKnupda(Request $request, int $id): JsonResponse
    {
        $record = PlotMergerApplication::findOrFail($id);
        $knupdaStatus = $request->input('knupda_status');
        
        $updateData = [
            'knupda_fee' => $request->input('knupda_fee'),
            'knupda_status' => $knupdaStatus,
            'knupda_remarks' => $request->input('knupda_remarks'),
            'updated_by' => Auth::id(),
        ];

        // Auto-approve or Auto-reject based on KNUPDA status
        if ($knupdaStatus === 'Approved') {
            $updateData['status'] = PlotMergerApplication::STATUS_APPROVED;
        } elseif ($knupdaStatus === 'Declined') {
            $updateData['status'] = PlotMergerApplication::STATUS_REJECTED;
        }

        $record->update($updateData);
        return response()->json(['success' => true, 'message' => 'KNUPDA status updated.']);
    }

    public function approvedList(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search'));
        $records = PlotMergerApplication::where('status', PlotMergerApplication::STATUS_APPROVED)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('temp_file_no', 'LIKE', "%{$search}%")
                        ->orWhere('file_no', 'LIKE', "%{$search}%")
                        ->orWhere('applicant_name', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get([
                'id', 
                'temp_file_no', 
                'file_no', 
                'applicant_name', 
                'file_title',
                'plot_no',
                'house_no',
                'street_name',
                'district',
                'lga',
                'state'
            ]);

        // Add source file numbers to each record
        $records->transform(function($record) {
            $record->source_file_nos = DB::connection('sqlsrv')->table('plot_application_sizes')
                ->where('application_id', $record->id)
                ->where('application_type', 'merger')
                ->pluck('source_file_no')
                ->toArray();
            return $record;
        });

        return response()->json(['success' => true, 'data' => $records]);
    }

    public function findByFileNo(string $fileNo): JsonResponse
    {
        $record = PlotMergerApplication::where(function($q) use ($fileNo) {
                $q->where('file_no', $fileNo)
                  ->orWhere('temp_file_no', $fileNo);
            })
            ->where('status', PlotMergerApplication::STATUS_APPROVED)
            ->orderByDesc('created_at')
            ->first();

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'No approved merger application found for this identifier.'], 404);
        }

        $record->source_file_nos = DB::connection('sqlsrv')->table('plot_application_sizes')
            ->where('application_id', $record->id)
            ->where('application_type', 'merger')
            ->pluck('source_file_no')
            ->toArray();

        return response()->json(['success' => true, 'data' => $record]);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = PlotMergerApplication::findOrFail($id);
        
        if ($record->status === PlotMergerApplication::STATUS_APPROVED) {
            return response()->json(['success' => false, 'message' => 'Approved applications cannot be deleted.'], 403);
        }

        $record->update([
            'is_deleted' => 1,
            'deleted_by' => Auth::id(),
            'deleted_at' => now(),
        ]);
        return response()->json(['success' => true, 'message' => 'Application deleted successfully.']);
    }
}
