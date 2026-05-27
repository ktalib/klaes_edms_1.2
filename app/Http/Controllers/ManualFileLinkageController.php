<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\PlotWorkflowService;
use App\Services\PropertyIdAllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ManualFileLinkageController extends Controller
{
    public function index()
    {
        if (Auth::user()->assign_role !== 'Supper Admin') {
            abort(403, 'Unauthorized access. Only Supper Admin can manage manually processed file linkages.');
        }

        $linkages = DB::connection('sqlsrv')
            ->table('manual_file_linkages')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $states     = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas       = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts  = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = DB::connection('sqlsrv')->table('street_names')->orderBy('name')->get(['id', 'name']);

        return view('admin.manual_linkage.index', compact('linkages', 'states', 'lgas', 'districts', 'streetNames'));
    }

    /**
     * AJAX: Validate a file number and return its details including decommission + already-linked status.
     */
    public function searchOldFile(Request $request)
    {
        if (Auth::user()->assign_role !== 'Supper Admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $fileNumber = strtoupper(trim((string) $request->input('file_number')));

        if (empty($fileNumber)) {
            return response()->json(['error' => 'File number is required.'], 400);
        }

        $indexing    = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $fileNumber)->first();
        $fileNoRecord = DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $fileNumber)->first();

        if (!$indexing && !$fileNoRecord) {
            // Check if it already sits in the decommissioned archive
            $decommissioned = DB::connection('sqlsrv')->table('decommissioned_files')
                ->where('mls_file_no', $fileNumber)
                ->orderBy('decommissioning_date', 'desc')
                ->first();

            if ($decommissioned) {
                return response()->json([
                    'exists'                 => false,
                    'is_decommissioned'      => true,
                    'decommissioning_reason' => $decommissioned->decommissioning_reason ?? null,
                    'message'                => "File {$fileNumber} is already decommissioned"
                        . ($decommissioned->decommissioning_date
                            ? ' (archived ' . \Carbon\Carbon::parse($decommissioned->decommissioning_date)->format('d/m/Y') . ')'
                            : '')
                        . ($decommissioned->decommissioning_reason ? ': ' . $decommissioned->decommissioning_reason : '.'),
                ]);
            }

            return response()->json(['exists' => false, 'message' => 'File number not found in active records.']);
        }

        // Transaction counts across staging tables
        $cofoCount = Schema::connection('sqlsrv')->hasTable('CofO_staging')
            ? DB::connection('sqlsrv')->table('CofO_staging')->where('mlsFNo', $fileNumber)->count()
            : 0;
        $praCount = Schema::connection('sqlsrv')->hasTable('pra')
            ? DB::connection('sqlsrv')->table('pra')->where('mlsFNo', $fileNumber)->count()
            : 0;
        $deedTable = Schema::connection('sqlsrv')->hasTable('deeds_registrations') ? 'deeds_registrations' : 'deed_registrations';
        $deedCount = Schema::connection('sqlsrv')->hasTable($deedTable)
            ? DB::connection('sqlsrv')->table($deedTable)->where('file_number', $fileNumber)->count()
            : 0;

        // Check if this file was already used as a source in a previous manual linkage
        $existingLinkage = DB::connection('sqlsrv')
            ->table('manual_file_linkages')
            ->whereRaw("old_file_numbers LIKE ?", ['%"' . $fileNumber . '"%'])
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'exists'           => true,
            'file_number'      => $indexing->file_number ?? $fileNoRecord->mlsfNo ?? $fileNumber,
            'file_title'       => $indexing->file_title ?? $fileNoRecord->FileName ?? 'N/A',
            'land_use'         => $indexing->land_use_type ?? 'N/A',
            'plot_number'      => $indexing->plot_number ?? 'N/A',
            'district'         => $indexing->district ?? 'N/A',
            'lga'              => $indexing->lga ?? 'N/A',
            'location'         => $indexing->location ?? 'N/A',
            'prop_id'          => $indexing->prop_id ?? 'None',
            'is_decommissioned' => false,
            'existing_linkage'  => $existingLinkage ? [
                'workflow_type'  => $existingLinkage->workflow_type,
                'new_file_number' => $existingLinkage->new_file_number,
                'date'           => \Carbon\Carbon::parse($existingLinkage->created_at)->format('d/m/Y'),
                'processed_by'   => $existingLinkage->processed_by,
            ] : null,
            'transactions' => [
                'cofo'  => $cofoCount,
                'pra'   => $praCount,
                'deeds' => $deedCount,
                'total' => $cofoCount + $praCount + $deedCount,
            ],
        ]);
    }

    /**
     * Persist the linkage, decommission old files, patch lineage on new files.
     * For Subdivision, accepts a children[] array so all N child plots are linked in one transaction.
     */
    public function store(Request $request)
    {
        if (Auth::user()->assign_role !== 'Supper Admin') {
            abort(403, 'Unauthorized.');
        }

        $workflowType = $request->input('workflow_type');

        // Strip empty values submitted by hidden inputs in non-active panels
        $request->merge([
            'old_file_numbers' => array_values(array_filter(
                $request->input('old_file_numbers', []),
                fn($v) => trim((string) $v) !== ''
            )),
        ]);

        // --- Validation -------------------------------------------------------
        $baseRules = [
            'workflow_type'      => 'required|in:Subdivision,Merger,Plot Extension,Change of Purpose',
            'old_file_numbers'   => 'required|array|min:1',
            'old_file_numbers.*' => 'required|string',
            'applicant_name'     => 'nullable|string|max:255',
            'file_title'         => 'nullable|string|max:500',
            'approval_reference' => 'nullable|string|max:255',
            'approval_date'      => 'nullable|date',
            'remarks'            => 'nullable|string',
            'plot_number'        => 'nullable|string|max:255',
            'plot_size'          => 'nullable|string|max:255',
            'land_use_type'      => 'nullable|string|max:255',
            'land_value'         => 'nullable|numeric',
            'knupda_fee'         => 'nullable|numeric',
            'plan_no'            => 'nullable|string|max:255',
            'house_no'           => 'nullable|string|max:255',
            'street_name'        => 'nullable|string|max:255',
            'district'           => 'nullable|string|max:255',
            'lga'                => 'nullable|string|max:255',
            'state'              => 'nullable|string|max:255',
            'location'           => 'nullable|string|max:2000',
            'land_size'          => 'nullable|numeric',
            'purpose'            => 'nullable|string|max:50',
        ];

        if ($workflowType === 'Subdivision') {
            $baseRules['children']                      = 'required|array|min:1';
            $baseRules['children.*.new_file_number']    = 'required|string';
            $baseRules['children.*.plot_number']        = 'nullable|string|max:100';
            $baseRules['children.*.plot_size']          = 'nullable|string|max:100';
            $baseRules['children.*.survey_plan_no']     = 'nullable|string|max:255';
        } else {
            $baseRules['new_file_number'] = 'required|string';
        }

        $validated = $request->validate($baseRules);

        // --- Normalize inputs -------------------------------------------------
        $oldFileNumbers = array_values(array_unique(array_map(
            fn ($f) => strtoupper(trim((string) $f)),
            $validated['old_file_numbers']
        )));

        $applicantName    = trim($validated['applicant_name'] ?? '');
        $fileTitle        = trim($validated['file_title'] ?? '');
        $remarks          = trim($validated['remarks'] ?? '');
        $approvalReference = trim($validated['approval_reference'] ?? '');
        $approvalDate     = $validated['approval_date'] ?? null;

        // Normalize children for Subdivision
        $children = [];
        if ($workflowType === 'Subdivision') {
            foreach ($validated['children'] as $child) {
                $children[] = [
                    'new_file_number' => strtoupper(trim($child['new_file_number'])),
                    'plot_number'     => trim($child['plot_number'] ?? ''),
                    'plot_size'       => trim($child['plot_size'] ?? ''),
                    'survey_plan_no'  => trim($child['survey_plan_no'] ?? ''),
                ];
            }
            // Guard: no child file can equal the parent
            foreach ($children as $child) {
                if (in_array($child['new_file_number'], $oldFileNumbers, true)) {
                    return back()
                        ->withErrors(['error' => "Child file {$child['new_file_number']} cannot equal the parent file."])
                        ->withInput();
                }
            }
        } else {
            $newFileNumber = strtoupper(trim($validated['new_file_number']));
            if (in_array($newFileNumber, $oldFileNumbers, true)) {
                return back()
                    ->withErrors(['new_file_number' => 'New file number cannot be the same as any old file number.'])
                    ->withInput();
            }
        }

        // --- Duplicate guard --------------------------------------------------
        // Reject if any old file is already recorded as a source in manual_file_linkages
        $alreadyLinked = DB::connection('sqlsrv')
            ->table('manual_file_linkages')
            ->where(function ($q) use ($oldFileNumbers) {
                foreach ($oldFileNumbers as $fn) {
                    $q->orWhereRaw("old_file_numbers LIKE ?", ['%"' . $fn . '"%']);
                }
            })
            ->get(['workflow_type', 'new_file_number', 'old_file_numbers', 'created_at']);

        if ($alreadyLinked->isNotEmpty()) {
            $conflicts = $alreadyLinked->map(fn ($l) =>
                implode(', ', json_decode($l->old_file_numbers, true) ?? [$l->old_file_numbers])
                . ' → ' . $l->new_file_number
                . ' (' . $l->workflow_type . ' on '
                . \Carbon\Carbon::parse($l->created_at)->format('d/m/Y') . ')'
            )->implode('; ');

            return back()
                ->withErrors(['error' => 'One or more source files are already linked: ' . $conflicts])
                ->withInput();
        }

        // --- Main transaction -------------------------------------------------
        DB::connection('sqlsrv')->beginTransaction();

        try {
            $commissionedBy = Auth::user()->first_name . ' ' . Auth::user()->last_name;
            $firstOldFile   = $oldFileNumbers[0];

            // Fetch details of the first source file BEFORE decommissioning deletes it
            $oldIndexing = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('file_number', $firstOldFile)
                ->first();

            // Collect old prop_ids BEFORE decommission removes the active records
            $oldPropIds = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->whereIn('file_number', $oldFileNumbers)
                ->whereNotNull('prop_id')
                ->pluck('prop_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($oldPropIds) && !empty($oldIndexing->prop_id)) {
                $oldPropIds = [(int) $oldIndexing->prop_id];
            }

            // Decommission old files once (regardless of child count for Subdivision)
            $workflowService = app(PlotWorkflowService::class);
            $decommReason = $workflowType === 'Subdivision'
                ? 'Subdivision → ' . implode(', ', array_column($children, 'new_file_number'))
                : "Manual Linkage: {$workflowType} → {$newFileNumber}";
            $workflowService->decommissionFiles($oldFileNumbers, $decommReason, $commissionedBy);

            $allocationService = app(PropertyIdAllocationService::class);
            $linkageGroupId    = Str::uuid()->toString();

            // ----------------------------------------------------------------
            if ($workflowType === 'Subdivision') {
                $parentPropId = $oldPropIds[0] ?? null;

                foreach ($children as $childData) {
                    $childFileNumber  = $childData['new_file_number'];
                    $childNewIndexing = DB::connection('sqlsrv')
                        ->table('file_indexings')
                        ->where('file_number', $childFileNumber)
                        ->first();

                    $childPropId = $childNewIndexing->prop_id ?? $allocationService->allocateOrRetrievePropId(
                        $childFileNumber,
                        $childFileNumber,
                        null,
                        null,
                        ['skip_lookup' => true, 'temp_fileno' => $firstOldFile]
                    );

                    // file_indexings row for this child
                    $indexingPayload = [
                        'file_number'      => $childFileNumber,
                        'file_title'       => $childNewIndexing->file_title
                            ?? ($fileTitle ?: ($applicantName ?: ($oldIndexing->file_title ?? 'Manual Linkage Result'))),
                        'land_use_type'    => $request->input('land_use_type')
                            ?: ($childNewIndexing->land_use_type ?? ($oldIndexing->land_use_type ?? 'N/A')),
                        'plot_number'      => $childData['plot_number']
                            ?: ($childNewIndexing->plot_number ?? ($oldIndexing->plot_number ?? 'N/A')),
                        'district'         => $request->input('district')
                            ?: ($childNewIndexing->district ?? ($oldIndexing->district ?? null)),
                        'lga'              => $request->input('lga')
                            ?: ($childNewIndexing->lga ?? ($oldIndexing->lga ?? null)),
                        'location'         => $request->input('location')
                            ?: ($childNewIndexing->location ?? ($oldIndexing->location ?? null)),
                        'plot_size'        => $childData['plot_size']
                            ?: ($childNewIndexing->plot_size ?? null),
                        'tp_no'            => $childNewIndexing->tp_no ?? ($oldIndexing->tp_no ?? null),
                        'lpkn_no'          => $childNewIndexing->lpkn_no ?? ($oldIndexing->lpkn_no ?? null),
                        'tracking_id'      => $childNewIndexing->tracking_id ?? ($oldIndexing->tracking_id ?? null),
                        'original_holder'  => $childNewIndexing->original_holder
                            ?? ($oldIndexing->original_holder ?? ($applicantName ?: null)),
                        'current_holder'   => $applicantName
                            ?: ($childNewIndexing->current_holder ?? ($oldIndexing->current_holder ?? null)),
                        'parent_prop_id'   => $parentPropId,
                        'related_fileno'   => json_encode($oldFileNumbers),
                        'has_transaction'  => 1,
                        'prop_id'          => $childPropId,
                        'general_registry' => $childNewIndexing->general_registry
                            ?? ($oldIndexing->general_registry ?? 'MLS'),
                        'updated_at'       => now(),
                    ];

                    if ($childNewIndexing) {
                        DB::connection('sqlsrv')->table('file_indexings')
                            ->where('id', $childNewIndexing->id)
                            ->update($indexingPayload);
                    } else {
                        $indexingPayload['created_at'] = now();
                        DB::connection('sqlsrv')->table('file_indexings')->insert($indexingPayload);
                    }

                    // fileNumber row for this child
                    $childFileNoRecord = DB::connection('sqlsrv')
                        ->table('fileNumber')
                        ->where('mlsfNo', $childFileNumber)
                        ->first();

                    $fileNumberPayload = [
                        'mlsfNo'              => $childFileNumber,
                        'FileName'            => $fileTitle
                            ?: ($applicantName ?: ($childFileNoRecord->FileName ?? ($oldIndexing->file_title ?? 'Manual Linkage Result'))),
                        'tracking_id'         => $childFileNoRecord->tracking_id ?? ($oldIndexing->tracking_id ?? null),
                        'commissioning_date'  => $childFileNoRecord->commissioning_date ?? now(),
                        'updated_at'          => now(),
                    ];
                    if (Schema::connection('sqlsrv')->hasColumn('fileNumber', 'parent_prop_id')) {
                        $fileNumberPayload['parent_prop_id'] = $parentPropId;
                    }
                    if (Schema::connection('sqlsrv')->hasColumn('fileNumber', 'related_fileno')) {
                        $fileNumberPayload['related_fileno'] = json_encode($oldFileNumbers);
                    }

                    if ($childFileNoRecord) {
                        DB::connection('sqlsrv')->table('fileNumber')
                            ->where('id', $childFileNoRecord->id)
                            ->update($fileNumberPayload);
                    } else {
                        $fileNumberPayload['created_at'] = now();
                        DB::connection('sqlsrv')->table('fileNumber')->insert($fileNumberPayload);
                    }

                    // PRA transaction for this child
                    DB::connection('sqlsrv')->table('pra')->insert([
                        'prop_id'              => $childPropId,
                        'mlsFNo'               => $childFileNumber,
                        'title_type'           => 'Subdivision',
                        'transaction_type'     => 'Plot Subdivision',
                        'property_description' => $remarks
                            ?: "Manual Processed: Plot Subdivision from {$firstOldFile}",
                        'created_at'           => now(),
                        'updated_at'           => now(),
                    ]);

                    // Audit row in manual_file_linkages for this child
                    $this->insertLinkageRow(
                        'Subdivision',
                        $oldFileNumbers,
                        $childFileNumber,
                        $childPropId,
                        $applicantName ?: ($oldIndexing->file_title ?? 'N/A'),
                        $remarks,
                        $commissionedBy,
                        $approvalReference,
                        $approvalDate,
                        $childData['plot_number'] ?: null,
                        $childData['plot_size'] ?: null,
                        $childData['survey_plan_no'] ?: null,
                        $linkageGroupId
                    );
                }

                app(AuditService::class)->logAction(
                    'MANUAL_LINKAGE',
                    'manual_file_linkages',
                    0,
                    ['old_files' => $oldFileNumbers],
                    [
                        'children'          => array_column($children, 'new_file_number'),
                        'workflow_type'     => 'Subdivision',
                        'linkage_group_id'  => $linkageGroupId,
                    ],
                    "Manually linked Subdivision from {$firstOldFile} to "
                        . count($children) . ' child plot(s)'
                );

                DB::connection('sqlsrv')->commit();
                $this->clearCache();

                $childCount = count($children);
                return redirect()->route('admin.manual-linkage.index')
                    ->with('success', "Subdivision linkage saved: {$firstOldFile} → {$childCount} child plot(s) decommissioned and linked.");

            } else {
                // --- Merger / Plot Extension / Change of Purpose -------------
                $newIndexing      = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $newFileNumber)->first();
                $existingNewPropId = $newIndexing->prop_id ?? null;

                $propId = $existingNewPropId ?: $allocationService->allocateOrRetrievePropId(
                    $newFileNumber,
                    $newFileNumber,
                    null,
                    null,
                    ['temp_fileno' => $firstOldFile]
                );

                $parentPropId = !empty($oldPropIds)
                    ? implode(',', $oldPropIds)
                    : ($oldIndexing->prop_id ?? null);

                $indexingPayload = [
                    'file_number'      => $newFileNumber,
                    'file_title'       => $newIndexing->file_title
                        ?? ($fileTitle ?: ($applicantName ?: ($oldIndexing ? $oldIndexing->file_title : 'Manual Linkage Result'))),
                    'land_use_type'    => $request->input('land_use_type')
                        ?: ($newIndexing->land_use_type ?? ($oldIndexing->land_use_type ?? 'N/A')),
                    'plot_number'      => $request->input('plot_number')
                        ?: ($newIndexing->plot_number ?? ($oldIndexing->plot_number ?? 'N/A')),
                    'district'         => $request->input('district')
                        ?: ($newIndexing->district ?? ($oldIndexing->district ?? null)),
                    'lga'              => $request->input('lga')
                        ?: ($newIndexing->lga ?? ($oldIndexing->lga ?? null)),
                    'location'         => $request->input('location')
                        ?: ($newIndexing->location ?? ($oldIndexing->location ?? null)),
                    'plot_size'        => $request->input('plot_size')
                        ?: ($newIndexing->plot_size ?? ($oldIndexing->plot_size ?? null)),
                    'tp_no'            => $newIndexing->tp_no ?? ($oldIndexing->tp_no ?? null),
                    'lpkn_no'          => $newIndexing->lpkn_no ?? ($oldIndexing->lpkn_no ?? null),
                    'tracking_id'      => $newIndexing->tracking_id ?? ($oldIndexing->tracking_id ?? null),
                    'original_holder'  => $newIndexing->original_holder
                        ?? ($oldIndexing->original_holder ?? ($applicantName ?: null)),
                    'current_holder'   => $applicantName
                        ?: ($newIndexing->current_holder ?? ($oldIndexing->current_holder ?? null)),
                    'parent_prop_id'   => $parentPropId,
                    'related_fileno'   => json_encode($oldFileNumbers),
                    'has_transaction'  => 1,
                    'prop_id'          => $propId,
                    'general_registry' => $newIndexing->general_registry
                        ?? ($oldIndexing->general_registry ?? 'MLS'),
                    'updated_at'       => now(),
                ];

                if ($newIndexing) {
                    DB::connection('sqlsrv')->table('file_indexings')
                        ->where('id', $newIndexing->id)
                        ->update($indexingPayload);
                } else {
                    $indexingPayload['created_at'] = now();
                    DB::connection('sqlsrv')->table('file_indexings')->insert($indexingPayload);
                }

                $newFileNumberRecord = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->where('mlsfNo', $newFileNumber)
                    ->first();

                $fileNumberPayload = [
                    'mlsfNo'             => $newFileNumber,
                    'FileName'           => $fileTitle
                        ?: ($applicantName ?: ($newFileNumberRecord->FileName ?? ($oldIndexing ? $oldIndexing->file_title : 'Manual Linkage Result'))),
                    'tracking_id'        => $newFileNumberRecord->tracking_id ?? ($oldIndexing->tracking_id ?? null),
                    'commissioning_date' => $newFileNumberRecord->commissioning_date ?? now(),
                    'updated_at'         => now(),
                ];
                if (Schema::connection('sqlsrv')->hasColumn('fileNumber', 'parent_prop_id')) {
                    $fileNumberPayload['parent_prop_id'] = $parentPropId;
                }
                if (Schema::connection('sqlsrv')->hasColumn('fileNumber', 'related_fileno')) {
                    $fileNumberPayload['related_fileno'] = json_encode($oldFileNumbers);
                }

                if ($newFileNumberRecord) {
                    DB::connection('sqlsrv')->table('fileNumber')
                        ->where('id', $newFileNumberRecord->id)
                        ->update($fileNumberPayload);
                } else {
                    $fileNumberPayload['created_at'] = now();
                    DB::connection('sqlsrv')->table('fileNumber')->insert($fileNumberPayload);
                }

                if (in_array($workflowType, ['Merger', 'Plot Extension', 'Change of Purpose'], true) && !empty($oldPropIds)) {
                    $workflowService->updateHistoricalPropId($oldPropIds, (int) $propId);
                }

                DB::connection('sqlsrv')->table('pra')->insert([
                    'prop_id'              => $propId,
                    'mlsFNo'               => $newFileNumber,
                    'title_type'           => $workflowType,
                    'transaction_type'     => $workflowType,
                    'property_description' => $remarks ?: "Manual Processed: {$workflowType} linkage",
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);

                $linkageId = $this->insertLinkageRow(
                    $workflowType,
                    $oldFileNumbers,
                    $newFileNumber,
                    $propId,
                    $applicantName ?: ($oldIndexing ? $oldIndexing->file_title : 'N/A'),
                    $remarks,
                    $commissionedBy,
                    $approvalReference,
                    $approvalDate,
                    null, null, null,
                    $linkageGroupId
                );

                app(AuditService::class)->logAction(
                    'MANUAL_LINKAGE',
                    'manual_file_linkages',
                    $linkageId,
                    ['old_files' => $oldFileNumbers],
                    ['new_file' => $newFileNumber, 'workflow_type' => $workflowType, 'prop_id' => $propId],
                    "Manually linked {$workflowType} from " . implode(', ', $oldFileNumbers) . " to {$newFileNumber}"
                );

                DB::connection('sqlsrv')->commit();
                $this->clearCache();

                return redirect()->route('admin.manual-linkage.index')
                    ->with('success', "Successfully linked manually processed files for {$workflowType}!");
            }

        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('Manual file linkage failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withErrors(['error' => 'An error occurred during linkage: ' . $e->getMessage()])
                ->withInput();
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function insertLinkageRow(
        string  $workflowType,
        array   $oldFileNumbers,
        string  $newFileNumber,
        mixed   $propId,
        string  $applicantName,
        string  $remarks,
        string  $commissionedBy,
        string  $approvalReference,
        ?string $approvalDate,
        ?string $childPlotNumber,
        ?string $childPlotSize,
        ?string $surveyPlanNo,
        string  $linkageGroupId
    ): int {
        $row = [
            'workflow_type'    => $workflowType,
            'old_file_numbers' => json_encode($oldFileNumbers),
            'new_file_number'  => $newFileNumber,
            'prop_id'          => $propId,
            'applicant_name'   => $applicantName,
            'remarks'          => $remarks,
            'processed_by'     => $commissionedBy,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];

        $optional = [
            'approval_reference' => $approvalReference ?: null,
            'approval_date'      => $approvalDate ?: null,
            'child_plot_number'  => $childPlotNumber,
            'child_plot_size'    => $childPlotSize,
            'survey_plan_no'     => $surveyPlanNo,
            'linkage_group_id'   => $linkageGroupId,
        ];

        foreach ($optional as $col => $value) {
            if (Schema::connection('sqlsrv')->hasColumn('manual_file_linkages', $col)) {
                $row[$col] = $value;
            }
        }

        return (int) DB::connection('sqlsrv')->table('manual_file_linkages')->insertGetId($row);
    }

    private function clearCache(): void
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
        } catch (\Throwable $e) {
            // non-fatal
        }
    }
}
