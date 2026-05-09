<?php

namespace App\Http\Controllers;

use App\Models\VfcWorker;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class VfcWorkerController extends Controller
{
    protected $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function getNextId()
    {
        return response()->json(['next_id' => $this->generateNextWorkerId()]);
    }

    public function index()
    {
        $workers = VfcWorker::with('user')->orderBy('vfc_worker_id')->get();
        
        // Fetch users who are not already in the pool
        $assignedUserIds = $workers->pluck('user_id')->toArray();
        $availableUsers = User::whereNotIn('id', $assignedUserIds)
            ->where('staff_type_category', '!=', 'MDCM')
            ->orderBy('first_name')
            ->get();

        return view('valuation_compensations.workers.index', compact('workers', 'availableUsers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:sqlsrv.users,id|unique:sqlsrv.vfc_workers,user_id',
        ]);

        try {
            $vfcWorkerId = $this->generateNextWorkerId();
            
            $worker = VfcWorker::create([
                'user_id' => $request->user_id,
                'vfc_worker_id' => $vfcWorkerId,
                'is_active' => true,
            ]);

            $this->auditService->logAction('CREATED', 'VfcWorker', $worker->id, null, $worker->toArray(), "Added worker {$vfcWorkerId} to VFC pool");

            return response()->json(['success' => true, 'message' => 'Worker added to pool successfully.', 'worker' => $worker->load('user')]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $worker = VfcWorker::findOrFail($id);
            $oldData = $worker->toArray();
            $worker->delete();

            $this->auditService->logAction('DELETED', 'VfcWorker', $id, $oldData, null, "Removed worker {$oldData['vfc_worker_id']} from VFC pool");

            return response()->json(['success' => true, 'message' => 'Worker removed from pool.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function generateNextWorkerId()
    {
        $year = date('Y');
        $last = VfcWorker::where('vfc_worker_id', 'like', "WRK-PRJ-{$year}-%")
            ->orderBy('vfc_worker_id', 'desc')
            ->first();
            
        if (!$last) {
            return "WRK-PRJ-{$year}-001";
        }

        $lastId = $last->vfc_worker_id;
        $parts = explode('-', $lastId);
        $num = (int) end($parts);
        $nextNum = str_pad($num + 1, 3, '0', STR_PAD_LEFT);
        
        return "WRK-PRJ-{$year}-{$nextNum}";
    }
}
