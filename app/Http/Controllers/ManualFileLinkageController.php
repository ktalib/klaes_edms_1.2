<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\PlotWorkflowService;
use App\Services\PropertyIdAllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManualFileLinkageController extends Controller
{
    /**
     * Display a listing of manually processed linkages and the entry card.
     */
    public function index()
    {
        // Only allow if user is a Super Admin
        if (Auth::user()->assign_role !== 'Supper Admin') {
            abort(403, 'Unauthorized access. Only Supper Admin can manage manually processed file linkages.');
        }

        $linkages = DB::connection('sqlsrv')
            ->table('manual_file_linkages')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.manual_linkage.index', compact('linkages'));
    }

    /**
     * AJAX Search endpoint to validate old file numbers and retrieve details.
     */
    public function searchOldFile(Request $request)
    {
        if (Auth::user()->assign_role !== 'Supper Admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $fileNumber = trim($request->input('file_number'));

        if (empty($fileNumber)) {
            return response()->json(['error' => 'File number is required.'], 400);
        }

        // Normalize year and prefix for search
        $normalized = $fileNumber;

        $indexing = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('file_number', $normalized)
            ->first();

        $fileNoRecord = DB::connection('sqlsrv')
            ->table('fileNumber')
            ->where('mlsfNo', $normalized)
            ->first();

        if (!$indexing && !$fileNoRecord) {
            return response()->json(['exists' => false, 'message' => 'File number not found in active records.']);
        }

        // Count existing transactions in other staging tables
        $cofoCount = DB::connection('sqlsrv')->table('CofO_staging')->where('mlsFNo', $normalized)->count();
        $praCount = DB::connection('sqlsrv')->table('pra')->where('mlsFNo', $normalized)->count();
        $deedCount = DB::connection('sqlsrv')->table('deed_registrations')->where('file_number', $normalized)->count();

        $details = [
            'exists' => true,
            'file_number' => $indexing->file_number ?? $fileNoRecord->mlsfNo ?? $normalized,
            'file_title' => $indexing->file_title ?? $fileNoRecord->FileName ?? 'N/A',
            'land_use' => $indexing->land_use_type ?? 'N/A',
            'plot_number' => $indexing->plot_number ?? 'N/A',
            'district' => $indexing->district ?? 'N/A',
            'lga' => $indexing->lga ?? 'N/A',
            'location' => $indexing->location ?? 'N/A',
            'prop_id' => $indexing->prop_id ?? 'None',
            'transactions' => [
                'cofo' => $cofoCount,
                'pra' => $praCount,
                'deeds' => $deedCount,
                'total' => $cofoCount + $praCount + $deedCount
            ]
        ];

        return response()->json($details);
    }

    /**
     * Store a newly created linkage.
     */
    public function store(Request $request)
    {
        if (Auth::user()->assign_role !== 'Supper Admin') {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'workflow_type' => 'required|in:Subdivision,Merger,Temporary File,Change of Purpose',
            'old_file_numbers' => 'required|array|min:1',
            'old_file_numbers.*' => 'required|string',
            'new_file_number' => 'required|string',
            'applicant_name' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
            'plot_number' => 'nullable|string|max:255',
            'plot_size' => 'nullable|string|max:255',
            'land_use_type' => 'nullable|string|max:255',
        ]);

        $workflowType = $validated['workflow_type'];
        $oldFileNumbers = array_map('trim', $validated['old_file_numbers']);
        $newFileNumber = trim($validated['new_file_number']);
        $applicantName = trim($validated['applicant_name'] ?? '');
        $remarks = trim($validated['remarks'] ?? '');

        // Prevent linking same file to itself
        if (in_array($newFileNumber, $oldFileNumbers, true)) {
            return back()->withErrors(['new_file_number' => 'New file number cannot be the same as any old file number.'])->withInput();
        }

        // Verify that the new file number is not already commissioned in fileNumber table
        $newFileExists = DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $newFileNumber)->exists();
        if ($newFileExists) {
            return back()->withErrors(['new_file_number' => 'The new file number already exists in active records. Please select a unique new file number.'])->withInput();
        }

        DB::connection('sqlsrv')->beginTransaction();

        try {
            $commissionedBy = Auth::user()->first_name . ' ' . Auth::user()->last_name;

            // 1. Fetch details of first old indexing record to copy forward before decommissioning
            $firstOldFile = $oldFileNumbers[0];
            $oldIndexing = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $firstOldFile)->first();

            // 2. Resolve/Allocate Property ID via central service
            $allocationService = app(PropertyIdAllocationService::class);
            $propId = $allocationService->allocateOrRetrievePropId(
                $newFileNumber,
                $newFileNumber,
                null,
                null,
                ['temp_fileno' => $firstOldFile]
            );

            // 3. Decommission old files using standard project service
            $workflowService = app(PlotWorkflowService::class);
            $reason = "Manual Linkage: " . $workflowType . " to " . $newFileNumber;
            $decomResult = $workflowService->decommissionFiles($oldFileNumbers, $reason, $commissionedBy);

            // 4. Create new indexings record
            DB::connection('sqlsrv')->table('file_indexings')->insert([
                'file_number' => $newFileNumber,
                'file_title' => $applicantName ?: ($oldIndexing ? $oldIndexing->file_title : 'Manual Linkage Result'),
                'land_use_type' => $request->input('land_use_type') ?: ($oldIndexing ? $oldIndexing->land_use_type : 'N/A'),
                'plot_number' => $request->input('plot_number') ?: ($oldIndexing ? $oldIndexing->plot_number : 'N/A'),
                'district' => $oldIndexing ? $oldIndexing->district : null,
                'lga' => $oldIndexing ? $oldIndexing->lga : null,
                'location' => $oldIndexing ? $oldIndexing->location : null,
                'plot_size' => $request->input('plot_size') ?: ($oldIndexing ? $oldIndexing->plot_size : null),
                'tp_no' => $oldIndexing ? $oldIndexing->tp_no : null,
                'lpkn_no' => $oldIndexing ? $oldIndexing->lpkn_no : null,
                'tracking_id' => $oldIndexing ? $oldIndexing->tracking_id : null,
                'original_holder' => $oldIndexing ? $oldIndexing->original_holder : ($applicantName ?: null),
                'current_holder' => $applicantName ?: ($oldIndexing ? $oldIndexing->current_holder : null),
                'parent_prop_id' => $oldIndexing ? $oldIndexing->parent_prop_id : null,
                'related_fileno' => json_encode($oldFileNumbers),
                'has_transaction' => 1,
                'prop_id' => $propId,
                'general_registry' => $oldIndexing ? $oldIndexing->general_registry : 'MLS',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // 5. Create new fileNumber record
            DB::connection('sqlsrv')->table('fileNumber')->insert([
                'mlsfNo' => $newFileNumber,
                'FileName' => $applicantName ?: ($oldIndexing ? $oldIndexing->file_title : 'Manual Linkage Result'),
                'tracking_id' => $oldIndexing ? $oldIndexing->tracking_id : null,
                'commissioning_date' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // 6. Record PRA transaction referencing resolved Prop ID
            DB::connection('sqlsrv')->table('pra')->insert([
                'Prop_id' => $propId,
                'mlsFNo' => $newFileNumber, // As per directive, set new file in mlsFNo only
                'temp_fileno' => null,
                'fileno' => null,
                'transaction' => $workflowType,
                'instrument' => $workflowType,
                'Property_Description_part1' => $remarks ?: ("Manual Processed: " . $workflowType . " linkage"),
                'Entry_Date' => now(),
                'Reg_Date' => now()
            ]);

            // 7. Insert audit log to manual_file_linkages
            $linkageId = DB::connection('sqlsrv')->table('manual_file_linkages')->insertGetId([
                'workflow_type' => $workflowType,
                'old_file_numbers' => json_encode($oldFileNumbers),
                'new_file_number' => $newFileNumber,
                'prop_id' => $propId,
                'applicant_name' => $applicantName ?: ($oldIndexing ? $oldIndexing->file_title : 'N/A'),
                'remarks' => $remarks,
                'processed_by' => $commissionedBy,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // 8. Log action in core Audit Service
            app(AuditService::class)->logAction(
                'MANUAL_LINKAGE',
                'manual_file_linkages',
                $linkageId,
                ['old_files' => $oldFileNumbers],
                ['new_file' => $newFileNumber, 'workflow_type' => $workflowType, 'prop_id' => $propId],
                "Manually linked {$workflowType} from " . implode(', ', $oldFileNumbers) . " to {$newFileNumber}"
            );

            DB::connection('sqlsrv')->commit();

            // Clear cache to make sure search picks up the new linkage instantly
            try {
                \Illuminate\Support\Facades\Artisan::call('cache:clear');
            } catch (\Throwable $ce) {}

            return redirect()->route('admin.manual-linkage.index')
                ->with('success', "Successfully linked manually processed files for {$workflowType}!");

        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('Manual file linkage failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors(['error' => 'An error occurred during linkage: ' . $e->getMessage()])->withInput();
        }
    }
}
