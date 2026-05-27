<?php

namespace App\Http\Controllers;

use App\Models\LandRecommendation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\PrintLog;

class LandRofoController extends Controller
{
    public function index(Request $request)
    {
        // Show approved recommendations AND OSS-type records (CoN applications ready to print)
        // Select only the columns the view needs — avoids loading large text fields (recommendation, survey_report, etc.)
        $query = LandRecommendation::with('creator')
            ->select([
                'id', 'file_number', 'applicant_name', 'purpose_of_clause', 'location',
                'plot_number', 'layout_plan_no', 'term', 'ground_rent', 'development_period',
                'survey_fees', 'development_value', 'development_charge', 'type',
                'rofo_status', 'status', 'approved_at', 'land_rofo_serial_no',
                'created_at', 'created_by', 'land_use', 'land_use_id', 'purpose_id',
            ])
            ->where(function ($q) {
                $q->where('status', LandRecommendation::STATUS_APPROVED)
                  ->orWhereRaw("UPPER(ISNULL(type, '')) = 'OSS'");
            });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('file_number', 'LIKE', "%{$search}%")
                  ->orWhere('applicant_name', 'LIKE', "%{$search}%")
                  ->orWhere('location', 'LIKE', "%{$search}%");
            });
        }

        $recommendations = $query->latest()->paginate(20);

        $PageTitle='Land RofO';
        $landUses = \App\Models\LandUse::orderBy('landuse')->get();

        // Single aggregated query for all stats to avoid multiple full-table scans
        $statsRow = DB::connection('sqlsrv')->table('land_recommendations')->selectRaw("
            COUNT(CASE WHEN UPPER(ISNULL(type,'')) <> 'OSS' AND status = 'approved'                                          THEN 1 END)   AS total_eligible,
            COUNT(CASE WHEN UPPER(ISNULL(type,'')) <> 'OSS' AND status = 'approved' AND ISNULL(rofo_status,'') = 'pending'   THEN 1 END)   AS pending_generation,
            COUNT(CASE WHEN UPPER(ISNULL(type,'')) <> 'OSS' AND ISNULL(rofo_status,'') = 'generated'                        THEN 1 END)   AS generated,
            ISNULL(SUM(CASE WHEN UPPER(ISNULL(type,'')) <> 'OSS' AND ISNULL(rofo_status,'') = 'generated' THEN ISNULL(rofo_dev_charge,0) ELSE 0 END), 0) AS total_dev_charge
        ")->first();

        // Count OSS Applications from the authoritative source (oss_applications) so
        // the stat matches the Change of Name page instead of counting type='OSS' rows
        // in land_recommendations which may have duplicates or test records.
        $ossColumns = DB::connection('sqlsrv')->getSchemaBuilder()->getColumnListing('oss_applications');
        $ossHasIsDeleted = in_array('is_deleted', array_map('strtolower', $ossColumns));
        $ossTotal = DB::connection('sqlsrv')->table('oss_applications')
            ->where('system_source', 'OSSOPCHANGEOFNAME')
            ->where(function ($q) use ($ossHasIsDeleted) {
                if ($ossHasIsDeleted) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                }
            })
            ->count();

        $stats = [
            'total_eligible'    => (int) ($statsRow->total_eligible    ?? 0),
            'pending_generation'=> (int) ($statsRow->pending_generation ?? 0),
            'generated'         => (int) ($statsRow->generated          ?? 0),
            'total_dev_charge'  => (float) ($statsRow->total_dev_charge ?? 0),
            'oss_total'         => $ossTotal,
        ];

        // Only fetch the paper_code column — the view only ever reads s.paper_code
        $availableSerials = DB::connection('sqlsrv')->table('global_security_paper_codes')
            ->select('paper_code')
            ->where('is_used', false)
            ->orderBy('paper_code', 'asc')
            ->get();

        return view('land_rofos.index', compact('recommendations', 'PageTitle', 'landUses', 'stats', 'availableSerials'));
    }

    public function assignSecurityPaperCode(Request $request, $id)
    {
        $request->validate([
            'paper_code' => 'required|string|exists:sqlsrv.global_security_paper_codes,paper_code',
        ]);

        $recommendation = LandRecommendation::findOrFail($id);

        DB::connection('sqlsrv')->beginTransaction();
        try {
            // Check if paper code is already used
            $serial = DB::connection('sqlsrv')->table('global_security_paper_codes')
                ->where('paper_code', $request->paper_code)
                ->first();

            if ($serial->is_used) {
                return response()->json(['success' => false, 'message' => 'Security paper code already in use.'], 422);
            }

            // If recommendation already has a paper code, mark the old one as unused
            if ($recommendation->land_rofo_serial_no) {
                DB::connection('sqlsrv')->table('global_security_paper_codes')
                    ->where('paper_code', $recommendation->land_rofo_serial_no)
                    ->update([
                        'is_used' => false,
                        'assigned_to_type' => null,
                        'assigned_to_id' => null,
                        'assigned_by' => null,
                        'assigned_at' => null,
                    ]);
            }

            // Assign new paper code
            $recommendation->update(['land_rofo_serial_no' => $request->paper_code]);

            DB::connection('sqlsrv')->table('global_security_paper_codes')
                ->where('paper_code', $request->paper_code)
                ->update([
                    'is_used' => true,
                    'assigned_to_type' => 'LandRecommendation',
                    'assigned_to_id' => $recommendation->id,
                    'assigned_by' => Auth::id(),
                    'assigned_at' => now(),
                ]);

            // Also update security_codes table for tracking/linking
            DB::connection('sqlsrv')->table('security_codes')->insert([
                'code' => 'L-' . $request->paper_code, // Use L- prefix for Land
                'security_paper_code' => $request->paper_code,
                'used_security_paper_code' => $request->paper_code,
                'is_used' => true,
                'assigned_to' => 'Land ROFO',
                'assigned_by' => Auth::id(),
                'assigned_at' => now(),
                'file_number' => $recommendation->file_number,
                'document_id' => $recommendation->id,
                'document_type' => 'Land ROFO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::connection('sqlsrv')->commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function generate(Request $request, $id)
    {
        $recommendation = LandRecommendation::findOrFail($id);
        
        if ($recommendation->status !== LandRecommendation::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Recommendation must be approved before generating ROFO.'
            ], 403);
        }

        $validated = $request->validate([
            'rofo_survey_fees' => 'nullable|numeric',
            'rofo_dev_charge' => 'nullable|numeric',
            'rofo_director_survey' => 'nullable|string|in:YES,NO',
            'rofo_licensed_surveyor' => 'nullable|string|in:YES,NO',
            'rofo_land_use_category' => 'nullable|string',
            'rofo_date_generated' => 'nullable|date',
            'rofo_time_generated' => 'nullable|string',
            'land_use_id' => 'nullable|exists:sqlsrv.land_uses,id',
            'purpose_id' => 'nullable|exists:sqlsrv.purposes,id',
        ]);

        if ($request->filled('land_use_id')) {
            $lu = \App\Models\LandUse::find($request->land_use_id);
            if ($lu) $validated['land_use'] = $lu->landuse;
        }

        if ($request->filled('purpose_id')) {
            $p = \App\Models\Purpose::find($request->purpose_id);
            if ($p) $validated['purpose_of_clause'] = $p->name;
        }

        // Use stored values if the request is empty (quick-generate from index)
        $mergedDate = $request->rofo_date_generated ?: $recommendation->rofo_date_generated;
        $mergedTime = $request->rofo_time_generated ?: $recommendation->rofo_time_generated;

        $generatedAt = now();
        if ($mergedDate && $mergedTime) {
            $generatedAt = \Carbon\Carbon::parse($mergedDate . ' ' . $mergedTime);
        } elseif ($mergedDate) {
            $generatedAt = \Carbon\Carbon::parse($mergedDate);
        }

        // Fill missing validated fields from stored record when quick-generating
        if (empty($validated['rofo_director_survey']))  $validated['rofo_director_survey']  = $recommendation->rofo_director_survey;
        if (empty($validated['rofo_licensed_surveyor'])) $validated['rofo_licensed_surveyor'] = $recommendation->rofo_licensed_surveyor;
        if (empty($validated['rofo_survey_fees']))       $validated['rofo_survey_fees']       = $recommendation->survey_fees ?? $recommendation->preparation_fees;
        if (empty($validated['rofo_dev_charge']))        $validated['rofo_dev_charge']        = $recommendation->development_charge;
        if (empty($validated['rofo_land_use_category'])) $validated['rofo_land_use_category'] = $recommendation->land_use;

        $recommendation->update(array_merge($validated, [
            'rofo_status'        => LandRecommendation::ROFO_GENERATED,
            'rofo_generated_at'  => $generatedAt,
            'rofo_date_generated'=> $mergedDate,
            'rofo_time_generated'=> $mergedTime,
        ]));

        return response()->json(['success' => true]);
    }

    public function print(Request $request, $id)
    {
        $recommendation = LandRecommendation::findOrFail($id);

        // Resolve land_use text from land_use_id if the text column is empty
        if (empty($recommendation->land_use) && $recommendation->land_use_id) {
            $lu = \App\Models\LandUse::find($recommendation->land_use_id);
            if ($lu) $recommendation->land_use = $lu->landuse;
        }

        // Resolve purpose_of_clause text from purpose_id if the text column is empty
        if (empty($recommendation->purpose_of_clause) && $recommendation->purpose_id) {
            $p = \App\Models\Purpose::find($recommendation->purpose_id);
            if ($p) $recommendation->purpose_of_clause = $p->name;
        }

        // Bypass limit check for Certified True Copy
        $isCTC = $request->query('status') === 'CTC' || $request->query('isCTC') == 1;
        // Generate security code for this print
        $securityCodeService = app(\App\Services\SecurityCodeService::class);
        $securityCode = $securityCodeService->getOrGenerateForDocument(
            $recommendation->file_number,
            $recommendation->id,
            'Land ROFO'
        );

        if (!$isCTC && $recommendation->rofo_print_count >= 2) {
            abort(403, 'Maximum ROFO print limit reached.');
        }

        return view('land_rofos.templates.rofo_print', compact('recommendation', 'securityCode'));
    }

    public function unprintedJson()
    {
        // File numbers already batch-printed
        $printed = DB::connection('sqlsrv')->table('print_logs')
            ->where('document_type', 'Land ROFO')
            ->where('print_type', 'LandRofoBatch')
            ->pluck('reference_number')
            ->map(fn($r) => strtoupper(trim((string) $r)))
            ->unique()
            ->all();

        $records = LandRecommendation::select([
                'id', 'file_number', 'applicant_name', 'location', 'plot_number',
                'land_rofo_serial_no', 'rofo_status',
            ])
            ->where('rofo_status', LandRecommendation::ROFO_GENERATED)
            ->get()
            ->filter(fn($r) => !in_array(strtoupper(trim((string) $r->file_number)), $printed))
            ->values();

        return response()->json(['success' => true, 'data' => $records, 'count' => $records->count()]);
    }

    public function batchPrint(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []), 'is_numeric');

        $records = LandRecommendation::whereIn('id', $ids)
            ->where('rofo_status', LandRecommendation::ROFO_GENERATED)
            ->get();

        // Use the same service the individual print uses so codes are consistent
        $securityCodeService = app(\App\Services\SecurityCodeService::class);
        $securityCodes = [];
        foreach ($records as $rec) {
            $sc = $securityCodeService->getOrGenerateForDocument(
                $rec->file_number,
                $rec->id,
                'Land ROFO'
            );
            if ($sc) {
                $securityCodes[$rec->id] = $sc;
            }
        }

        return view('land_rofos.templates.batch_rofo_print', compact('records', 'securityCodes'));
    }

    public function batchPrintLog(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []), 'is_numeric');

        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No records specified.'], 422);
        }

        $records = LandRecommendation::whereIn('id', $ids)
            ->where('rofo_status', LandRecommendation::ROFO_GENERATED)
            ->get();

        DB::connection('sqlsrv')->beginTransaction();
        try {
            foreach ($records as $rec) {
                foreach (['Original', 'Duplicate', 'Triplicate'] as $copy) {
                    PrintLog::create([
                        'reference_number' => $rec->file_number,
                        'document_type'    => 'Land ROFO',
                        'print_type'       => 'LandRofoBatch',
                        'status'           => $copy,
                        'user_id'          => Auth::id(),
                    ]);
                }
                $rec->increment('rofo_print_count');
            }
            DB::connection('sqlsrv')->commit();
            return response()->json(['success' => true, 'count' => $records->count()]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function logPrint(Request $request, $id)
    {
        $recommendation = LandRecommendation::findOrFail($id);

        $status = $request->query('status', 'Original');
        $isCTC = $status === 'CTC' || $request->query('isCTC') == 1;

        // Only enforce limits for non-CTC prints
        if (!$isCTC && $recommendation->rofo_print_count >= 2) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum ROFO print limit reached.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            PrintLog::create([
                'reference_number' => $recommendation->file_number,
                'document_type' => 'Land ROFO',
                'print_type' => 'Individual',
                'status' => $status,
                'user_id' => Auth::id()
            ]);

            // Only increment count for non-CTC prints
            if ($status !== 'CTC') {
                $recommendation->increment('rofo_print_count');
            }

            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error logging ROFO print: ' . $e->getMessage()
            ], 500);
        }
    }
}
