<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectWorker;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ValuationCompensationFileNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    protected $auditService;
    protected $fileNumberService;

    public function __construct(AuditService $auditService, ValuationCompensationFileNumberService $fileNumberService)
    {
        $this->auditService = $auditService;
        $this->fileNumberService = $fileNumberService;
    }

    public function index()
    {
        $projects = Project::with('workers.user')->orderBy('created_at', 'desc')->get();
        
        // Fetch all users who are not MDCM
        $workers = User::where('staff_type_category', '!=', 'MDCM')
            ->orderBy('first_name')
            ->get();

        // Location data (reuse existing logic if possible, or fetch simple Kano lists)
        $lgas = DB::connection('sqlsrv')->table('StatLGAs')->join('States', 'StatLGAs.StateID', '=', 'States.StateID')->where('States.StateName', 'Kano')->orderBy('LGAName')->get();
        $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streets = \App\Models\StreetName::orderBy('name')->get();

        return view('valuation_compensations.projects.index', compact('projects', 'workers', 'lgas', 'districts', 'streets'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'project_name' => 'required|string',
            'number_of_items' => 'required|integer|min:1',
            'project_type' => 'required|string',
            'our_reference' => 'required|string',
            'your_reference' => 'nullable|string',
            'worker_assignments' => 'required|array|min:1',
            'worker_assignments.*.user_id' => 'required|exists:sqlsrv.users,id',
        ]);

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $projectCode = $this->generateNextProjectCode();
            $projectFileNo = $this->fileNumberService->generateNextFileNumber();
            
            $project = Project::create([
                'project_name' => $request->project_name,
                'project_code' => $projectCode,
                'project_fileno' => $projectFileNo,
                'number_of_items' => $request->number_of_items,
                'street' => $request->street,
                'district' => $request->district,
                'lga' => $request->lga,
                'state' => $request->state ?? 'Kano',
                'project_type' => $request->project_type,
                'project_type_other' => $request->project_type_other,
                'our_reference' => $request->our_reference,
                'your_reference' => $request->your_reference,
                'user_id' => Auth::id(),
            ]);

            foreach ($request->worker_assignments as $index => $assignment) {
                $workerIndex = str_pad($index + 1, 3, '0', STR_PAD_LEFT);
                $workerCode = "WRK-{$project->project_code}-{$workerIndex}";

                ProjectWorker::create([
                    'project_id' => $project->id,
                    'user_id' => $assignment['user_id'],
                    'worker_code' => $workerCode,
                ]);
            }

            DB::connection('sqlsrv')->commit();

            $this->auditService->logAction('CREATED', 'Project', $project->id, null, $project->toArray(), "Created project {$project->project_name}");

            return response()->json(['success' => true, 'message' => 'Project created successfully.']);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getProjectsForSelection()
    {
        $projects = Project::withCount(['valuations', 'workers'])->get()->map(function($p) {
            return [
                'id' => $p->id,
                'name' => $p->project_name,
                'code' => $p->project_code,
                'fileno' => $p->project_fileno,
                'total_items' => $p->number_of_items,
                'valuations_count' => $p->valuations_count,
                'workers_count' => $p->workers_count,
                'our_reference' => $p->our_reference,
                'your_reference' => $p->your_reference,
            ];
        });

        return response()->json($projects);
    }

    public function getProjectWorkers($id)
    {
        $workers = ProjectWorker::where('project_id', $id)->with('user')->get();
        return response()->json($workers);
    }

    public function generateWorkerTemplates($id)
    {
        $project = Project::with('workers.user')->findOrFail($id);
        return view('valuation_compensations.projects.worker_template', compact('project'));
    }

    public function getNextCode()
    {
        return response()->json([
            'code' => $this->generateNextProjectCode(),
            'fileno' => $this->fileNumberService->peekNextFileNumber()
        ]);
    }

    private function generateNextProjectCode()
    {
        $year = \Carbon\Carbon::now()->year;
        
        // Get the next identity value for vfc_projects
        $result = DB::connection('sqlsrv')
            ->select("SELECT IDENT_CURRENT('vfc_projects') + IDENT_INCR('vfc_projects') as next_id");
            
        $nextId = $result[0]->next_id ?? 1;
        $serial = str_pad($nextId, 4, '0', STR_PAD_LEFT);

        return "PRJ-{$year}-{$serial}";
    }
}
