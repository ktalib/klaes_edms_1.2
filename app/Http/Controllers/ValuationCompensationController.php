<?php

namespace App\Http\Controllers;

use App\Models\ValuationCompensation;
use App\Models\BuildingType;
use App\Models\PrintLog;
use App\Services\AuditService;
use App\Services\ValuationCompensationFileNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ValuationCompensationController extends Controller
{
    protected $auditService;
    protected $fileNumberService;

    public function __construct(AuditService $auditService, ValuationCompensationFileNumberService $fileNumberService)
    {
        $this->auditService = $auditService;
        $this->fileNumberService = $fileNumberService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $records = ValuationCompensation::active()
            ->with('project')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('project_id');
        
        // Statistics
        $stats = [
            'total_valuations' => ValuationCompensation::active()->count(),
            'total_amount' => ValuationCompensation::active()->sum('compensation_amount'),
            'total_buildings' => ValuationCompensation::active()->sum('building_count'),
            'today_count' => ValuationCompensation::active()->whereDate('created_at', Carbon::today())->count(),
        ];

        // Fetch Location Data for the Modal Form
        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = DB::connection('sqlsrv')
            ->table('StatLGAs')
            ->join('States', 'StatLGAs.StateID', '=', 'States.StateID')
            ->where('States.StateName', 'Kano')
            ->orderBy('LGAName')
            ->get();
        $districts = DB::connection('sqlsrv')
            ->table('districts')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $buildingTypes = BuildingType::active()->orderBy('name')->get();

        $streets = \App\Models\StreetName::orderBy('name')->get();

        $valuationItems = DB::connection('sqlsrv')->table('vfc_valuation_items')->where('is_active', 1)->get();

        $banks = \App\Models\VfcBank::orderBy('name')->get()->map(function($b) {
            return [
                'title' => $b->name,
                'route' => asset('assets/' . $b->logo),
                'code' => $b->code
            ];
        });
        $PageTitle = "Valuation for Compensation";

        return view('valuation_compensations.index', compact('records', 'stats', 'PageTitle', 'states', 'lgas', 'districts', 'buildingTypes', 'streets', 'banks', 'valuationItems'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('valuation_compensations.form');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'our_ref' => 'nullable|string',
            'your_ref' => 'nullable|string',
            'valuation_date' => 'required|date',
            'location' => 'required|string',
            'owner_name' => 'required|string',
            'plot_no' => 'nullable|string',
            'street_name' => 'nullable|string',
            'building_type' => 'required|string',
            'building_count' => 'required|integer|min:0',
            'area_covered' => 'required|numeric|min:0',
            'rate_of_cost' => 'required|numeric|min:0',
            'compensation_amount' => 'required|numeric|min:0',
            'account_name' => 'nullable|string',
            'account_number' => 'required|string',
            'bank_name' => 'nullable|string',
            'phone_number' => 'required|string',
            'remarks' => 'nullable|string',
            'compensated_items' => 'nullable|string',
            'compensated_items_other' => 'nullable|string',
            'project_id' => 'required|exists:sqlsrv.vfc_projects,id',
            'project_fileno' => 'nullable|string',
            'worker_id' => 'required|string',
        ]);

        $data['user_id'] = Auth::id();
        
        // Auto-generate file number if not provided
        if (empty($data['our_ref'])) {
            $project = \App\Models\Project::findOrFail($data['project_id']);
            $data['our_ref'] = $project->project_fileno;
        }

        $record = ValuationCompensation::create($data);

        // Audit log
        $this->auditService->logAction(
            'CREATED',
            'ValuationCompensation',
            $record->id,
            null,
            $record->toArray(),
            'Created valuation for ' . $record->owner_name
        );

        return response()->json([
            'success' => true,
            'message' => 'Valuation for compensation recorded successfully.',
            'id' => $record->id
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $record = ValuationCompensation::active()->findOrFail($id);
        return view('valuation_compensations.form', compact('record'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $record = ValuationCompensation::active()->findOrFail($id);
        $oldData = $record->toArray();

        $data = $request->validate([
            'our_ref' => 'nullable|string',
            'your_ref' => 'nullable|string',
            'valuation_date' => 'required|date',
            'location' => 'required|string',
            'owner_name' => 'required|string',
            'plot_no' => 'nullable|string',
            'street_name' => 'nullable|string',
            'building_type' => 'required|string',
            'building_count' => 'required|integer|min:0',
            'area_covered' => 'required|numeric|min:0',
            'rate_of_cost' => 'required|numeric|min:0',
            'compensation_amount' => 'required|numeric|min:0',
            'account_name' => 'nullable|string',
            'account_number' => 'required|string',
            'bank_name' => 'nullable|string',
            'phone_number' => 'required|string',
            'remarks' => 'nullable|string',
            'compensated_items' => 'nullable|string',
            'compensated_items_other' => 'nullable|string',
            'project_id' => 'required|exists:sqlsrv.vfc_projects,id',
            'project_fileno' => 'nullable|string',
            'worker_id' => 'required|string',
        ]);

        $record->update($data);

        // Audit log
        $this->auditService->logAction(
            'UPDATED',
            'ValuationCompensation',
            $record->id,
            $oldData,
            $record->toArray(),
            'Updated valuation for ' . $record->owner_name
        );

        return response()->json([
            'success' => true,
            'message' => 'Valuation for compensation updated successfully.',
            'id' => $record->id
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $record = ValuationCompensation::active()->findOrFail($id);
        return view('valuation_compensations.template', compact('record'));
    }

    /**
     * Soft delete the specified resource.
     */
    public function destroy($id)
    {
        $record = ValuationCompensation::active()->findOrFail($id);
        $oldData = $record->toArray();
        
        $record->update(['is_deleted' => 1]);

        // Audit log
        $this->auditService->logAction(
            'DELETED',
            'ValuationCompensation',
            $record->id,
            $oldData,
            ['is_deleted' => 1],
            'Soft deleted valuation for ' . $record->owner_name
        );

        return response()->json([
            'success' => true,
            'message' => 'Valuation for compensation deleted successfully.'
        ]);
    }

    /**
     * Log print action.
     */
    public function logPrint(Request $request, $id)
    {
        $record = ValuationCompensation::active()->findOrFail($id);

        DB::beginTransaction();
        try {
            PrintLog::create([
                'reference_number' => $record->our_ref ?? 'COMP-' . $record->id,
                'document_type' => 'Valuation for Compensation',
                'print_type' => 'Individual',
                'status' => 'Original',
                'user_id' => Auth::id()
            ]);

            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
