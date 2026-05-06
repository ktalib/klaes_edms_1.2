<?php

namespace App\Http\Controllers\LandsOneStopShop;

use App\Http\Controllers\Controller;
use App\Models\PlotExtensionApplication;
use App\Models\StreetName;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PlotExtensionController extends Controller
{
    public function index(Request $request): View
    {
        $limit = max(10, min((int) $request->input('limit', 50), 200));
        $search = trim((string) $request->input('search'));

        $records = PlotExtensionApplication::query()
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('residential_address', 'LIKE', "%{$search}%")
                        ->orWhere('correspondence_address', 'LIKE', "%{$search}%")
                        ->orWhere('occupation', 'LIKE', "%{$search}%")
                        ->orWhere('file_no', 'LIKE', "%{$search}%")
                        ->orWhere('land_use', 'LIKE', "%{$search}%")
                        ->orWhere('location', 'LIKE', "%{$search}%")
                        ->orWhere('plot_no', 'LIKE', "%{$search}%")
                        ->orWhere('plan_no', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($limit);

        $states      = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas        = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts   = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();

        return view('lands_one_stop_shop.plot_extension', compact(
            'records', 'limit', 'states', 'lgas', 'districts', 'streetNames'
        ));
    }

    public function show(int $id): JsonResponse
    {
        $record = PlotExtensionApplication::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $record,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['captured_by'] = Auth::id();
        $data['status'] = PlotExtensionApplication::STATUS_PENDING;

        $record = PlotExtensionApplication::create($data);

        if ($request->hasFile('site_plan')) {
            $file = $request->file('site_plan');
            $filename = 'extension_' . $record->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('site_plans', $filename, 'public');
            $record->update(['site_plan' => $path]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Plot extension application created successfully.',
            'data' => $record,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = PlotExtensionApplication::findOrFail($id);

        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['updated_by'] = Auth::id();

        $record->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Plot extension application updated successfully.',
            'data' => $record->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = PlotExtensionApplication::findOrFail($id);
        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Plot extension application deleted successfully.',
        ]);
    }

    public function approve(int $id): JsonResponse
    {
        $record = PlotExtensionApplication::findOrFail($id);

        $record->update([
            'status' => PlotExtensionApplication::STATUS_APPROVED,
            'updated_by' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Application approved.']);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $record = PlotExtensionApplication::findOrFail($id);

        $reason = trim((string) $request->input('reason', ''));

        $record->update([
            'status' => PlotExtensionApplication::STATUS_REJECTED,
            'remarks' => $reason ? "Rejected: {$reason}" : 'Rejected',
            'updated_by' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Application rejected.']);
    }

    public function generateApplication(int $id): JsonResponse
    {
        $record = PlotExtensionApplication::findOrFail($id);
        $record->update([
            'application_generated_at' => now(),
            'updated_by' => Auth::id(),
        ]);
        return response()->json(['success' => true, 'message' => 'Application generated.']);
    }

    public function printApplication(int $id)
    {
        $record = PlotExtensionApplication::findOrFail($id);
        return view('lands_one_stop_shop.print.extension_application', compact('record'));
    }

    public function generateRecommendation(int $id): JsonResponse
    {
        $record = PlotExtensionApplication::where('status', PlotExtensionApplication::STATUS_APPROVED)->findOrFail($id);
        $record->update([
            'recommendation_generated_at' => now(),
            'updated_by' => Auth::id(),
        ]);
        return response()->json(['success' => true, 'message' => 'Recommendation generated.']);
    }

    public function printRecommendation(int $id)
    {
        $record = PlotExtensionApplication::findOrFail($id);
        return view('lands_one_stop_shop.print.extension_recommendation', compact('record'));
    }

    public function updateKnupda(Request $request, int $id): JsonResponse
    {
        $record = PlotExtensionApplication::findOrFail($id);
        $record->update([
            'knupda_fee' => $request->input('knupda_fee'),
            'knupda_status' => $request->input('knupda_status'),
            'knupda_remarks' => $request->input('knupda_remarks'),
            'updated_by' => Auth::id(),
        ]);
        return response()->json(['success' => true, 'message' => 'KNUPDA status updated.']);
    }

    private function rules(): array
    {
        return [
            'applicant_name' => 'required|string|max:255',
            'residential_address' => 'nullable|string|max:2000',
            'correspondence_address' => 'nullable|string|max:2000',
            'nationality' => 'nullable|string|max:255',
            'occupation' => 'nullable|string|max:255',
            'file_no' => 'nullable|string|max:255',
            'land_use' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:2000',
            'plot_no' => 'nullable|string|max:100',
            'plan_no' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
            'knupda_fee' => 'nullable|numeric',
            'site_plan' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
        ];
    }
}
